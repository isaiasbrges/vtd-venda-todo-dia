'use strict';

// Cliente de API HTTP centralizado com envio automático de credenciais e CSRF
const api = {
  csrfToken: '',
  async req(endpoint, opts = {}) {
    let url = endpoint.startsWith('/') ? endpoint : '/' + endpoint;
    const apiPath = url.startsWith('/api') ? url : '/api' + url;
    const headers = {
      'Content-Type': 'application/json',
      ...(opts.headers || {})
    };
    if (this.csrfToken) {
      headers['X-CSRF-Token'] = this.csrfToken;
    }
    try {
      const res = await fetch(apiPath, {
        credentials: 'include',
        ...opts,
        headers
      });

      // Trata respostas sem corpo (ex: 204)
      if (res.status === 204) return null;

      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.success === false) {
        if (res.status === 401 && !['login', 'recovery'].includes(route())) {
          S.session = false;
          go('login');
        }
        const err = new Error(data?.error?.message || 'Erro na requisição ao servidor.');
        err.code = data?.error?.code;
        err.status = res.status;
        throw err;
      }
      return data.data;
    } catch (e) {
      console.error('API Error:', e);
      throw e;
    }
  },
  get(ep, params) {
    let q = '';
    if (params) {
      const sp = new URLSearchParams();
      for (const [k, v] of Object.entries(params)) {
        if (v !== '' && v !== null && v !== undefined) sp.append(k, String(v));
      }
      const s = sp.toString();
      if (s) q = (ep.includes('?') ? '&' : '?') + s;
    }
    return this.req(ep + q, { method: 'GET' });
  },
  post(ep, body) {
    return this.req(ep, { method: 'POST', body: JSON.stringify(body || {}) });
  },
  put(ep, body) {
    return this.req(ep, { method: 'PUT', body: JSON.stringify(body || {}) });
  },
  patch(ep, body) {
    return this.req(ep, { method: 'PATCH', body: JSON.stringify(body || {}) });
  },
  delete(ep) {
    return this.req(ep, { method: 'DELETE' });
  }
};

const icons = {
  home: 'M3 10 12 3l9 7v11h-6v-7H9v7H3z',
  truck: 'M3 6h11v12H3z M14 10h4l3 4v4h-7 M7 18a2 2 0 1 0 .01 0 M17 18a2 2 0 1 0 .01 0',
  calendar: 'M4 5h16v16H4z M4 10h16 M8 3v4 M16 3v4',
  book: 'M3 4c4-1 7 0 9 2 2-2 5-3 9-2v16c-4-1-7 0-9 2-2-2-5-3-9-2z M12 6v16',
  star: 'm12 3 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z',
  wallet: 'M3 6h18v15H3z M3 6l14-3v3 M16 12h5v5h-5z',
  user: 'M5 21v-3a7 7 0 0 1 14 0v3z M12 3a4 4 0 1 0 .01 0',
  logout: 'M10 3H4v18h6 M9 12h13 M17 7l5 5-5 5',
  arrow: 'M5 12h14 M13 6l6 6-6 6',
  search: 'M10 3a7 7 0 1 0 .01 0 M15 15l6 6',
  plus: 'M12 4v16 M4 12h16',
  check: 'm5 12 5 5L20 7',
  edit: 'm4 16 12-12 4 4L8 20H4z M13 7l4 4',
  trash: 'M3 6h18 M8 6V3h8v3 M5 6l1 15h12l1-15 M10 10v7 M14 10v7',
  download: 'M12 3v12 M7 10l5 5 5-5 M3 16v5h18v-5',
  lock: 'M6 10h12v11H6z M8 10V7a4 4 0 0 1 8 0v3',
  settings: 'M12 8a4 4 0 1 0 .01 0 M12 2v3 M12 19v3 M2 12h3 M19 12h3 M5 5l2 2 M17 17l2 2 M5 19l2-2 M17 7l2-2',
  menu: 'M3 6h18 M3 12h18 M3 18h18',
  help: 'M12 3a9 9 0 1 0 .01 0 M9 9a3 3 0 0 1 6 0c0 2-3 2-3 5 M12 17h.01',
  chart: 'M4 21V11h4v10 M10 21V3h4v18 M16 21V8h4v13',
  up: 'M12 21V3 M5 10l7-7 7 7',
  down: 'M12 3v18 M5 14l7 7 7-7',
  mail: 'M3 5h18v14H3z m0 0 9 8 9-8',
  external: 'M14 3h7v7 M21 3 11 13 M10 3H3v18h18v-7',
  shield: 'm12 3 9 4v5c0 5-9 10-9 10S3 17 3 12V7z m-4 9 3 3 5-6'
};

const icon = n => `<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="${icons[n] || icons.arrow}"/></svg>`;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const money = n => (n / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const today = () => new Date().toLocaleDateString('en-CA');
const day = s => s ? s.split('-').reverse().join('/') : '—';
const safeURL = u => {
  try {
    const v = new URL(u);
    return ['https:', 'http:'].includes(v.protocol) ? v.href : '#';
  } catch {
    return '#';
  }
};

// Estado reativo em memória sincronizado com a API
let S = {
  session: false,
  role: 'user',
  plan: 'plus',
  name: '',
  store: '',
  email: '',
  initialBalance: 0,
  favs: [],
  checks: {},
  read: [],
  notes: {},
  suppliers: [],
  chapters: [],
  months: [],
  categories: [],
  clients: [],
  transactions: [],
  financeStats: { start: 0, inc: 0, out: 0, balance: 0, projected: 0 },
  chartWeekly: { income: [0,0,0,0,0], expense: [0,0,0,0,0] },
  chartCategories: []
};

let state = {
  search: '',
  segment: '',
  wa: false,
  cat: false,
  page: 1,
  totalPages: 1,
  totalSuppliers: 0,
  month: new Date().getMonth(),
  chapter: 0,
  financeMonth: today().slice(0, 7),
  txSearch: '',
  txStatus: '',
  txType: '',
  clientSearch: '',
  adminSearch: ''
};

const $ = s => document.querySelector(s);
let toastTimer;
function toast(s) {
  const el = $('#toast');
  if (!el) return;
  el.textContent = s;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3500);
}

