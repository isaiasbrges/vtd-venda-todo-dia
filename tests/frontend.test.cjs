const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

function app() {
  const listeners = {};
  const elements = new Map();
  const element = () => ({
    innerHTML: '', textContent: '', hidden: true, dataset: {},
    setAttribute() {}, removeAttribute() {},
    classList: { add() {}, remove() {}, contains() { return false; } },
    querySelector() { return null; }, querySelectorAll() { return []; }
  });
  for (const id of ['#app', '#toast', '#page-progress']) elements.set(id, element());
  const context = vm.createContext({
    console: { error() {} }, URL, URLSearchParams, FormData, AbortController,
    location: { hash: '#/login' },
    window: { scrollY: 0, scrollTo() {}, addEventListener() {} },
    document: {
      activeElement: null, body: element(),
      querySelector(selector) { return elements.get(selector) || null; },
      querySelectorAll() { return []; },
      getElementById(id) { return elements.get('#' + id) || null; },
      createElement: element,
      addEventListener(type, callback) { listeners[type] = callback; }
    },
    setTimeout(callback, delay) { const timer = setTimeout(callback, delay); timer.unref(); return timer; },
    clearTimeout,
    fetch: async () => { throw new Error('Unexpected request'); }
  });
  for (const file of ['master.js', 'app.js']) vm.runInContext(fs.readFileSync(path.join(__dirname, '..', file), 'utf8'), context);
  return { context, listeners, elements, run: source => vm.runInContext(source, context) };
}

const json = data => ({ ok: true, status: 200, json: async () => ({ success: true, data }) });

test('favoritos consultam a API com filtro e paginação reais', async () => {
  const a = app();
  let requested;
  a.context.fetch = async url => { requested = url; return json({ suppliers: [{ id: '50', nome: 'Empresa fora da primeira página' }], total: 15, total_pages: 2 }); };
  a.run("location.hash = '#/favorites'; state.page = 2;");
  await a.run('loadSuppliers()');
  assert.match(requested, /favs=1/);
  assert.match(requested, /page=2/);
  assert.match(a.run('supplierList(true)'), /15 favoritos/);
  assert.match(a.run('supplierList(true)'), /Empresa fora da primeira página/);
});

test('a resposta de busca antiga não substitui a busca mais recente', async () => {
  const a = app();
  const responses = [];
  a.context.fetch = () => new Promise(resolve => responses.push(resolve));
  a.run("location.hash = '#/suppliers'; state.search = 'antiga';");
  const older = a.run('loadSuppliers()');
  a.run("state.search = 'nova';");
  const newer = a.run('loadSuppliers()');
  responses[1](json({ suppliers: [{ id: 'new' }], total: 1, total_pages: 1 }));
  await newer;
  responses[0](json({ suppliers: [{ id: 'old' }], total: 1, total_pages: 1 }));
  await older;
  assert.equal(a.run('S.suppliers[0].id'), 'new');
});

test('falha ao favoritar não modifica o estado salvo', async () => {
  const a = app();
  a.run("S.favs = ['7'];");
  a.context.fetch = async () => { throw new Error('offline'); };
  const button = { dataset: { action: 'favorite', id: '7' }, disabled: false };
  await a.listeners.click({ target: { closest: selector => selector === '[data-action]' ? button : null } });
  assert.equal(a.run("S.favs.includes('7')"), true);
  assert.match(a.elements.get('#toast').textContent, /Não foi possível/);
  assert.equal(button.disabled, false);
});

test('contas a pagar não herdam filtro de realizados da tela de lançamentos', async () => {
  const a = app();
  const requests = [];
  a.context.fetch = async url => { requests.push(url); return json(url.includes('overview') ? { stats: {} } : []); };
  a.run("location.hash = '#/finance/payable'; state.txStatus = 'paid';");
  await a.run('loadFinanceData()');
  const request = requests.find(url => url.includes('/transactions'));
  assert.match(request, /status=pending/);
  assert.match(request, /type=expense/);
});

test('resposta inválida do servidor não aparece como sucesso', async () => {
  const a = app();
  a.context.fetch = async () => ({ ok: true, status: 200, json: async () => { throw new SyntaxError('HTML'); } });
  await assert.rejects(a.run("api.get('/suppliers')"), /resposta válida/);
});

test('produtos refletem nome, status e acessos da API', () => {
  const a = app();
  a.run("masterState.products = [{ name: 'Oferta atual', is_active: 0, active_clients: 12, features: '[\"guide\"]' }]");
  const html = a.run('master.renderProducts()');
  assert.match(html, /Oferta atual/);
  assert.match(html, /Inativo/);
  assert.match(html, /12/);
  assert.doesNotMatch(html, /DRE/);
});

test('anotações não salvas continuam no guia ao navegar', () => {
  const a = app();
  a.run("S.chapters = [{ title: 'Capítulo', body: '', name: 'Capítulo', task: '' }]; noteDrafts[1] = 'Minha ideia ainda não salva';");
  assert.match(a.run('guide()'), /Minha ideia ainda não salva/);
  assert.match(a.run('guide()'), /Rascunho ainda não salvo/);
});

test('falha de carregamento exibe uma opção para tentar novamente', async () => {
  const a = app();
  a.run("S.session = true; S.role = 'admin'; location.hash = '#/master/clients';");
  a.context.fetch = async () => { throw new Error('Falha de conexão'); };
  await a.run('render()');
  assert.match(a.elements.get('#app').innerHTML, /Tentar novamente/);
  assert.doesNotMatch(a.elements.get('#app').innerHTML, /Nenhum cliente encontrado/);
});

test('dois cliques enquanto salva um favorito enviam somente uma requisição', async () => {
  const a = app();
  let finish;
  let count = 0;
  a.context.fetch = () => { count++; return new Promise(resolve => { finish = resolve; }); };
  const button = { dataset: { action: 'favorite', id: '7' }, disabled: false };
  const event = { target: { closest: selector => selector === '[data-action]' ? button : null } };
  const first = a.listeners.click(event);
  await a.listeners.click(event);
  assert.equal(count, 1);
  finish(json({}));
  await first;
  assert.equal(a.run("S.favs.includes('7')"), true);
});

test('uma tela antiga não sobrescreve a navegação mais recente', async () => {
  const a = app();
  let finish;
  a.context.fetch = () => new Promise(resolve => { finish = resolve; });
  a.run("S.session = true; S.role = 'admin'; location.hash = '#/master/clients';");
  const oldPage = a.run('render()');
  a.run("location.hash = '#/home';");
  await a.run('render()');
  finish(json({ clients: [], total: 0, total_pages: 1 }));
  await oldPage;
  assert.match(a.elements.get('#app').innerHTML, /class="upv-dashboard"/);
  assert.doesNotMatch(a.elements.get('#app').innerHTML, /Gestão de Clientes/);
});

test('o dashboard atualiza o progresso após salvar uma tarefa', async () => {
  const a = app();
  a.context.fetch = async () => json({});
  const summary = { textContent: '' };
  const bar = { style: {} };
  a.elements.set('.upv-task-summary span:last-child', summary);
  a.elements.set('.upv-planning-card .progress i', bar);
  a.run("location.hash = '#/home'; S.checks = { 'home-products': true };");
  await a.listeners.change({ target: { dataset: { check: 'home-suppliers' }, checked: true } });
  assert.equal(summary.textContent, '2 de 3 concluídos');
  assert.ok(Math.abs(parseFloat(bar.style.width) - 200 / 3) < .01);
});

test('o checklist volta ao valor anterior quando o salvamento falha', async () => {
  const a = app();
  a.context.fetch = async () => { throw new Error('offline'); };
  const checkbox = { dataset: { check: 'm-0-0' }, checked: true, disabled: false };
  await a.listeners.change({ target: checkbox });
  assert.equal(checkbox.checked, false);
  assert.equal(checkbox.disabled, false);
  assert.equal(a.run("!!S.checks['m-0-0']"), false);
});

test('envio repetido do formulário aguarda a primeira resposta', async () => {
  const a = app();
  a.context.FormData = class {
    constructor(form) { this.values = form.values; }
    [Symbol.iterator]() { return Object.entries(this.values)[Symbol.iterator](); }
  };
  let finish;
  let count = 0;
  a.context.fetch = () => { count++; return new Promise(resolve => { finish = resolve; }); };
  const button = { innerHTML: 'Recuperar', disabled: false };
  const form = {
    dataset: { form: 'recovery' }, values: { email: 'teste@example.com' },
    setAttribute() {}, removeAttribute() {}, querySelector() { return null; },
    querySelectorAll() { return [button]; }
  };
  const event = { preventDefault() {}, target: { closest() { return form; } } };
  const first = a.listeners.submit(event);
  await a.listeners.submit(event);
  assert.equal(count, 1);
  assert.equal(button.disabled, true);
  finish(json({}));
  await first;
  assert.equal(button.disabled, false);
  assert.equal(button.innerHTML, 'Recuperar');
});