const route = () => location.hash.replace(/^#\/?/, '').split('?')[0] || 'login';
function go(r) {
  if (route() === r) render();
  else location.hash = '/' + r;
}

function btn(text, action, cls = '', data = '') {
  return `<button type="button" class="btn ${cls}" data-action="${action}" ${data}>${text}</button>`;
}
function link(text, r, cls = 'btn') {
  return `<a class="${cls}" href="#/${r}">${text}</a>`;
}
function logo() {
  return `<a class="logo" href="#/home"><span class="logo-mark">VTD</span><span><strong>Venda Todo Dia</strong><small>O kit do Matheus</small></span></a>`;
}
function field(label, name, value = '', type = 'text', required = true, placeholder = '') {
  return `<div class="field"><label for="f-${name}">${label}</label><input id="f-${name}" name="${name}" type="${type}" value="${esc(value)}" placeholder="${esc(placeholder)}" ${required ? 'required' : ''}></div>`;
}
function select(label, name, options, value) {
  return `<div class="field"><label for="f-${name}">${label}</label><select id="f-${name}" name="${name}">${options.map(([v, l]) => `<option value="${esc(v)}" ${String(v) === String(value) ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></div>`;
}
function modal(title, body, submit = '', form = '') {
  const el = $('#modal');
  if (el.open) el.close();
  el.innerHTML = `<div class="dialog-head"><h2>${title}</h2><button class="close" data-action="close" aria-label="Fechar janela">×</button></div>${form ? `<form data-form="${form}">` : ''}${body}<div class="modal-actions">${btn('Fechar', 'close')}${submit ? `<button class="btn primary" type="submit">${submit}</button>` : ''}</div>${form ? '</form>' : ''}`;
  el.showModal();
}
function head(title, description, actions = '') {
  return `<div class="page-head"><div><h1>${title}</h1><p>${description}</p></div>${actions ? `<div class="actions">${actions}</div>` : ''}</div>`;
}

const nav = [
  ['home', 'Início', 'home'],
  ['suppliers', 'Fornecedores', 'truck'],
  ['calendar', 'Calendário comercial', 'calendar'],
  ['guide', 'Guia do Matheus', 'book'],
  ['favorites', 'Meus favoritos', 'star'],
  ['finance', 'Controle financeiro', 'wallet']
];

function shell(body, current) {
  const section = current.split('/')[0];
  const initials = (S.name || 'VTD').split(' ').map(x => x[0]).slice(0, 2).join('');
  return `<aside class="sidebar" id="sidebar">
    ${logo()}
    <nav aria-label="Navegação principal">
      ${nav.map(([r, l, i]) => `<a href="#/${r}" class="${section === r ? 'active' : ''}">${icon(i)}${l}${r === 'finance' && S.plan === 'essential' ? icon('lock') : ''}</a>`).join('')}
      ${S.role === 'admin' ? `<a href="#/admin" class="${section === 'admin' ? 'active' : ''}">${icon('shield')}Administração</a>` : ''}
    </nav>
    <div class="side-bottom">
      <div class="side-note"><b>${S.plan === 'plus' ? 'Kit + Financeiro' : 'Kit Essencial'}</b>${esc(S.store || 'Minha Loja')}</div>
      <a href="#/account">${icon('user')}Minha conta</a>
      <a href="#/help">${icon('help')}Ajuda</a>
      <a href="vendas.html">${icon('external')}Página de vendas</a>
      <button data-action="logout">${icon('logout')}Sair da conta</button>
    </div>
  </aside>
  <div class="shell">
    <header class="topbar">
      <div class="header-mobile-logo">
        <button class="mobile-menu" data-action="menu" aria-label="Abrir menu">${icon('menu')}</button>
        <span class="logo-mark">VTD</span>
      </div>
      <div class="crumb">
        ${icon('home')}
        <span>Área do lojista</span>
        <span class="muted">/</span>
        <span>${section === 'admin' ? 'Administração' : nav.find(n => n[0] === section)?.[1] || ({ account: 'Minha conta', help: 'Ajuda' }[section]) || 'VTD'}</span>
      </div>
      <div class="top-right">
        <a href="#/account" class="avatar" aria-label="Minha conta">${esc(initials)}</a>
      </div>
    </header>
    <main class="content" id="main">${body}</main>
  </div>
  <nav class="mobile-bottom" aria-label="Navegação mobile">
    ${nav.filter(n => n[0] !== 'favorites').map(([r, l, i]) => `<a href="#/${r}" class="${section === r ? 'active' : ''}">${icon(i)}${({ suppliers: 'Fornecedores', calendar: 'Calendário', guide: 'Guia', finance: 'Financeiro', home: 'Início' })[r]}</a>`).join('')}
  </nav>`;
}

function login(recovery = false) {
  const urlParams = new URLSearchParams(location.hash.split('?')[1] || '');
  const resetToken = urlParams.get('token');

  return `<div class="login">
    <section class="login-visual">
      ${logo()}
      <h1>Sua próxima venda começa aqui.</h1>
      <p>Fornecedores, calendário comercial e orientação prática para sua loja.</p>
    </section>
    <section class="login-form">
      <div class="eyebrow">Acesso à Plataforma</div>
      <h2 style="margin-top:12px">${recovery ? (resetToken ? 'Nova senha' : 'Recuperar acesso') : 'Acesse sua conta'}</h2>
      <p>${recovery ? (resetToken ? 'Crie uma nova senha para acessar sua conta.' : 'Informe seu e-mail para receber as instruções de recuperação.') : 'Entre com seu e-mail e senha para acessar os materiais e sua loja.'}</p>
      ${recovery ? `
        <form data-form="${resetToken ? 'reset-password' : 'recovery'}">
          ${resetToken ? `
            <input type="hidden" name="token" value="${esc(resetToken)}">
            ${field('Nova Senha (mínimo 6 caracteres)', 'new_password', '', 'password', true, 'Digite sua nova senha')}
            <button class="btn primary" type="submit">Salvar Nova Senha ${icon('arrow')}</button>
          ` : `
            ${field('Seu E-mail cadastrado', 'email', '', 'email', true, 'exemplo@loja.com')}
            <button class="btn primary" type="submit">Enviar link de recuperação ${icon('arrow')}</button>
          `}
        </form>
        <div style="margin-top:20px">${link('← Voltar para o login', 'login', 'link')}</div>
      ` : `
        <form data-form="login">
          ${field('E-mail', 'email', '', 'email', true, 'seu@email.com')}
          ${field('Senha', 'password', '', 'password', true, 'Sua senha de acesso')}
          <button class="btn primary" type="submit" style="width:100%;margin-top:12px">Entrar na plataforma ${icon('arrow')}</button>
        </form>
        <p style="text-align:center;margin:20px 0">${link('Esqueci minha senha', 'recovery', 'link')}</p>
        <a class="link" href="vendas.html">← Ver página de vendas</a>
      `}
    </section>
  </div>`;
}

function check(key, text) {
  return `<label class="check"><input type="checkbox" data-check="${esc(key)}" ${S.checks[key] ? 'checked' : ''}><span>${text}</span></label>`;
}

function home() {
  const m = S.months[state.month] || { n: 'Mês atual', dates: [['Campanha comercial', '']], items: [] };
  return head('Tudo para sua próxima venda.', 'Escolha bons produtos, encontre fornecedores e prepare sua loja.') +
    `<section class="welcome"><div><div class="eyebrow">Comece por aqui</div><h2>Sua loja preparada<br>para vender.</h2><p>Conheça o kit e coloque as ideias em prática.</p>${link('Explorar o guia ' + icon('arrow'), 'guide', 'btn primary')}</div></section>
    <div class="columns">
      <section class="panel">
        <div class="panel-head"><h2>Explore seu kit</h2><span class="tag">Seu acesso</span></div>
        ${[['suppliers', 'truck', 'Fornecedores do Matheus', `${S.suppliers.length || 234} contatos organizados por categoria`],
           ['calendar', 'calendar', 'Calendário comercial', 'O que comprar e quando se preparar'],
           ['guide', 'book', 'Guia Loja que Vende Todo Dia', `${S.chapters.length || 8} capítulos com tarefas para sua loja`]].map(([r, i, t, d]) => `<a class="kit-row" href="#/${r}"><span class="kit-icon">${icon(i)}</span><div><h3>${t}</h3><p>${d}</p></div>${icon('arrow')}</a>`).join('')}
      </section>
      <section class="panel campaign">
        <div class="eyebrow">${esc(m.n)}</div>
        <h2>Prepare a próxima campanha.</h2>
        <p>${esc(m.dates?.[0]?.[0] || 'Planejamento')} · Antecipe as compras e a divulgação.</p>
        ${check('home-products', 'Escolher os produtos')}
        ${check('home-suppliers', 'Consultar fornecedores')}
        ${check('home-publish', 'Preparar a divulgação')}
        ${link('Abrir calendário ' + icon('arrow'), 'calendar', 'btn primary')}
      </section>
    </div>
    <a class="next-step" href="#/guide"><span class="kit-icon">${icon('book')}</span><div><div class="muted small">Seu próximo passo</div><b>Anote 3 produtos que seus clientes pediram e você ainda não tem.</b></div></a>`;
}

function supplierList(favs = false) {
  const list = favs ? S.suppliers.filter(r => S.favs.includes(r.id)) : S.suppliers;
  const count = favs ? list.length : state.totalSuppliers;
  const pages = favs ? Math.max(1, Math.ceil(list.length / 12)) : state.totalPages;

  return `<div class="list-count">
    <span>${count} ${favs ? 'favoritos' : 'fornecedores encontrados'}</span>
    <span>Página ${state.page} de ${pages}</span>
  </div>
  ${list.length ? list.map(r => `<article class="supplier">
    <span class="supplier-logo">${esc(r.nome.slice(0, 2))}</span>
    <div class="supplier-info">
      <h3>${esc(r.nome)}</h3>
      <p>${esc(r.produtos)}</p>
      <div class="supplier-meta">
        <span class="tag">${esc(r.seg)}</span>
        ${r.wa?.length ? '<span class="tag green">WhatsApp disponível</span>' : ''}
        ${r.cat?.length ? '<span class="tag yellow">Com catálogo</span>' : ''}
      </div>
    </div>
    <div class="supplier-side">
      <button class="star ${S.favs.includes(r.id) ? 'saved' : ''}" data-action="favorite" data-id="${r.id}" aria-pressed="${S.favs.includes(r.id)}" aria-label="Favoritar ${esc(r.nome)}">
        ${S.favs.includes(r.id) ? '★' : '☆'}
      </button>
      ${btn('Ver contatos', 'supplier-detail', 'small', `data-id="${r.id}"`)}
    </div>
  </article>`).join('') : `<div class="empty">
    ${icon(favs ? 'star' : 'search')}
    <h3>${favs ? 'Nenhum favorito por aqui ainda.' : 'Nenhum fornecedor encontrado.'}</h3>
    <p>${favs ? 'Marque a estrela de um fornecedor para encontrá-lo aqui.' : 'Tente outra busca ou limpe os filtros.'}</p>
    ${btn(favs ? 'Explorar fornecedores' : 'Limpar filtros', favs ? 'go-suppliers' : 'clear-filters', 'primary')}
  </div>`}
  <div class="pagination">
    ${btn('← Anterior', 'page', 'small', `data-page="${state.page - 1}" ${state.page <= 1 ? 'disabled' : ''}`)}
    <span class="small">${state.page} / ${pages}</span>
    ${btn('Próxima →', 'page', 'small', `data-page="${state.page + 1}" ${state.page >= pages ? 'disabled' : ''}`)}
  </div>`;
}

function suppliers(favs = false) {
  const segs = ['Brinquedos', 'Educativos', 'Papelaria/Escolar', 'Puericultura/Bebê', 'Jogos', 'Pelúcias', 'Esporte/Ar livre', 'Livros', 'Bonecas/Bonecos', 'Veículos/Carrinhos', 'Fantasias', 'Outros'];
  return head(favs ? 'Seus fornecedores, por perto.' : 'Fornecedores do Matheus', favs ? 'Reúna os contatos que você quer consultar novamente.' : 'Base completa de contatos para encontrar os produtos da sua loja.') +
    `<div class="searchbar">
      <div class="search">
        ${icon('search')}
        <input id="supplier-search" aria-label="Buscar fornecedores" placeholder="Busque por fornecedor, produto ou marca" value="${esc(state.search)}">
      </div>
      <select id="supplier-segment" aria-label="Segmento">
        <option value="">Todos os segmentos</option>
        ${segs.map(s => `<option ${state.segment === s ? 'selected' : ''}>${esc(s)}</option>`).join('')}
      </select>
    </div>
    <div class="filters">
      <button class="chip ${state.wa ? 'active' : ''}" data-action="filter-wa" aria-pressed="${state.wa}">Com WhatsApp</button>
      <button class="chip ${state.cat ? 'active' : ''}" data-action="filter-cat" aria-pressed="${state.cat}">Com catálogo</button>
      ${btn('Limpar filtros', 'clear-filters', 'small ghost')}
    </div>
    <div id="supplier-results">${supplierList(favs)}</div>
    <p class="storage-note">Contatos comerciais importados do material original. Confirme autenticidade, pedido mínimo, frete e condições diretamente com cada fornecedor.</p>`;
}

function supplierDetail(id) {
  const r = S.suppliers.find(x => x.id === id);
  if (!r) return;
  const channels = [
    ['Site', r.site],
    ['Instagram', r.ig],
    ['WhatsApp', r.wa?.[0] ? 'https://wa.me/55' + r.wa[0].replace(/\D/g, '') : ''],
    ...((r.cat || []).map((u, i) => ['Catálogo ' + (i + 1), u]))
  ].filter(x => x[1]);

  modal(esc(r.nome), `
    <span class="tag yellow">${esc(r.seg)}</span>
    <p>${esc(r.produtos)}</p>
    <div class="contact-links">
      ${channels.map(([l, u]) => `<a class="btn small" href="${esc(safeURL(u))}" target="_blank" rel="noopener noreferrer">${esc(l)} ${icon('external')}</a>`).join('')}
    </div>
    ${[['WhatsApp', (r.wa || []).join(' · ')], ['Telefone', (r.tel || []).join(' · ')], ['E-mail', r.email]].filter(x => x[1]).map(([k, v]) => `<div class="detail-row"><span>${k}</span><strong>${esc(v)}</strong></div>`).join('')}
    ${r.nota ? `<div class="note">${esc(r.nota)}</div>` : ''}
    <p class="muted small">Confirme diretamente as condições antes de fazer qualquer pagamento.</p>
  `);
}

function calendar() {
  const m = S.months[state.month] || { n: '', s: '', lead: '', buy: '', tip: '', dates: [], items: [] };
  const done = (m.items || []).filter((_, i) => S.checks[`m-${state.month}-${i}`]).length;
  return head('Calendário comercial', 'Prepare sua loja antes das próximas datas.') +
    `<div class="month-tabs" aria-label="Selecionar mês">
      ${S.months.map((x, i) => `<button data-action="month" data-index="${i}" class="${state.month === i ? 'active' : ''}" aria-pressed="${state.month === i}">${esc(x.s)}</button>`).join('')}
    </div>
    <div class="columns">
      <section class="panel">
        <div class="eyebrow muted">Planeje com antecedência</div>
        <h2 class="month-title" style="margin-top:13px">${esc(m.n)}</h2>
        <div class="dates">${(m.dates || []).map(d => `<div class="date ${d[2] ? 'key' : ''}"><b>${esc(d[0])}</b><small>${esc(d[1])}</small></div>`).join('')}</div>
        <p class="muted">${esc(m.lead)}</p>
        <div class="note"><b>Antecipe suas compras</b><br>${esc(m.buy)}<br><small>Ajuste quantidades ao seu caixa, espaço e demanda.</small></div>
        <h3>Uma ideia para colocar em prática</h3>
        <p class="small muted">${esc(m.tip)}</p>
        <div class="reader-nav">
          ${btn('← Mês anterior', 'month', 'small', `data-index="${(state.month + 11) % 12}"`)}
          ${btn('Próximo mês →', 'month', 'small', `data-index="${(state.month + 1) % 12}"`)}
        </div>
      </section>
      <section class="panel campaign">
        <h2 style="font-size:23px;margin-top:0">Meu checklist do mês</h2>
        <p>Selecione o que faz sentido para sua loja.</p>
        ${(m.items || []).map(([text, kind], i) => check(`m-${state.month}-${i}`, `${esc(text)} <small class="tag">${({ f: 'Febre', e: 'Estação', c: 'Coringa' })[kind] || 'Coringa'}</small>`)).join('')}
        <div style="margin:20px 0">
          <span id="month-progress" class="small">${done} de ${(m.items || []).length} itens concluídos</span>
          <div class="progress"><i id="month-bar" style="width:${(m.items?.length ? (done / m.items.length * 100) : 0)}%"></i></div>
        </div>
        ${link('Buscar fornecedores ' + icon('search'), 'suppliers', 'btn primary')}
      </section>
    </div>`;
}

function guide() {
  state.chapter = Math.min(state.chapter, Math.max(0, S.chapters.length - 1));
  const ch = S.chapters[state.chapter] || { title: '', body: '', task: '', name: '' };
  return head('Guia do Matheus', 'Leia no seu ritmo e transforme cada capítulo em uma ação.') +
    `<div class="reading">
      <aside class="panel chapters">
        <h3>Loja que Vende Todo Dia</h3>
        <p class="small muted">Por Matheus · ${S.read.length} de 8 concluídos</p>
        <div class="progress"><i style="width:${(S.read.length / 8 * 100)}%"></i></div>
        <div class="chapters-list">
          ${S.chapters.map((c, i) => `<a href="#/guide/${i + 1}" class="chapter-link ${i === state.chapter ? 'active' : ''}"><span>${S.read.includes(i + 1) ? '✓' : String(i + 1).padStart(2, '0')}</span>${esc(c.name)}</a>`).join('')}
        </div>
      </aside>
      <article class="panel reader">
        <div class="eyebrow muted">Capítulo ${String(state.chapter + 1).padStart(2, '0')}</div>
        <h1>${esc(ch.title)}</h1>
        <div class="reader-body">${ch.body}</div>
        <div class="task-box">
          <h3>Coloque em prática</h3>
          <p class="small">${esc(ch.task)}</p>
          <label class="check">
            <input type="checkbox" id="chapter-done" ${S.read.includes(state.chapter + 1) ? 'checked' : ''}>
            <span>Concluí esta tarefa</span>
          </label>
          <label class="field">
            <span>Minhas anotações</span>
            <textarea id="chapter-note" placeholder="Registre suas ideias para aplicar na loja.">${esc(S.notes[state.chapter + 1] || '')}</textarea>
          </label>
          ${btn('Salvar anotação', 'save-note', 'small', `style="margin-top:10px"`)}
        </div>
        <div class="reader-nav">
          ${btn('← Anterior', 'chapter', '', `data-index="${state.chapter - 1}" ${!state.chapter ? 'disabled' : ''}`)}
          ${btn(state.chapter === 7 ? 'Voltar ao início' : 'Próximo capítulo →', 'chapter', 'primary', `data-index="${state.chapter === 7 ? 0 : state.chapter + 1}"`)}
        </div>
      </article>
    </div>`;
}

function financeNav(current) {
  return `<nav class="financial-nav" aria-label="Seções financeiras">
    ${[['finance', 'Visão geral'], ['finance/transactions', 'Lançamentos'], ['finance/payable', 'A pagar'], ['finance/receivable', 'A receber'], ['finance/categories', 'Categorias'], ['finance/reports', 'Relatórios']].map(([r, t]) => link(t, r, ' ' + (current === r ? 'active' : ''))).join('')}
  </nav>`;
}

function stats() {
  const t = S.financeStats;
  return `<div class="stats">
    ${[['Saldo inicial', t.start, 'wallet'], ['Entradas recebidas', t.inc, 'up'], ['Saídas pagas', t.out, 'down'], ['Saldo atual', t.balance, 'chart']].map(([l, v, i]) => `<div class="stat">${icon(i)}<small>${l}</small><strong>${money(v)}</strong></div>`).join('')}
  </div>`;
}

function financeHeader(title, desc) {
  return head(title, desc, `<input type="month" id="finance-month" value="${state.financeMonth}" aria-label="Mês financeiro" style="width:165px">${btn(icon('plus') + ' Novo lançamento', 'new-tx', 'primary')}`);
}

function chart() {
  const inc = S.chartWeekly?.income || [0, 0, 0, 0, 0];
  const out = S.chartWeekly?.expense || [0, 0, 0, 0, 0];
  const max = Math.max(...inc, ...out, 100);
  const pts = a => a.map((v, i) => `${50 + i * 110},${170 - v / max * 130}`).join(' ');

  return `<svg class="chart" viewBox="0 0 530 210" role="img" aria-label="Entradas e saídas pagas por semana do mês">
    <g stroke="#e9e9e2">${[40, 83, 126, 170].map(y => `<path d="M40 ${y}H505"/>`).join('')}</g>
    <g font-size="10" fill="#888">${[0, 1, 2, 3, 4].map(i => `<text x="${38 + i * 110}" y="198">${i * 7 + 1}–${Math.min(i * 7 + 7, 31)}</text>`).join('')}</g>
    <polyline points="${pts(inc)}" fill="none" stroke="#ebc500" stroke-width="3"/>
    <polyline points="${pts(out)}" fill="none" stroke="#222" stroke-width="2"/>
    ${inc.map((v, i) => `<circle cx="${50 + i * 110}" cy="${170 - v / max * 130}" r="4" fill="#ebc500"><title>Semana ${i + 1}: entradas ${money(v)}</title></circle>`).join('')}
  </svg>
  <div class="chart-legend"><span><i></i>Entradas recebidas</span><span><i style="background:#222"></i>Saídas pagas</span></div>`;
}

function categoriesChart() {
  const cats = S.chartCategories || [];
  const total = cats.reduce((s, x) => s + x.value_cents, 0);

  return cats.length ? cats.map(c => `
    <div class="category-bar">
      <div><span>${esc(c.name)}</span><b>${money(c.value_cents)}</b></div>
      <div class="bar"><i style="width:${total ? c.value_cents / total * 100 : 0}%"></i></div>
    </div>`).join('') : `<p class="muted small">Não há despesas pagas neste mês.</p>`;
}

function txTable(list) {
  return list.length ? `<div class="table-wrap"><table>
    <thead><tr><th>Descrição</th><th>Categoria</th><th>Vencimento / data</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead>
    <tbody>
      ${list.map(t => `<tr>
        <td>${esc(t.description)}</td>
        <td><span class="tag">${esc(t.category)}</span></td>
        <td>${day(t.date)}</td>
        <td class="${t.type === 'income' ? 'positive' : 'negative'}">${t.type === 'income' ? '+' : '−'} ${money(t.value)}</td>
        <td><span class="tag ${t.status === 'paid' ? 'green' : 'yellow'}">${t.status === 'paid' ? (t.type === 'income' ? 'Recebido' : 'Pago') : 'Pendente'}</span></td>
        <td>
          <div class="table-actions">
            ${t.status === 'pending' ? btn(icon('check'), 'settle-tx', 'small', `data-id="${t.id}" aria-label="Marcar como ${t.type === 'income' ? 'recebido' : 'pago'}"`) : ''}
            ${btn(icon('edit'), 'edit-tx', 'small', `data-id="${t.id}" aria-label="Editar lançamento"`)}
            ${btn(icon('trash'), 'delete-tx', 'small danger', `data-id="${t.id}" aria-label="Excluir lançamento"`)}
          </div>
        </td>
      </tr>`).join('')}
    </tbody>
  </table></div>` : `<div class="empty">${icon('wallet')}<h3>Nenhum lançamento encontrado.</h3><p>Adicione um lançamento ou selecione outro período.</p>${btn('Novo lançamento', 'new-tx', 'primary')}</div>`;
}

function finance(current) {
  if (S.plan !== 'plus') {
    return head('Controle financeiro', 'Acompanhe as finanças, entradas e saídas da sua loja.') +
      `<section class="panel locked">
        <span class="kit-icon">${icon('lock')}</span>
        <h2>Este módulo faz parte do Kit + Financeiro.</h2>
        <p>Sua conta está no plano Kit Essencial. Faça o upgrade na Greenn para liberar o controle completo de lançamentos, quitações e relatórios.</p>
        <a class="btn primary" href="vendas.html" target="_blank" rel="noopener">Conhecer o Kit + Financeiro</a>
      </section>`;
  }

  const suffix = current.split('/')[1] || '';
  const title = { transactions: 'Lançamentos', payable: 'Contas a pagar', receivable: 'Contas a receber', categories: 'Categorias', reports: 'Relatórios' }[suffix] || 'Seu dinheiro, com clareza.';
  let html = financeHeader(title, 'Acompanhe os valores realizados e os compromissos previstos da sua loja.') + financeNav(current);

  if (suffix === 'categories') {
    html += `<section class="panel">
      <div class="panel-head"><h2>Organize seus lançamentos</h2>${btn(icon('plus') + ' Nova categoria', 'new-category', 'primary')}</div>
      ${S.categories.map(c => `<div class="detail-row"><strong>${esc(c.name)}</strong><span>${c.tx_count || 0} lançamentos ${btn(icon('trash'), 'delete-category', 'small danger', `data-id="${c.id}" aria-label="Excluir categoria"`)}</span></div>`).join('')}
      <p class="storage-note">Categorias com lançamentos vinculados não podem ser excluídas.</p>
    </section>`;
    return html;
  }

  html += stats();

  if (['transactions', 'payable', 'receivable'].includes(suffix)) {
    let list = S.transactions.filter(t =>
      (suffix !== 'payable' || (t.type === 'expense' && t.status === 'pending')) &&
      (suffix !== 'receivable' || (t.type === 'income' && t.status === 'pending')) &&
      (!state.txSearch || t.description.toLowerCase().includes(state.txSearch.toLowerCase())) &&
      (!state.txStatus || t.status === state.txStatus) &&
      (!state.txType || t.type === state.txType)
    );

    html += `<section class="panel">
      <div class="searchbar">
        <div class="search">
          ${icon('search')}
          <input id="tx-search" value="${esc(state.txSearch)}" placeholder="Buscar lançamento" aria-label="Buscar lançamento">
        </div>
        ${suffix === 'transactions' ? `
          <select id="tx-status" aria-label="Status">
            <option value="">Todos os status</option>
            <option value="paid" ${state.txStatus === 'paid' ? 'selected' : ''}>Realizados</option>
            <option value="pending" ${state.txStatus === 'pending' ? 'selected' : ''}>Pendentes</option>
          </select>` : ''}
        ${btn(icon('download') + ' CSV', 'export-csv', 'small')}
      </div>
      <div id="tx-results">${txTable(list)}</div>
    </section>`;
    return html;
  }

  html += `<div class="finance-grid">
    <section class="panel"><div class="panel-head"><h2>Fluxo de caixa</h2><span class="tag yellow">Semanal</span></div>${chart()}</section>
    <section class="panel"><div class="panel-head"><h2>Despesas por categoria</h2></div>${categoriesChart()}</section>
  </div>`;

  html += `<section class="panel" style="margin-bottom:22px">
    <div class="panel-head">
      <h3>Saldo projetado para o fim do mês</h3>
      <strong>${money(S.financeStats.projected)}</strong>
    </div>
    <p class="small muted">Saldo atual + entradas pendentes − saídas pendentes do mês selecionado. Valores pendentes não alteram o saldo realizado.</p>
  </section>`;

  if (suffix === 'reports') {
    html += `<section class="panel">
      <div class="panel-head"><h2>Exportar dados do período</h2>${btn(icon('download') + ' Baixar CSV', 'export-csv', 'primary')}</div>
      <p class="small muted">Exporte os lançamentos do mês selecionado para abrir em uma planilha do Excel.</p>
    </section>`;
  } else {
    html += `<section class="panel">
      <div class="panel-head"><h2>Últimos lançamentos</h2>${link('Ver todos →', 'finance/transactions', 'link')}</div>
      ${txTable(S.transactions.slice(0, 5))}
    </section>`;
  }

  return html;
}

function transactionModal(id) {
  const t = S.transactions.find(x => x.id === id) || { type: 'expense', value: 0, category_id: S.categories[0]?.id || 1, date: state.financeMonth + '-24', status: 'pending' };
  modal(id ? 'Editar lançamento' : 'Novo lançamento', `
    <input type="hidden" name="id" value="${esc(id || '')}">
    <div class="fields">
      ${field('Descrição', 'description', t.description || '')}
      ${field('Valor em R$', 'value', t.value ? (t.value / 100).toFixed(2) : '', 'number')}
      ${select('Tipo', 'type', [['income', 'Entrada'], ['expense', 'Saída']], t.type)}
      ${select('Categoria', 'category_id', S.categories.map(c => [c.id, c.name]), t.category_id)}
      ${field('Data do pagamento / vencimento', 'date', t.date, 'date')}
      ${select('Status', 'status', [['pending', 'Pendente'], ['paid', 'Realizado (pago / recebido)']], t.status)}
    </div>
    <p class="storage-note">Data representa o vencimento enquanto pendente e a data de pagamento quando realizado.</p>
  `, 'Salvar lançamento', 'transaction');
  $('#f-value').min = '0.01';
  $('#f-value').step = '0.01';
}

function account() {
  return head('Minha conta', 'Gerencie as preferências da sua loja e seus dados de acesso.') +
    `<div class="settings">
      <form class="panel" data-form="profile">
        <div class="panel-head"><h2>Seu perfil</h2></div>
        <div class="fields">
          ${field('Nome de exibição', 'name', S.name)}
          ${field('Nome da loja', 'store', S.store)}
          ${field('E-mail de acesso', 'email', S.email, 'email')}
          ${field('Saldo inicial da loja (R$)', 'initialBalance', (S.initialBalance / 100).toFixed(2), 'number')}
        </div>
        <p class="storage-note">O saldo inicial é anterior aos lançamentos do sistema e compõe o saldo de abertura.</p>
        <button class="btn primary" style="margin-top:20px" type="submit">Salvar preferências</button>
      </form>
      <section class="panel">
        <h2>Seu plano ativo</h2>
        <div class="account-options">
          <div class="option active">
            <h3>${S.plan === 'plus' ? 'Kit + Financeiro' : 'Kit Essencial'}</h3>
            <p>${S.plan === 'plus' ? 'Acesso completo ao kit de fornecedores, guia, calendário e módulo financeiro.' : 'Acesso completo aos fornecedores, guia e calendário comercial.'}</p>
          </div>
        </div>
      </section>
    </div>`;
}

function help() {
  return head('Como podemos ajudar?', 'Conheça os caminhos da plataforma.') +
    `<section class="panel help-list">
      ${[['Como encontro um fornecedor?', 'Abra Fornecedores, pesquise por produto ou empresa e use os filtros de segmento, WhatsApp ou catálogo. Em Ver contatos você encontra os canais disponíveis.'],
         ['Como salvo um favorito?', 'Clique na estrela ao lado do fornecedor. Ele aparecerá em Meus favoritos.'],
         ['Como uso o calendário?', 'Escolha o mês, consulte as sugestões e marque os itens do checklist que fazem sentido para sua loja.'],
         ['O que significa o saldo projetado?', 'É o saldo realizado somado às entradas pendentes e descontadas as saídas pendentes do mês.'],
         ['Como funciona a quitação de contas?', 'Ao pagar ou receber uma conta pendente, clique no botão de quitação e informe a data efetiva para que o valor passe a compor o realizado do mês correspondente.'],
         ['Como faço o backup dos dados?', 'Na aba Relatórios do Financeiro você pode exportar todos os lançamentos para uma planilha CSV.']].map(([q, a]) => `<details><summary>${q}</summary><p>${a}</p></details>`).join('')}
    </section>`;
}

function admin(current) {
  const tab = current.split('/')[1] || 'overview';
  let html = head('Administração VTD', 'Gerencie clientes, acessos e os conteúdos da plataforma.', `<span class="tag green">Admin Seguro</span>`) +
    `<nav class="admin-nav">${[['overview', 'Visão geral'], ['clients', 'Clientes e acessos'], ['suppliers', 'Fornecedores'], ['content', 'Guia'], ['calendar', 'Calendário']].map(([r, t]) => link(t, 'admin/' + r, r === tab ? 'active' : '')).join('')}</nav>`;

  if (tab === 'overview') {
    return html + `<div class="stats">
      ${[['Fornecedores', S.suppliers.length || 234], ['Clientes cadastrados', S.clients.length], ['Capítulos', S.chapters.length || 8], ['Meses', S.months.length || 12]].map(([l, v]) => `<div class="stat"><small>${l}</small><strong>${v}</strong></div>`).join('')}
    </div>
    <div class="columns">
      <section class="panel">
        <h2>Conteúdo do produto</h2>
        ${[['suppliers', 'truck', 'Base de fornecedores', 'Cadastre ou edite contatos comerciais.'],
           ['content', 'book', 'Guia do Matheus', 'Revise o conteúdo dos oito capítulos.'],
           ['calendar', 'calendar', 'Calendário comercial', 'Atualize as sugestões de cada mês.']].map(([r, i, t, d]) => `<a class="kit-row" href="#/admin/${r}"><span class="kit-icon">${icon(i)}</span><div><h3>${t}</h3><p>${d}</p></div>${icon('arrow')}</a>`).join('')}
      </section>
      <section class="panel campaign">
        <h2>Clientes & Lojas</h2>
        <p>Cadastre novos clientes com acesso direto ao dashboard financeiro ou altere permissões.</p>
        ${link('Gerenciar acessos →', 'admin/clients', 'btn primary')}
      </section>
    </div>`;
  }

  if (tab === 'clients') {
    return html + `<section class="panel">
      <div class="panel-head"><h2>Clientes cadastrados</h2>${btn('Adicionar cliente', 'new-client', 'primary')}</div>
      <div class="table-wrap"><table>
        <thead><tr><th>Nome / E-mail</th><th>Loja</th><th>Oferta</th><th>Acesso</th><th>Ações</th></tr></thead>
        <tbody>
          ${S.clients.map(c => `<tr>
            <td><b>${esc(c.name)}</b><br><span class="muted">${esc(c.email)}</span></td>
            <td>${esc(c.store || '—')}</td>
            <td><span class="tag ${c.plan === 'plus' ? 'yellow' : ''}">${c.plan === 'plus' ? 'Kit + Financeiro' : 'Kit Essencial'}</span></td>
            <td><span class="tag ${c.active ? 'green' : 'red'}">${c.active ? 'Liberado' : 'Suspenso'}</span></td>
            <td>
              <div class="table-actions">
                ${btn('Editar', 'edit-client', 'small', `data-id="${c.id}"`)}
                ${btn(c.active ? 'Suspender' : 'Liberar', 'toggle-client', 'small', `data-id="${c.id}"`)}
              </div>
            </td>
          </tr>`).join('')}
        </tbody>
      </table></div>
    </section>`;
  }

  if (tab === 'suppliers') {
    const list = S.suppliers.filter(r => r.nome.toLowerCase().includes(state.adminSearch.toLowerCase())).slice(0, 30);
    return html + `<section class="panel">
      <div class="panel-head"><h2>Fornecedores</h2>${btn('Adicionar fornecedor', 'new-supplier', 'primary')}</div>
      <div class="searchbar">
        <div class="search">${icon('search')}<input id="admin-search" aria-label="Buscar fornecedor no admin" placeholder="Buscar pelo nome" value="${esc(state.adminSearch)}"></div>
      </div>
      <p class="small muted" style="margin-bottom:16px">Exibindo até 30 registros por busca. ${S.suppliers.length} contatos cadastrados.</p>
      <div class="table-wrap"><table>
        <thead><tr><th>Empresa</th><th>Segmento</th><th>Site</th><th>Ações</th></tr></thead>
        <tbody>
          ${list.map(r => `<tr>
            <td>${esc(r.nome)}</td>
            <td>${esc(r.seg)}</td>
            <td>${r.site ? 'Cadastrado' : '—'}</td>
            <td>${btn(icon('edit'), 'edit-supplier', 'small', `data-id="${r.id}" aria-label="Editar ${esc(r.nome)}"`)}</td>
          </tr>`).join('')}
        </tbody>
      </table></div>
    </section>`;
  }

  if (tab === 'content') {
    return html + `<section class="panel">
      <h2>Capítulos do guia</h2>
      ${S.chapters.map((c, i) => `<div class="detail-row"><strong>${i + 1}. ${esc(c.title)}</strong>${btn('Editar', 'edit-chapter', 'small', `data-index="${i}"`)}</div>`).join('')}
    </section>`;
  }

  if (tab === 'calendar') {
    return html + `<section class="panel">
      <h2>Planejamento por mês</h2>
      ${S.months.map((m, i) => `<div class="detail-row"><strong>${esc(m.n)}</strong><span>${m.items?.length || 0} sugestões ${btn('Editar', 'edit-month', 'small', `data-index="${i}"`)}</span></div>`).join('')}
    </section>`;
  }

  return html;
}

function clientModal(id) {
  const c = S.clients.find(x => x.id === id) || { plan: 'plus', active: true };
  modal(id ? 'Editar cliente' : 'Cadastrar novo cliente', `
    <input type="hidden" name="id" value="${esc(id || '')}">
    <div class="fields">
      ${field('Nome do cliente', 'name', c.name || '', 'text', true)}
      ${field('E-mail de acesso', 'email', c.email || '', 'email', true)}
      ${!id ? field('Senha inicial de acesso', 'password', '', 'password', true, 'Defina a senha inicial') : field('Redefinir senha (opcional)', 'password', '', 'password', false, 'Deixe em branco para manter a atual')}
      ${field('Nome da loja / empresa', 'store', c.store || '', 'text', true)}
      ${select('Oferta / Plano', 'plan', [['plus', 'Kit + Financeiro (Com Controle Financeiro)'], ['essential', 'Kit Essencial']], c.plan)}
      ${id ? select('Acesso', 'active', [['true', 'Liberado'], ['false', 'Suspenso']], String(c.active)) : ''}
    </div>
  `, 'Salvar cliente', 'client');
}

function supplierModal(id) {
  const r = S.suppliers.find(x => x.id === id) || {};
  modal(id ? 'Editar fornecedor' : 'Adicionar fornecedor', `
    <input type="hidden" name="id" value="${esc(id || '')}">
    <div class="fields">
      ${field('Nome da empresa', 'nome', r.nome || '')}
      ${field('Segmento', 'seg', r.seg || '')}
      ${field('Produtos', 'produtos', r.produtos || '')}
      ${field('Categoria', 'categoria', r.categoria || '')}
      ${field('Site', 'site', r.site || '', 'url', false)}
      ${field('Instagram (URL)', 'ig', r.ig || '', 'url', false)}
      ${field('WhatsApp (com DDD)', 'wa', r.wa?.[0] || '', 'text', false)}
      ${field('Catálogo (URL)', 'cat', r.cat?.[0] || '', 'url', false)}
      ${field('E-mail comercial', 'email', r.email || '', 'text', false)}
      ${field('Telefone', 'tel', r.tel?.[0] || '', 'text', false)}
      <div class="field full"><label for="f-nota">Observações</label><textarea id="f-nota" name="nota">${esc(r.nota || '')}</textarea></div>
    </div>
  `, 'Salvar fornecedor', 'supplier');
}

async function loadInitialData() {
  try {
    const me = await api.get('/me');
    S.session = true;
    S.name = me.user.name;
    S.email = me.user.email;
    S.role = me.user.role;
    S.plan = me.tenant.plan;
    S.store = me.tenant.name;
    S.initialBalance = me.tenant.initial_balance_cents;
    api.csrfToken = me.csrf_token;

    // Carrega dados compartilhados e do usuário
    const [suppliersRes, favs, checks, prog, notes, cal, chaps] = await Promise.all([
      api.get('/suppliers', { page: state.page, limit: 12 }),
      api.get('/favorites'),
      api.get('/checklists'),
      api.get('/reading-progress'),
      api.get('/notes'),
      api.get('/calendar'),
      api.get('/chapters')
    ]);

    S.suppliers = suppliersRes.suppliers || [];
    state.totalSuppliers = suppliersRes.total || S.suppliers.length;
    state.totalPages = suppliersRes.total_pages || 1;
    S.favs = favs || [];
    S.checks = checks || {};
    S.read = prog || [];
    S.notes = notes || {};
    S.months = cal || [];
    S.chapters = chaps || [];

    if (S.plan === 'plus') {
      await loadFinanceData();
    }

    if (S.role === 'admin') {
      const clients = await api.get('/admin/clients').catch(() => []);
      S.clients = clients || [];
    }
  } catch (e) {
    S.session = false;
  }
}

async function loadFinanceData() {
  if (S.plan !== 'plus') return;
  try {
    const [overview, txs, cats] = await Promise.all([
      api.get('/finance/overview', { month: state.financeMonth }),
      api.get('/finance/transactions', { month: state.financeMonth, search: state.txSearch, status: state.txStatus, type: state.txType }),
      api.get('/finance/categories')
    ]);

    S.financeStats = overview.stats || { start: 0, inc: 0, out: 0, balance: 0, projected: 0 };
    S.chartWeekly = overview.chart || { income: [0,0,0,0,0], expense: [0,0,0,0,0] };
    S.chartCategories = overview.categories || [];
    S.transactions = txs || [];
    S.categories = cats || [];
  } catch (e) {
    console.error('Erro ao carregar dados financeiros:', e);
  }
}

async function render() {
  let r = route();
  if (['login', 'recovery'].includes(r)) {
    document.title = 'VTD · Acesso';
    $('#app').innerHTML = login(r === 'recovery');
    return;
  }

  if (!S.session) {
    await loadInitialData();
    if (!S.session) {
      go('login');
      return;
    }
  }

  if (r.startsWith('guide/')) {
    const n = Number(r.split('/')[1]);
    if (n >= 1 && n <= 8) state.chapter = n - 1;
  }

  const base = r.split('/')[0];
  if (base === 'finance') {
    await loadFinanceData();
  }

  const body = base === 'home' ? home() :
    base === 'suppliers' ? suppliers() :
    base === 'favorites' ? suppliers(true) :
    base === 'calendar' ? calendar() :
    base === 'guide' ? guide() :
    base === 'finance' ? finance(r) :
    base === 'account' ? account() :
    base === 'admin' ? admin(r) :
    base === 'help' ? help() :
    `<div class="empty"><h1>Página não encontrada</h1><p>Escolha uma seção no menu para continuar.</p>${link('Voltar ao início', 'home', 'btn primary')}</div>`;

  document.title = 'VTD · ' + (nav.find(n => n[0] === base)?.[1] || ({ admin: 'Administração', account: 'Minha conta', help: 'Ajuda' }[base]) || 'Página');
  $('#app').innerHTML = shell(body, r);
  window.scrollTo(0, 0);
}

window.addEventListener('hashchange', render);

document.addEventListener('input', async e => {
  if (e.target.id === 'supplier-search') {
    state.search = e.target.value;
    state.page = 1;
    const res = await api.get('/suppliers', { search: state.search, segment: state.segment, wa: state.wa ? 1 : '', cat: state.cat ? 1 : '', page: 1, limit: 12 });
    S.suppliers = res.suppliers;
    state.totalSuppliers = res.total;
    state.totalPages = res.total_pages;
    const el = $('#supplier-results');
    if (el) el.innerHTML = supplierList(route() === 'favorites');
  }

  if (e.target.id === 'tx-search') {
    state.txSearch = e.target.value;
    await loadFinanceData();
    const el = $('#tx-results');
    if (el) el.innerHTML = txTable(S.transactions);
  }

  if (e.target.id === 'admin-search') {
    state.adminSearch = e.target.value;
    const start = e.target.selectionStart;
    render();
    const el = $('#admin-search');
    if (el) {
      el.focus();
      el.setSelectionRange(start, start);
    }
  }
});

document.addEventListener('change', async e => {
  const t = e.target;
  if (t.dataset.check) {
    S.checks[t.dataset.check] = t.checked;
    await api.post('/checklists', { key: t.dataset.check, checked: t.checked }).catch(() => {});
    if (route() === 'calendar') {
      const m = S.months[state.month] || { items: [] };
      const done = (m.items || []).filter((_, i) => S.checks[`m-${state.month}-${i}`]).length;
      $('#month-progress').textContent = `${done} de ${(m.items || []).length} itens concluídos`;
      $('#month-bar').style.width = ((m.items?.length ? done / m.items.length : 0) * 100) + '%';
    }
  }

  if (t.id === 'chapter-done') {
    const chNum = state.chapter + 1;
    if (t.checked) S.read = [...new Set([...S.read, chNum])];
    else S.read = S.read.filter(x => x !== chNum);
    await api.post('/reading-progress', { chapter_id: chNum, completed: t.checked }).catch(() => {});
    toast(t.checked ? 'Capítulo concluído.' : 'Capítulo reaberto.');
  }

  if (t.id === 'supplier-segment') {
    state.segment = t.value;
    state.page = 1;
    const res = await api.get('/suppliers', { search: state.search, segment: state.segment, wa: state.wa ? 1 : '', cat: state.cat ? 1 : '', page: 1, limit: 12 });
    S.suppliers = res.suppliers;
    state.totalSuppliers = res.total;
    state.totalPages = res.total_pages;
    const el = $('#supplier-results');
    if (el) el.innerHTML = supplierList(route() === 'favorites');
  }

  if (t.id === 'finance-month') {
    if (t.value) {
      state.financeMonth = t.value;
      await loadFinanceData();
      render();
    }
  }

  if (t.id === 'tx-status') {
    state.txStatus = t.value;
    await loadFinanceData();
    render();
  }
});

document.addEventListener('click', async e => {
  const el = e.target.closest('[data-action]');
  if (!el) return;
  const a = el.dataset.action;
  const id = el.dataset.id;
  const index = Number(el.dataset.index);

  if (a === 'close') {
    $('#modal').close();
    return;
  }
  if (a === 'menu') {
    $('#sidebar').classList.toggle('open');
    return;
  }
  if (a === 'logout') {
    await api.post('/auth/logout').catch(() => {});
    S.session = false;
    go('login');
    return;
  }
  if (a === 'go-suppliers') {
    state.search = '';
    state.segment = '';
    go('suppliers');
    return;
  }
  if (a === 'favorite') {
    const isFav = S.favs.includes(id);
    S.favs = isFav ? S.favs.filter(x => x !== id) : [...S.favs, id];
    await api.post('/favorites', { supplier_id: id, status: !isFav }).catch(() => {});
    const resEl = $('#supplier-results');
    if (resEl) resEl.innerHTML = supplierList(route() === 'favorites');
    return;
  }
  if (a === 'supplier-detail') {
    supplierDetail(id);
    return;
  }
  if (a === 'filter-wa' || a === 'filter-cat') {
    state[a === 'filter-wa' ? 'wa' : 'cat'] = !state[a === 'filter-wa' ? 'wa' : 'cat'];
    state.page = 1;
    const res = await api.get('/suppliers', { search: state.search, segment: state.segment, wa: state.wa ? 1 : '', cat: state.cat ? 1 : '', page: 1, limit: 12 });
    S.suppliers = res.suppliers;
    state.totalSuppliers = res.total;
    state.totalPages = res.total_pages;
    render();
    return;
  }
  if (a === 'clear-filters') {
    state.search = '';
    state.segment = '';
    state.wa = state.cat = false;
    state.page = 1;
    const res = await api.get('/suppliers', { page: 1, limit: 12 });
    S.suppliers = res.suppliers;
    state.totalSuppliers = res.total;
    state.totalPages = res.total_pages;
    render();
    return;
  }
  if (a === 'page') {
    state.page = Number(el.dataset.page);
    const res = await api.get('/suppliers', { search: state.search, segment: state.segment, wa: state.wa ? 1 : '', cat: state.cat ? 1 : '', page: state.page, limit: 12 });
    S.suppliers = res.suppliers;
    state.totalSuppliers = res.total;
    state.totalPages = res.total_pages;
    const resEl = $('#supplier-results');
    if (resEl) resEl.innerHTML = supplierList(route() === 'favorites');
    return;
  }
  if (a === 'month') {
    state.month = index;
    render();
    return;
  }
  if (a === 'chapter') {
    state.chapter = index;
    go('guide/' + (index + 1));
    return;
  }
  if (a === 'save-note') {
    const noteVal = $('#chapter-note').value;
    S.notes[state.chapter + 1] = noteVal;
    await api.post('/notes', { chapter_id: state.chapter + 1, note: noteVal }).catch(() => {});
    toast('Anotação salva com sucesso.');
    return;
  }
  if (a === 'new-tx' || a === 'edit-tx') {
    transactionModal(id);
    return;
  }
  if (a === 'delete-tx') {
    modal('Excluir lançamento?', `<p>Este lançamento será removido permanentemente do controle financeiro.</p>${btn('Confirmar exclusão', 'confirm-delete-tx', 'danger', `data-id="${id}"`)}`);
    return;
  }
  if (a === 'confirm-delete-tx') {
    try {
      await api.delete('/finance/transactions/' + id);
      $('#modal').close();
      await loadFinanceData();
      render();
      toast('Lançamento excluído com sucesso.');
    } catch (err) {
      toast(err.message);
    }
    return;
  }
  if (a === 'settle-tx') {
    const t = S.transactions.find(x => x.id === id);
    modal(t?.type === 'income' ? 'Registrar recebimento' : 'Registrar pagamento', `
      <input type="hidden" name="id" value="${id}">
      ${field('Data efetiva do pagamento / recebimento', 'date', today(), 'date')}
      <p class="storage-note">O valor passará para o realizado no mês da data efetiva informada.</p>
    `, 'Confirmar', 'settle');
    return;
  }
  if (a === 'new-category') {
    modal('Nova categoria', field('Nome da categoria', 'name'), 'Adicionar', 'category');
    return;
  }
  if (a === 'delete-category') {
    const catId = el.dataset.id;
    try {
      await api.delete('/finance/categories/' + catId);
      await loadFinanceData();
      render();
      toast('Categoria excluída com sucesso.');
    } catch (err) {
      toast(err.message);
    }
    return;
  }
  if (a === 'export-csv') {
    window.location.href = `/api/finance/export-csv?month=${encodeURIComponent(state.financeMonth)}`;
    toast('Gerando download do arquivo CSV...');
    return;
  }
  if (a === 'new-supplier' || a === 'edit-supplier') {
    supplierModal(id);
    return;
  }
  if (a === 'new-client' || a === 'edit-client') {
    clientModal(id);
    return;
  }
  if (a === 'toggle-client') {
    try {
      await api.patch('/admin/clients/' + id + '/toggle');
      const clients = await api.get('/admin/clients');
      S.clients = clients;
      render();
      toast('Acesso do cliente atualizado.');
    } catch (err) {
      toast(err.message);
    }
    return;
  }
  if (a === 'edit-chapter') {
    const c = S.chapters[index];
    modal('Editar capítulo', `
      <input type="hidden" name="index" value="${index}">
      ${field('Título', 'title', c.title)}
      <div class="field" style="margin-top:15px">
        <label for="chapter-editor">Conteúdo (texto simples)</label>
        <textarea id="chapter-editor" name="body" style="min-height:260px">${esc(c.body.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim())}</textarea>
      </div>
      <div class="field" style="margin-top:15px">
        <label for="task-editor">Tarefa do capítulo</label>
        <textarea name="task" id="task-editor">${esc(c.task)}</textarea>
      </div>
    `, 'Salvar capítulo', 'chapter');
    return;
  }
  if (a === 'edit-month') {
    const m = S.months[index];
    modal('Editar ' + esc(m.n), `
      <input type="hidden" name="index" value="${index}">
      <div class="field"><label for="m-lead">Apresentação</label><textarea id="m-lead" name="lead">${esc(m.lead)}</textarea></div>
      <div class="field" style="margin-top:14px"><label for="m-buy">Orientação de compra</label><textarea id="m-buy" name="buy">${esc(m.buy)}</textarea></div>
      <div class="field" style="margin-top:14px"><label for="m-tip">Dica prática</label><textarea id="m-tip" name="tip">${esc(m.tip)}</textarea></div>
      <div class="field" style="margin-top:14px">
        <label for="m-items">Produtos (um por linha; tipo: febre, estação ou coringa)</label>
        <textarea id="m-items" name="items">${esc((m.items || []).map(([s, k]) => s + ' | ' + ({ f: 'febre', e: 'estação', c: 'coringa' })[k]).join('\n'))}</textarea>
      </div>
    `, 'Salvar mês', 'month');
    return;
  }
});

document.addEventListener('submit', async e => {
  const form = e.target.closest('[data-form]');
  if (!form) return;
  e.preventDefault();
  const d = Object.fromEntries(new FormData(form));
  const kind = form.dataset.form;

  if (kind === 'login') {
    try {
      const res = await api.post('/auth/login', { email: d.email, password: d.password });
      S.session = true;
      S.name = res.user.name;
      S.email = res.user.email;
      S.role = res.user.role;
      S.plan = res.tenant.plan;
      S.store = res.tenant.name;
      S.initialBalance = res.tenant.initial_balance_cents;
      api.csrfToken = res.csrf_token;
      await loadInitialData();
      go('home');
      toast('Bem-vindo(a) ao Venda Todo Dia!');
    } catch (err) {
      toast(err.message);
    }
    return;
  }

  if (kind === 'recovery') {
    try {
      await api.post('/auth/forgot-password', { email: d.email });
      toast('Se o e-mail estiver cadastrado, as instruções foram enviadas.');
      go('login');
    } catch (err) {
      toast(err.message);
    }
    return;
  }

  if (kind === 'reset-password') {
    try {
      await api.post('/auth/reset-password', { token: d.token, new_password: d.new_password });
      toast('Sua senha foi redefinida com sucesso!');
      go('login');
    } catch (err) {
      toast(err.message);
    }
    return;
  }

  if (kind === 'profile') {
    const amount = Number(d.initialBalance);
    if (!Number.isFinite(amount)) {
      toast('Informe um saldo válido.');
      return;
    }
    try {
      await api.put('/me', {
        name: d.name.trim(),
        store: d.store.trim(),
        email: d.email.trim(),
        initial_balance_cents: Math.round(amount * 100)
      });
      S.name = d.name.trim();
      S.store = d.store.trim();
      S.email = d.email.trim();
      S.initialBalance = Math.round(amount * 100);
      render();
      toast('Preferências salvas com sucesso.');
    } catch (err) {
      toast(err.message);
    }
    return;
  }

  if (kind === 'transaction') {
    const value = Math.round(Number(d.value) * 100);
    if (!Number.isFinite(value) || value <= 0 || !d.description.trim()) {
      toast('Preencha uma descrição e um valor maior que zero.');
      return;
    }
    const payload = {
      description: d.description.trim(),
      type: d.type,
      value_cents: value,
      category_id: Number(d.category_id),
      date: d.date,
      status: d.status
    };
    try {
      if (d.id) {
        await api.put('/finance/transactions/' + d.id, payload);
      } else {
        await api.post('/finance/transactions', payload);
      }
      $('#modal').close();
      await loadFinanceData();
      render();
      toast('Lançamento salvo com sucesso.');
    } catch (err) {
      toast(err.message);
    }
    return;
  }

  if (kind === 'settle') {
    try {
      await api.post('/finance/transactions/' + d.id + '/settle', { effective_date: d.date });
      $('#modal').close();
      await loadFinanceData();
      render();
      toast('Quitação registrada com sucesso.');
    } catch (err) {
      toast(err.message);
    }
    return;
  }

  if (kind === 'category') {
    const c = d.name.trim();
    if (!c) {
      toast('Informe um nome de categoria.');
      return;
    }
    try {
      await api.post('/finance/categories', { name: c });
      $('#modal').close();
      await loadFinanceData();
      render();
      toast('Categoria criada com sucesso.');
    } catch (err) {
      toast(err.message);
    }
    return;
  }

  if (kind === 'client') {
    try {
      if (d.id) {
        await api.put('/admin/clients/' + d.id, {
          name: d.name.trim(),
          email: d.email.trim(),
          store: d.store.trim(),
          plan: d.plan,
          active: d.active === 'true',
          password: d.password || undefined
        });
        toast('Cliente atualizado com sucesso.');
      } else {
        await api.post('/admin/clients', {
          name: d.name.trim(),
          email: d.email.trim(),
          password: d.password,
          store: d.store.trim(),
          plan: d.plan
        });
        toast('Cliente cadastrado com sucesso! As credenciais de acesso foram enviadas por e-mail.');
      }
      $('#modal').close();
      const clients = await api.get('/admin/clients');
      S.clients = clients;
      render();
    } catch (err) {
      toast(err.message);
    }
    return;
  }

  if (kind === 'supplier') {
    try {
      await api.put('/admin/suppliers/' + d.id, {
        nome: d.nome.trim(),
        seg: d.seg.trim(),
        produtos: d.produtos.trim(),
        categoria: d.categoria.trim(),
        site: d.site,
        ig: d.ig,
        wa: d.wa ? [d.wa] : [],
        cat: d.cat ? [d.cat] : [],
        email: d.email,
        tel: d.tel ? [d.tel] : [],
        nota: d.nota
      });
      $('#modal').close();
      const res = await api.get('/suppliers', { search: state.search, page: state.page, limit: 12 });
      S.suppliers = res.suppliers;
      render();
      toast('Fornecedor atualizado com sucesso.');
    } catch (err) {
      toast(err.message);
    }
    return;
  }

  if (kind === 'chapter') {
    try {
      await api.put('/admin/chapters/' + d.index, {
        title: d.title.trim(),
        body: d.body.split(/\n\s*\n/).map(p => '<p>' + esc(p) + '</p>').join(''),
        task: d.task.trim()
      });
      $('#modal').close();
      S.chapters = await api.get('/chapters');
      render();
      toast('Capítulo atualizado com sucesso.');
    } catch (err) {
      toast(err.message);
    }
    return;
  }

  if (kind === 'month') {
    const items = d.items.split('\n').filter(x => x.trim()).map(line => {
      const [t, k = 'coringa'] = line.split('|');
      return [t.trim(), ({ 'febre': 'f', 'estação': 'e', 'estacao': 'e', 'coringa': 'c' })[k.trim().toLowerCase()] || 'c'];
    });
    if (!items.length) {
      toast('Adicione pelo menos um produto ao mês.');
      return;
    }
    try {
      await api.put('/admin/calendar/' + d.index, {
        lead: d.lead,
        buy: d.buy,
        tip: d.tip,
        items: items
      });
      $('#modal').close();
      S.months = await api.get('/calendar');
      render();
      toast('Mês atualizado com sucesso.');
    } catch (err) {
      toast(err.message);
    }
    return;
  }
});

// Inicialização da aplicação
render();
