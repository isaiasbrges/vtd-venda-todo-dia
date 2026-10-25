'use strict';

// Cliente de API HTTP centralizado com envio automático de credenciais e CSRF
const api = {
  csrfToken: '',
  async req(endpoint, opts = {}) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 20000);
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
        signal: opts.signal || controller.signal,
        headers
      });

      // Trata respostas sem corpo (ex: 204)
      if (res.status === 204) return null;

      let data;
      try {
        data = await res.json();
      } catch {
        throw new Error('O servidor não retornou uma resposta válida. Tente novamente.');
      }
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
      if (e.name === 'AbortError') e = new Error('O servidor demorou para responder. Tente novamente.');
      else if (e instanceof TypeError) e = new Error('Não foi possível conectar ao servidor. Confira sua conexão e tente novamente.');
      const form = document.querySelector('form[data-submitting]');
      if (form) formError(form, e.message || 'Não foi possível concluir a ação.');
      console.error('API Error:', e);
      throw e;
    } finally {
      clearTimeout(timeout);
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
  shield: 'm12 3 9 4v5c0 5-9 10-9 10S3 17 3 12V7z m-4 9 3 3 5-6',
  users: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2 M9 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M23 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
  package: 'M16.5 9.4 7.55 4.24 M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z M3.27 6.96 12 12.01l8.73-5.05 M12 22.08V12',
  link: 'M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71 M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71',
  list: 'M8 6h13 M8 12h13 M8 18h13 M3 6h.01 M3 12h.01 M3 18h.01',
  alert: 'M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z M12 9v4 M12 17h.01',
  copy: 'M20 9h-9a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2z M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1',
  refresh: 'M21.5 2v6h-6 M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67'
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
  catalogTotal: 0,
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
const noteDrafts = {};
function toast(s) {
  const el = $('#toast');
  if (!el) return;
  el.textContent = s;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3500);
}

function formError(form, message) {
  let feedback = form.querySelector('.form-feedback');
  if (!feedback) {
    feedback = document.createElement('p');
    feedback.className = 'form-feedback';
    feedback.setAttribute('role', 'alert');
    form.prepend(feedback);
  }
  feedback.textContent = message;
}

function errorPanel(error) {
  return `<section class="empty" role="alert"><h1>Não foi possível carregar esta tela</h1><p>${esc(error.message || 'Confira sua conexão e tente novamente.')}</p>${btn('Tentar novamente', 'retry-page', 'primary')}${link('Voltar ao início', 'home')}</section>`;
}

function syncMenuState() {
  document.querySelectorAll('.sidebar, .master-sidebar').forEach(sidebar => {
    const masterMenu = sidebar.classList.contains('master-sidebar');
    const mobile = window.matchMedia?.(`(max-width: ${masterMenu ? 768 : 760}px)`).matches;
    sidebar.inert = !!mobile && !sidebar.classList.contains('open');
    if (!mobile) {
      sidebar.classList.remove('open');
      const backdrop = $('.nav-backdrop');
      if (backdrop) backdrop.hidden = true;
    }
  });
}

function filterRecentClients() {
  const query = ($('#master-quick-search')?.value || '').trim().toLowerCase();
  const offer = $('#master-quick-offer')?.value || '';
  const status = $('#master-quick-status')?.value || '';
  document.querySelectorAll('#master-recent-clients-tbody tr').forEach(row => {
    const text = row.textContent.toLowerCase();
    const matchesOffer = !offer || row.querySelector(`.badge-offer.${offer}`);
    const matchesStatus = !status || text.includes(status === 'active' ? 'liberado' : 'suspenso');
    row.hidden = !((!query || text.includes(query)) && matchesOffer && matchesStatus);
  });
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
  return `<a class="logo" href="#/home" aria-label="VTD Venda Todo Dia, início">${brandImage()}</a>`;
}
function brandImage() {
  return '<span class="brand-art"><img src="assets/logo-vtd.png" width="2172" height="724" alt="VTD Venda Todo Dia por Matheus"></span>';
}
function field(label, name, value = '', type = 'text', required = true, placeholder = '') {
  const extra = type === 'number' ? 'step="0.01" inputmode="decimal"' : type === 'email' ? 'autocomplete="email"' : type === 'password' ? `autocomplete="${name === 'password' ? 'current-password' : 'new-password'}"` : '';
  const input = `<input id="f-${name}" name="${name}" type="${type}" value="${esc(value)}" placeholder="${esc(placeholder)}" ${extra} ${['new_password', 'confirm_password'].includes(name) ? 'minlength="6"' : ''} ${required ? 'required' : ''}>`;
  return `<div class="field"><label for="f-${name}">${label}</label>${type === 'password' ? `<div class="password-control">${input}<button type="button" data-action="toggle-password" aria-controls="f-${name}" aria-label="Mostrar senha">Mostrar</button></div>` : input}</div>`;
}
function select(label, name, options, value) {
  return `<div class="field"><label for="f-${name}">${label}</label><select id="f-${name}" name="${name}">${options.map(([v, l]) => `<option value="${esc(v)}" ${String(v) === String(value) ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></div>`;
}
function modal(title, body, submit = '', form = '') {
  const el = $('#modal');
  if (el.open) el.close();
  el.setAttribute('aria-labelledby', 'dialog-title');
  el.innerHTML = `<div class="dialog-head"><h2 id="dialog-title">${title}</h2><button class="close" data-action="close" aria-label="Fechar janela">×</button></div>${form ? `<form data-form="${form}">` : ''}${body}<div class="modal-actions">${btn('Fechar', 'close')}${submit ? `<button class="btn primary" type="submit">${submit}</button>` : ''}</div>${form ? '</form>' : ''}`;
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
      ${S.role === 'admin' ? `<a href="#/master/overview" class="${section === 'master' || section === 'admin' ? 'active' : ''}">${icon('shield')}Painel Master</a>` : ''}
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
    ${S.role === 'admin' ? `<div style="background:#111215; color:#FFD500; padding:8px 34px; font-size:12px; font-weight:600; display:flex; justify-content:space-between; align-items:center;"><span>Modo lojista ativo.</span><a href="#/master/overview" style="background:#FFD500; color:#111215; padding:4px 12px; border-radius:6px; font-weight:700; text-decoration:none;">Voltar ao Painel Master →</a></div>` : ''}
    <header class="topbar">
      <div class="header-mobile-logo">
        <button class="mobile-menu" data-action="menu" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false">${icon('menu')}</button>
        <a class="mobile-brand" href="#/home" aria-label="VTD Venda Todo Dia, início">${brandImage()}</a>
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
    <main class="content" id="main" tabindex="-1">${body}</main>
  </div>
  <button class="nav-backdrop" data-action="close-menu" aria-label="Fechar menu" hidden></button>
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
            ${field('Confirme a nova senha', 'confirm_password', '', 'password', true, 'Repita sua nova senha')}
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
  const completed = S.chapters.filter((_, i) => S.read.includes(i + 1)).length;
  const total = S.chapters.length;
  const progress = total ? Math.round(completed / total * 100) : 0;
  const nextChapter = Math.max(0, S.chapters.findIndex((_, i) => !S.read.includes(i + 1))) + 1;
  return `<div class="home-greeting"><span class="eyebrow">SEU ESPAÇO DE CRESCIMENTO</span><span>${esc(new Date().toLocaleDateString('pt-BR', { day: 'numeric', month: 'long' }))}</span></div>` + head(`Olá, ${esc(S.name.split(' ')[0] || 'lojista')}. Vamos vender?`, 'Tudo para sua próxima venda. Seus materiais e sua loja, no mesmo lugar.') +
    `<section class="welcome home-hero"><div class="hero-content"><div class="eyebrow">IDEIAS QUE MOVIMENTAM SUA LOJA</div><h2>O próximo passo<br>começa <em>aqui.</em></h2><p>Encontre bons produtos, planeje suas campanhas e transforme conhecimento em ação.</p>${link('Encontrar fornecedores ' + icon('arrow'), 'suppliers', 'btn primary')}</div><aside class="hero-learning"><span class="hero-learning-icon">${icon('book')}</span><span class="eyebrow">SUA TRILHA DE APRENDIZADO</span><h3>Um capítulo.<br>Uma nova possibilidade.</h3><p>${completed} de ${total} capítulos concluídos</p><div class="progress"><i style="width:${progress}%"></i></div>${link('Continuar meu guia ' + icon('arrow'), 'guide/' + nextChapter, 'hero-learning-link')}</aside></section>
    <div class="overview-strip">
      <a href="#/suppliers" class="overview-tile"><span class="overview-icon">${icon('truck')}</span><div><strong>${S.catalogTotal}</strong><span>Fornecedores para explorar</span></div>${icon('arrow')}</a>
      <a href="#/favorites" class="overview-tile"><span class="overview-icon">${icon('star')}</span><div><strong>${S.favs.length}</strong><span>Contatos nos seus favoritos</span></div>${icon('arrow')}</a>
      <a href="#/calendar" class="overview-tile"><span class="overview-icon">${icon('calendar')}</span><div><strong>${esc(m.n)}</strong><span>Seu planejamento comercial</span></div>${icon('arrow')}</a>
    </div>
    <div class="columns">
      <section class="panel">
        <div class="panel-head"><h2>Explore seu kit</h2><span class="tag">Seu acesso</span></div>
        ${[['suppliers', 'truck', 'Fornecedores do Matheus', `${S.catalogTotal} contatos organizados por categoria`],
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
  const list = S.suppliers;
  const count = state.totalSuppliers;
  const pages = state.totalPages;
  const filtered = !!(state.search || state.segment || state.wa || state.cat);

  return `<div class="list-count">
    <span>${count} ${favs ? (count === 1 ? 'favorito' : 'favoritos') : (count === 1 ? 'fornecedor encontrado' : 'fornecedores encontrados')}</span>
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
      ${S.role === 'admin' && route().startsWith('master/') ? btn('Editar', 'edit-supplier', 'small', `data-id="${r.id}"`) : ''}
    </div>
  </article>`).join('') : `<div class="empty">
    ${icon(favs ? 'star' : 'search')}
    <h3>${favs ? (filtered ? 'Nenhum favorito com esses filtros.' : 'Nenhum favorito por aqui ainda.') : 'Nenhum fornecedor encontrado.'}</h3>
    <p>${favs && !filtered ? 'Marque a estrela de um fornecedor para encontrá-lo aqui.' : 'Tente outra busca ou limpe os filtros.'}</p>
    ${btn(favs && !filtered ? 'Explorar fornecedores' : 'Limpar filtros', favs && !filtered ? 'go-suppliers' : 'clear-filters', 'primary')}
  </div>`}
  <div class="pagination">
    ${btn('← Anterior', 'page', 'small', `data-page="${state.page - 1}" ${state.page <= 1 ? 'disabled' : ''}`)}
    <span class="small">${state.page} / ${pages}</span>
    ${btn('Próxima →', 'page', 'small', `data-page="${state.page + 1}" ${state.page >= pages ? 'disabled' : ''}`)}
  </div>`;
}

function suppliers(favs = false) {
  const segs = [...new Set([...(typeof ORIGINAL_SUPPLIERS !== 'undefined' ? ORIGINAL_SUPPLIERS.map(s => s.seg) : []), ...S.suppliers.map(s => s.seg), state.segment].filter(Boolean))].sort((a, b) => a.localeCompare(b, 'pt-BR'));
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
    <div id="supplier-results" aria-live="polite">${supplierList(favs)}</div>
    <p class="storage-note">Contatos comerciais importados do material original. Confirme autenticidade, pedido mínimo, frete e condições diretamente com cada fornecedor.</p>`;
}

function supplierDetail(id) {
  const r = S.suppliers.find(x => x.id === id);
  if (!r) return;
  const channels = [
    ['Site', r.site],
    ['Instagram', r.ig],
    ...(r.wa || []).map((number, i) => ['WhatsApp' + (i ? ' ' + (i + 1) : ''), 'https://wa.me/' + (number.replace(/\D/g, '').startsWith('55') && number.replace(/\D/g, '').length > 11 ? '' : '55') + number.replace(/\D/g, '')]),
    ['Facebook', r.fb], ['YouTube', r.yt], ['LinkedIn', r.li], ['TikTok', r.tt], ['Página de contato', r.contato],
    ...((r.cat || []).map((u, i) => ['Catálogo ' + (i + 1), u]))
  ].filter(x => x[1] && safeURL(x[1]) !== '#');

  modal(esc(r.nome), `
    <span class="tag yellow">${esc(r.seg)}</span>
    <p>${esc(r.produtos)}</p>
    <div class="contact-links">
      ${channels.map(([l, u]) => `<a class="btn small" href="${esc(safeURL(u))}" target="_blank" rel="noopener noreferrer">${esc(l)} ${icon('external')}</a>`).join('')}
    </div>
    <div class="contact-links">
      ${(r.tel || []).filter(number => number.replace(/\D/g, '')).map(number => `<a class="btn small" href="tel:${number.replace(/[^+\d]/g, '')}">Ligar: ${esc(number)}</a>`).join('')}
      ${(r.email || '').split(/[,;]/).map(email => email.trim()).filter(email => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)).map(email => `<a class="btn small" href="mailto:${esc(email)}">Enviar e-mail ${icon('mail')}</a>`).join('')}
    </div>
    ${[['WhatsApp', (r.wa || []).join(' · ')], ['Telefone', (r.tel || []).join(' · ')], ['E-mail', r.email]].filter(x => x[1]).map(([k, v]) => `<div class="detail-row"><span>${k}</span><strong>${esc(v)}</strong></div>`).join('')}
    ${r.nota ? `<div class="note">${esc(r.nota)}</div>` : ''}
    <p class="muted small">Confirme diretamente as condições antes de fazer qualquer pagamento.</p>
  `);
}

function calendar() {
  if (!S.months.length) return head('Calendário comercial', 'Prepare sua loja antes das próximas datas.') + '<section class="empty"><h2>Calendário em preparação</h2><p>As orientações de cada mês aparecerão aqui quando estiverem disponíveis.</p></section>';
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
  if (!S.chapters.length) return head('Guia do Matheus', 'Aprenda no seu ritmo.') + '<section class="empty"><h2>Seu guia está em preparação</h2><p>Os capítulos aparecerão aqui quando estiverem disponíveis.</p></section>';
  state.chapter = Math.min(state.chapter, Math.max(0, S.chapters.length - 1));
  const ch = S.chapters[state.chapter] || { title: '', body: '', task: '', name: '' };
  return head('Guia do Matheus', 'Leia no seu ritmo e transforme cada capítulo em uma ação.') +
    `<div class="reading">
      <aside class="panel chapters">
        <h3>Loja que Vende Todo Dia</h3>
        <p class="small muted">Por Matheus · ${S.read.length} de ${S.chapters.length} concluídos</p>
        <div class="progress"><i style="width:${(S.chapters.length ? Math.min(100, S.read.length / S.chapters.length * 100) : 0)}%"></i></div>
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
            <textarea id="chapter-note" placeholder="Registre suas ideias para aplicar na loja.">${esc(noteDrafts[state.chapter + 1] ?? S.notes[state.chapter + 1] ?? '')}</textarea>
          </label>
          <p class="note-draft" id="note-status">${noteDrafts[state.chapter + 1] !== undefined ? 'Rascunho ainda não salvo.' : 'Clique em salvar depois de editar suas anotações.'}</p>
          ${btn('Salvar anotação', 'save-note', 'small', `style="margin-top:10px"`)}
        </div>
        <div class="reader-nav">
          ${btn('← Anterior', 'chapter', '', `data-index="${state.chapter - 1}" ${!state.chapter ? 'disabled' : ''}`)}
          ${btn(state.chapter === S.chapters.length - 1 ? 'Voltar ao primeiro capítulo' : 'Próximo capítulo →', 'chapter', 'primary', `data-index="${state.chapter === S.chapters.length - 1 ? 0 : state.chapter + 1}"`)}
        </div>
      </article>
    </div>`;
}

function chapterText(html) {
  const doc = new DOMParser().parseFromString(html || '', 'text/html');
  doc.querySelectorAll('p,h2,h3,h4,li,blockquote,br').forEach(node => node.append(doc.createTextNode('\n\n')));
  return doc.body.textContent.replace(/\n{3,}/g, '\n\n').trim();
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
      (suffix !== 'transactions' || !state.txStatus || t.status === state.txStatus) &&
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
  if (!S.categories.length) {
    modal('Organize seu primeiro lançamento', `<p>Crie uma categoria antes de registrar entradas e saídas.</p>${btn('Criar categoria', 'new-category', 'primary')}`);
    return;
  }
  const defaultDate = state.financeMonth === today().slice(0, 7) ? today() : state.financeMonth + '-01';
  const t = S.transactions.find(x => String(x.id) === String(id)) || { type: 'expense', value: 0, category_id: S.categories[0].id, date: defaultDate, status: 'pending' };
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
    S.catalogTotal = suppliersRes.total || 0;
    state.totalSuppliers = suppliersRes.total || S.suppliers.length;
    state.totalPages = suppliersRes.total_pages || 1;
    S.favs = favs || [];
    S.checks = checks || {};
    S.read = prog || [];
    S.notes = notes || {};
    S.months = cal || [];
    S.chapters = chaps || [];

  } catch (e) {
    if (e.status === 401) S.session = false;
    throw e;
  }
}

let supplierRequestId = 0;
let supplierTimer;
let supplierContext = '';
async function loadSuppliers() {
  const requestId = ++supplierRequestId;
  const current = route();
  const favs = current === 'favorites';
  const result = await api.get('/suppliers', {
    search: state.search, segment: state.segment,
    wa: state.wa ? 1 : '', cat: state.cat ? 1 : '',
    favs: favs ? 1 : '', page: state.page, limit: 12
  });
  if (requestId !== supplierRequestId || route() !== current) return false;
  if (state.page > result.total_pages) {
    state.page = Math.max(1, result.total_pages);
    return loadSuppliers();
  }
  S.suppliers = result.suppliers || [];
  state.totalSuppliers = result.total || 0;
  state.totalPages = result.total_pages || 1;
  return true;
}

async function refreshSupplierResults() {
  const current = route();
  const target = $('#supplier-results');
  target?.setAttribute('aria-busy', 'true');
  try {
    if (await loadSuppliers()) {
      const results = $('#supplier-results');
      if (results) results.innerHTML = supplierList(current === 'favorites');
    }
  } catch (error) {
    if (route() === current && $('#supplier-results')) $('#supplier-results').innerHTML = errorPanel(error);
  } finally {
    target?.removeAttribute('aria-busy');
  }
}

let financeRequestId = 0;
let financeSearchTimer;
async function loadFinanceData() {
  if (S.plan !== 'plus') return;
  const requestId = ++financeRequestId;
  const current = route();
  const suffix = current.split('/')[1];
  const pending = ['payable', 'receivable'].includes(suffix);
  try {
    const [overview, txs, cats] = await Promise.all([
      api.get('/finance/overview', { month: state.financeMonth }),
      api.get('/finance/transactions', { month: state.financeMonth, search: state.txSearch, status: pending ? 'pending' : state.txStatus, type: suffix === 'payable' ? 'expense' : suffix === 'receivable' ? 'income' : state.txType }),
      api.get('/finance/categories')
    ]);

    if (requestId !== financeRequestId || current !== route()) return;
    S.financeStats = overview.stats || { start: 0, inc: 0, out: 0, balance: 0, projected: 0 };
    S.chartWeekly = overview.chart || { income: [0,0,0,0,0], expense: [0,0,0,0,0] };
    S.chartCategories = overview.categories || [];
    S.transactions = txs || [];
    S.categories = cats || [];
  } catch (e) {
    throw e;
  }
}

async function renderMasterRoute(r) {
  const sub = r.replace(/^master\/?/, '').split('/')[0] || 'overview';

  if (sub === 'overview') {
    try {
      const data = await api.get('/master/overview');
      masterState.overview = data;
    } catch (e) {
      throw e;
    }
    return master.renderOverview();
  }

  if (sub === 'clients') {
    try {
      const res = await api.get('/master/clients', {
        search: masterState.clientSearch,
        offer: masterState.clientOffer,
        status: masterState.clientStatus,
        page: masterState.clientPage,
        limit: masterState.clientLimit
      });
      masterState.clients = res.clients || [];
      masterState.clientTotal = res.total || 0;
      masterState.clientTotalPages = res.total_pages || 1;
    } catch (e) {
      throw e;
    }
    return master.renderClients();
  }

  if (sub === 'products') {
    try {
      const res = await api.get('/master/products');
      masterState.products = res || [];
    } catch (e) {
      throw e;
    }
    return master.renderProducts();
  }

  if (sub === 'integrations') {
    try {
      const res = await api.get('/master/integrations');
      masterState.integrations = res || [];
    } catch (e) {
      throw e;
    }
    return master.renderIntegrations();
  }

  if (sub === 'events') {
    try {
      const res = await api.get('/master/events', {
        status: masterState.eventStatus,
        integration_id: masterState.eventIntegrationId,
        search: masterState.eventSearch,
        page: masterState.eventPage,
        limit: masterState.eventLimit
      });
      masterState.events = res.events || [];
      masterState.eventTotal = res.total || 0;
      masterState.eventTotalPages = res.total_pages || 1;
    } catch (e) {
      throw e;
    }
    return master.renderEvents();
  }

  if (sub === 'suppliers') {
    supplierContext = 'master/suppliers';
    await loadSuppliers();
    return `
      <div class="master-header">
        <div>
          <h1>Catálogo de Fornecedores</h1>
          <p>Gerencie os contatos comerciais da plataforma.</p>
        </div>
        <div class="master-header-actions">
          <button class="btn-master-primary" data-action="new-supplier">
            ${mIcon('plus')} Adicionar fornecedor
          </button>
        </div>
      </div>
      <section class="master-card">
        ${suppliers(false)}
      </section>
    `;
  }

  if (sub === 'calendar') {
    return `
      <div class="master-header">
        <div>
          <h1>Calendário Comercial</h1>
          <p>Planejamento estratégico de vendas e compras dos 12 meses.</p>
        </div>
      </div>
      <section class="master-card">
        <h2>Planejamento por mês</h2>
        ${S.months.map((m, i) => `<div class="detail-row"><strong>${esc(m.n)}</strong>${btn('Editar mês', 'edit-month', 'small', `data-index="${i}"`)}</div>`).join('')}
      </section>
    `;
  }

  if (sub === 'guide') {
    return `
      <div class="master-header">
        <div>
          <h1>Guia do Matheus</h1>
          <p>Conteúdo formativo dos 8 capítulos do curso.</p>
        </div>
      </div>
      <section class="master-card">
        <h2>Capítulos do guia</h2>
        ${S.chapters.map((c, i) => `<div class="detail-row"><strong>${i + 1}. ${esc(c.title)}</strong>${btn('Editar capítulo', 'edit-chapter', 'small', `data-index="${i}"`)}</div>`).join('')}
      </section>
    `;
  }

  if (sub === 'settings') {
    try {
      const s = await api.get('/master/settings');
      masterState.settings = s;
    } catch (e) {
      throw e;
    }
    return master.renderSettings();
  }

  return `<div class="empty"><h1>Seção do Master não encontrada</h1><p><a href="#/master/overview" class="btn primary">Voltar à visão geral</a></p></div>`;
}

function masterClientModal(client = null) {
  modal(client ? 'Editar cliente' : 'Criar cliente', `
    <input type="hidden" name="id" value="${esc(client?.tenant_id || '')}">
    <div class="fields">
      ${field('Nome completo do cliente', 'name', client?.name || '', 'text', true, 'Ex: Ana Souza')}
      ${field('E-mail de acesso', 'email', client?.email || '', 'email', true, 'Ex: ana@exemplo.com')}
      ${field('Nome da loja / empresa', 'store', client?.store || '', 'text', true, 'Ex: Bella Tênis')}
      ${select('Produto liberado', 'offer', [['kit-plus', 'Kit + Financeiro (Completo)'], ['kit-essencial', 'Kit Essencial']], client?.plan || 'kit-plus')}
      ${select('Status do acesso', 'status', [['active', 'Liberado'], ['suspended', 'Suspenso']], client?.status || 'active')}
      ${field('Validade do acesso (opcional)', 'expires_at', client?.expires_at ? client.expires_at.slice(0, 10) : '', 'date', false)}
      <div class="field full" style="margin-top:10px;">
        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
          <input type="checkbox" name="send_invite" value="1" checked style="width:18px; height:18px; accent-color:#FFD500;">
          <span>Enviar convite para definição de senha por e-mail</span>
        </label>
        <small class="muted" style="display:block; margin-top:4px;">Se não houver servidor SMTP configurado, a conta será criada com segurança e o sistema informará que o e-mail não pôde ser enviado.</small>
      </div>
    </div>
  `, 'Salvar cliente', 'master-client');
}

async function masterClientDetailModal(tenantId) {
  try {
    const data = await api.get('/master/clients/' + tenantId);
    const c = data.client;
    const accesses = data.accesses || [];
    const history = data.history || [];

    modal('Ficha do Cliente: ' + esc(c.client_name), `
      <div style="margin-bottom:20px; padding:16px; background:#F9FAFB; border-radius:8px; border:1px solid #E5E7EB;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
          <div>
            <h3 style="margin:0 0 4px 0; font-size:18px;">${esc(c.client_name)}</h3>
            <span class="muted">${esc(c.client_email)}</span><br>
            <span style="font-size:12px;">Loja: <b>${esc(c.store_name)}</b></span>
          </div>
          <div>
            <span class="badge-status-pill ${c.status === 'active' ? 'green' : 'red'}">${c.status === 'active' ? 'Liberado' : 'Suspenso'}</span>
          </div>
        </div>
      </div>

      <h4 style="margin:16px 0 8px 0;">Produtos e Acessos Vinculados</h4>
      <div class="master-table-wrapper" style="margin-bottom:20px;">
        <table class="master-table" style="font-size:12px;">
          <thead>
            <tr>
              <th>Produto</th>
              <th>Origem</th>
              <th>Referência / Pedido</th>
              <th>Status</th>
              <th>Ação</th>
            </tr>
          </thead>
          <tbody>
            ${accesses.length === 0 ? `<tr><td colspan="5" class="muted">Nenhum produto vinculado.</td></tr>` : accesses.map(a => `
              <tr>
                <td><b>${esc(a.product_name || a.product_id)}</b></td>
                <td><span class="badge-offer essential">${esc(a.source)}</span></td>
                <td>${esc(a.external_reference || '—')}</td>
                <td><span class="badge-status-pill ${a.status === 'active' ? 'green' : 'red'}">${esc(a.status)}</span></td>
                <td>
                  ${a.status === 'active' ? `
                    <button class="btn-table-action" style="color:#DC2626;" data-action="master-revoke-access" data-tenant="${c.tenant_id}" data-access="${a.id}">
                      Revogar produto
                    </button>
                  ` : 'Revogado'}
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>

      <h4 style="margin:16px 0 8px 0;">Ações Rápidas</h4>
      <div style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
        <button class="btn-table-action" data-action="master-toggle-client" data-id="${c.tenant_id}">
          ${c.status === 'active' ? 'Suspender Conta' : 'Reativar Conta'}
        </button>
        <button class="btn-table-action" data-action="master-resend-invite" data-id="${c.tenant_id}">
          Reenviar Convite de Senha
        </button>
      </div>

      <h4 style="margin:16px 0 8px 0;">Histórico de Alterações</h4>
      <div style="font-size:11px; color:#4B5563; max-height:120px; overflow-y:auto; border:1px solid #E5E7EB; border-radius:6px; padding:10px;">
        ${history.length === 0 ? 'Nenhuma alteração registrada.' : history.map(h => `
          <div style="padding:4px 0; border-bottom:1px solid #F3F4F6;">
            <b>${esc(h.created_at)}</b>: Ação <code>${esc(h.action)}</code>
          </div>
        `).join('')}
      </div>
    `, '', '');
  } catch (e) {
    toast('Erro ao abrir ficha do cliente: ' + e.message);
  }
}

function masterIntegrationModal(integ = null) {
  const isEdit = !!integ;
  const mappings = integ?.offer_mappings || {};
  const mappingsText = Object.entries(mappings).map(([k, v]) => `${k} = ${v}`).join('\n');

  modal(isEdit ? 'Configurar integração' : 'Nova integração', `
    <input type="hidden" name="id" value="${esc(integ?.id || '')}">
    <div class="fields">
      ${field('Nome da integração', 'name', integ?.name || '', 'text', true, 'Ex: Checkout Principal Greenn')}
      ${select('Provedor', 'provider', [['greenn', 'Greenn'], ['kiwify', 'Kiwify'], ['hotmart', 'Hotmart'], ['custom', 'Personalizado / Outro']], integ?.provider || 'greenn')}
      ${select('Ambiente', 'environment', [['production', 'Produção'], ['sandbox', 'Testes / Sandbox']], integ?.environment || 'production')}
      ${select('Status', 'status', [['active', 'Ativo'], ['inactive', 'Inativo']], integ?.status || 'active')}
      ${field('Chave secreta / Token do Webhook (opcional)', 'secret_key', '', 'password', false, integ?.has_secret ? 'Chave salva. Deixe vazio para manter.' : 'Secret configurado no checkout')}
      <div class="field full">
        <label for="m-mappings">Mapeamento de Ofertas Externas (um por linha no formato: <code>codigo_externo = kit-plus</code> ou <code>codigo_externo = kit-essencial</code>)</label>
        <textarea id="m-mappings" name="offer_mappings_text" placeholder="codigo_da_sua_oferta = kit-plus" style="font-family:monospace; min-height:80px;">${esc(mappingsText)}</textarea>
      </div>
    </div>
  `, 'Salvar integração', 'master-integration');
}

async function masterReviewEventModal(eventId) {
  try {
    const e = await api.get('/master/events/' + eventId);
    modal('Detalhes do Evento #' + e.id, `
      <div style="display:flex; flex-direction:column; gap:12px;">
        <div style="display:flex; justify-content:space-between; align-items:center; background:#F9FAFB; padding:12px; border-radius:8px;">
          <div>
            <b>${esc(e.event_type)}</b> (${esc(e.provider)})<br>
            <span class="muted small">ID Externo: ${esc(e.external_reference || '—')}</span>
          </div>
          <span class="badge-status-pill ${e.status === 'success' ? 'green' : (e.status === 'failed' ? 'red' : 'gray')}">
            ${esc(e.status)}
          </span>
        </div>

        ${e.failure_reason ? `
          <div style="background:#FEE2E2; border:1px solid #FCA5A5; color:#991B1B; padding:12px; border-radius:8px; font-size:12px;">
            <strong>Motivo da Falha:</strong><br>${esc(e.failure_reason)}
          </div>
        ` : ''}

        <div>
          <strong>Payload Sanitizado Recebido:</strong>
          <pre style="background:#111215; color:#E5E7EB; padding:12px; border-radius:8px; font-size:11px; max-height:220px; overflow-y:auto;">${esc(JSON.stringify(e.sanitized_payload, null, 2))}</pre>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:10px;">
          <button class="btn-master-primary" data-action="master-reprocess-event" data-id="${e.id}">
            ${mIcon('refresh')} Reprocessar Evento Agora
          </button>
        </div>
      </div>
    `, '', '');
  } catch (err) {
    toast('Erro ao buscar detalhes do evento: ' + err.message);
  }
}

let renderVersion = 0;
async function render() {
  const version = ++renderVersion;
  const current = route();
  const active = document.activeElement;
  const focus = active?.id ? { id: active.id, start: active.selectionStart, end: active.selectionEnd } : null;
  const scroll = window.scrollY;
  const app = $('#app');
  app.setAttribute('aria-busy', 'true');
  $('#page-progress')?.removeAttribute('hidden');
  try {
    await renderPage(current, version);
    if (version !== renderVersion || route() !== current) return;
    if (focus) {
      const replacement = document.getElementById(focus.id);
      replacement?.focus({ preventScroll: true });
      if (replacement?.setSelectionRange && focus.start !== null) {
        try { replacement.setSelectionRange(focus.start, focus.end); } catch {}
      }
      window.scrollTo(0, scroll);
    }
    document.querySelectorAll('nav a[href]').forEach(a => {
      if (a.classList.contains('active')) a.setAttribute('aria-current', 'page');
    });
    document.querySelectorAll('.table-wrap, .master-table-wrapper').forEach(table => {
      table.setAttribute('tabindex', '0');
      table.setAttribute('role', 'region');
      table.setAttribute('aria-label', 'Tabela de registros. Deslize horizontalmente para ver todas as colunas.');
    });
    syncMenuState();
  } catch (error) {
    if (version !== renderVersion || route() !== current) return;
    if (error.status === 401) { go('login'); return; }
    const body = errorPanel(error);
    app.innerHTML = S.session ? (current.startsWith('master') || current.startsWith('admin') ? master.renderShell(body, current) : shell(body, current)) : body;
  } finally {
    if (version === renderVersion) {
      app.removeAttribute('aria-busy');
      $('#page-progress')?.setAttribute('hidden', '');
      syncMenuState();
    }
  }
}

async function renderPage(r, version) {
  if (['login', 'recovery'].includes(r)) {
    document.body.classList.remove('master-active');
    document.title = 'VTD · Acesso';
    $('#app').innerHTML = login(r === 'recovery');
    return;
  }

  if (!S.session) {
    await loadInitialData();
    if (version !== renderVersion || route() !== r) return;
    if (!S.session) {
      go('login');
      return;
    }
  }

  // Despacha rotas do Painel Master
  if (r.startsWith('master') || r.startsWith('admin')) {
    if (S.role !== 'admin') {
      toast('Acesso restrito ao Painel Master.');
      go('home');
      return;
    }
    document.body.classList.add('master-active');
    let masterRoute = r;
    if (r === 'admin' || r === 'admin/overview') masterRoute = 'master/overview';
    else if (r === 'admin/clients') masterRoute = 'master/clients';
    else if (r === 'admin/content') masterRoute = 'master/guide';
    else if (r.startsWith('admin/')) masterRoute = r.replace(/^admin\//, 'master/');
    const body = await renderMasterRoute(masterRoute);
    if (version !== renderVersion || route() !== r) return;
    document.title = 'VTD · Painel Master';
    $('#app').innerHTML = master.renderShell(body, masterRoute);
    window.scrollTo(0, 0);
    return;
  }

  document.body.classList.remove('master-active');

  if (r.startsWith('guide/')) {
    const n = Number(r.split('/')[1]);
    if (n >= 1 && n <= 8) state.chapter = n - 1;
  }

  const base = r.split('/')[0];
  if (['suppliers', 'favorites'].includes(base)) {
    if (supplierContext !== base) { state.page = 1; supplierContext = base; }
    await loadSuppliers();
  }
  if (base === 'finance') {
    await loadFinanceData();
  }
  if (version !== renderVersion || route() !== r) return;

  const body = base === 'home' ? home() :
    base === 'suppliers' ? suppliers() :
    base === 'favorites' ? suppliers(true) :
    base === 'calendar' ? calendar() :
    base === 'guide' ? guide() :
    base === 'finance' ? finance(r) :
    base === 'account' ? account() :
    base === 'help' ? help() :
    `<div class="empty"><h1>Página não encontrada</h1><p>Escolha uma seção no menu para continuar.</p>${link('Voltar ao início', 'home', 'btn primary')}</div>`;

  document.title = 'VTD · ' + (nav.find(n => n[0] === base)?.[1] || ({ account: 'Minha conta', help: 'Ajuda' }[base]) || 'Página');
  $('#app').innerHTML = shell(body, r);
  window.scrollTo(0, 0);
}

window.addEventListener('hashchange', () => {
  if (location.hash === '#main') { $('#main')?.focus(); return; }
  if ($('#modal')?.open) $('#modal').close();
  render();
});
window.matchMedia?.('(max-width: 760px)').addEventListener('change', syncMenuState);
window.matchMedia?.('(max-width: 768px)').addEventListener('change', syncMenuState);

document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && $('.nav-backdrop') && !$('.nav-backdrop').hidden) $('.nav-backdrop').click();
});

document.addEventListener('input', async e => {
  if (e.target.id === 'chapter-note') {
    noteDrafts[state.chapter + 1] = e.target.value;
    if ($('#note-status')) $('#note-status').textContent = 'Rascunho ainda não salvo.';
  }
  if (e.target.id === 'supplier-search') {
    state.search = e.target.value;
    state.page = 1;
    ++supplierRequestId;
    clearTimeout(supplierTimer);
    supplierTimer = setTimeout(refreshSupplierResults, 300);
  }

  if (e.target.id === 'tx-search') {
    state.txSearch = e.target.value;
    ++financeRequestId;
    clearTimeout(financeSearchTimer);
    financeSearchTimer = setTimeout(render, 300);
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

  // Master: Busca rápida de clientes na Visão Geral
  if (e.target.id === 'master-quick-search') {
    filterRecentClients();
  }

  // Master: Busca com debounce na tabela completa de Clientes
  if (e.target.id === 'master-clients-search') {
    masterState.clientSearch = e.target.value;
    masterState.clientPage = 1;
    clearTimeout(masterState._searchTimer);
    masterState._searchTimer = setTimeout(() => {
      render();
    }, 350);
  }

  // Master: Busca de Eventos
  if (e.target.id === 'master-events-search') {
    masterState.eventSearch = e.target.value;
    masterState.eventPage = 1;
    clearTimeout(masterState._evSearchTimer);
    masterState._evSearchTimer = setTimeout(() => {
      render();
    }, 350);
  }
});

document.addEventListener('change', async e => {
  const t = e.target;
  if (t.dataset.check) {
    const previous = !!S.checks[t.dataset.check];
    t.disabled = true;
    try {
      await api.post('/checklists', { key: t.dataset.check, checked: t.checked });
      S.checks[t.dataset.check] = t.checked;
    } catch (error) {
      t.checked = previous;
      toast('Não foi possível salvar o checklist. Tente novamente.');
    } finally { t.disabled = false; }
    if (route() === 'calendar') {
      const m = S.months[state.month] || { items: [] };
      const done = (m.items || []).filter((_, i) => S.checks[`m-${state.month}-${i}`]).length;
      $('#month-progress').textContent = `${done} de ${(m.items || []).length} itens concluídos`;
      $('#month-bar').style.width = ((m.items?.length ? done / m.items.length : 0) * 100) + '%';
    }
  }

  if (t.id === 'chapter-done') {
    const chNum = state.chapter + 1;
    const previous = S.read.includes(chNum);
    t.disabled = true;
    try {
      await api.post('/reading-progress', { chapter_id: chNum, completed: t.checked });
      if (t.checked) S.read = [...new Set([...S.read, chNum])];
      else S.read = S.read.filter(x => x !== chNum);
      toast(t.checked ? 'Capítulo concluído.' : 'Capítulo reaberto.');
    } catch (error) {
      t.checked = previous;
      toast('Não foi possível salvar seu progresso. Tente novamente.');
    } finally { t.disabled = false; }
  }

  if (t.id === 'supplier-segment') {
    state.segment = t.value;
    state.page = 1;
    clearTimeout(supplierTimer);
    await refreshSupplierResults();
  }

  if (t.id === 'finance-month') {
    if (t.value) {
      state.financeMonth = t.value;
      render();
    }
  }

  if (t.id === 'tx-status') {
    state.txStatus = t.value;
    render();
  }

  // Master: Filtros rápidos da Visão Geral
  if (t.id === 'master-quick-offer' || t.id === 'master-quick-status') {
    filterRecentClients();
  }

  // Master: Filtros na tela de Clientes
  if (t.id === 'master-clients-filter-offer') {
    masterState.clientOffer = t.value;
    masterState.clientPage = 1;
    render();
  }

  if (t.id === 'master-clients-filter-status') {
    masterState.clientStatus = t.value;
    masterState.clientPage = 1;
    render();
  }

  // Master: Filtro de Eventos
  if (t.id === 'master-events-filter-status') {
    masterState.eventStatus = t.value;
    masterState.eventPage = 1;
    render();
  }
});

document.addEventListener('click', async e => {
  if (e.target.closest('.skip-link')) {
    e.preventDefault();
    $('#main')?.focus();
    return;
  }
  const el = e.target.closest('[data-action]');
  if (!el) return;
  const a = el.dataset.action;
  const id = el.dataset.id;
  const index = Number(el.dataset.index);
  if (el.dataset.pending) return;
  el.dataset.pending = 'true';
  const wasDisabled = el.disabled;
  el.disabled = true;
  try {

  if (a === 'toggle-password') {
    const input = document.getElementById(el.getAttribute('aria-controls'));
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    el.textContent = visible ? 'Ocultar' : 'Mostrar';
    el.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha');
    return;
  }

  if (a === 'close') {
    $('#modal').close();
    return;
  }
  if (a === 'menu') {
    const open = $('#sidebar').classList.toggle('open');
    el.setAttribute('aria-expanded', String(open));
    el.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
    $('.nav-backdrop').hidden = !open;
    syncMenuState();
    return;
  }
  if (a === 'retry-page') { render(); return; }
  if (a === 'close-menu') {
    document.querySelectorAll('.sidebar.open, .master-sidebar.open').forEach(side => side.classList.remove('open'));
    document.querySelectorAll('[aria-controls="sidebar"], [aria-controls="master-sidebar"]').forEach(toggle => {
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', toggle.getAttribute('aria-controls') === 'sidebar' ? 'Abrir menu' : 'Abrir menu administrativo');
      toggle.focus();
    });
    $('.nav-backdrop').hidden = true;
    syncMenuState();
    return;
  }
  if (a === 'master-menu') {
    const open = $('#master-sidebar').classList.toggle('open');
    el.setAttribute('aria-expanded', String(open));
    el.setAttribute('aria-label', open ? 'Fechar menu administrativo' : 'Abrir menu administrativo');
    const backdrop = $('.nav-backdrop');
    if (backdrop) backdrop.hidden = !open;
    syncMenuState();
    return;
  }
  if (a === 'logout') {
    try { await api.post('/auth/logout'); } catch (error) { toast(error.message); return; }
    S.session = false;
    api.csrfToken = '';
    S.favs = []; S.notes = {}; S.transactions = [];
    Object.keys(noteDrafts).forEach(key => delete noteDrafts[key]);
    go('login');
    return;
  }
  if (a === 'go-suppliers') {
    state.search = '';
    state.segment = '';
    state.wa = state.cat = false;
    state.page = 1;
    go('suppliers');
    return;
  }
  if (a === 'favorite') {
    const isFav = S.favs.includes(id);
    el.disabled = true;
    try {
      await api.post('/favorites', { supplier_id: id, status: !isFav });
      S.favs = isFav ? S.favs.filter(x => x !== id) : [...S.favs, id];
      if (route() === 'favorites') await refreshSupplierResults();
      else if ($('#supplier-results')) $('#supplier-results').innerHTML = supplierList();
      toast(isFav ? 'Fornecedor removido dos favoritos.' : 'Fornecedor salvo nos favoritos.');
    } catch (error) { toast('Não foi possível atualizar seus favoritos. Tente novamente.'); }
    finally { el.disabled = false; }
    return;
  }
  if (a === 'supplier-detail') {
    supplierDetail(id);
    return;
  }
  if (a === 'filter-wa' || a === 'filter-cat') {
    state[a === 'filter-wa' ? 'wa' : 'cat'] = !state[a === 'filter-wa' ? 'wa' : 'cat'];
    state.page = 1;
    render();
    return;
  }
  if (a === 'clear-filters') {
    state.search = '';
    state.segment = '';
    state.wa = state.cat = false;
    state.page = 1;
    render();
    return;
  }
  if (a === 'page') {
    state.page = Math.max(1, Math.min(state.totalPages, Number(el.dataset.page)));
    await refreshSupplierResults();
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
    const chapter = state.chapter + 1;
    el.disabled = true;
    try {
      await api.post('/notes', { chapter_id: chapter, note: noteVal });
      S.notes[chapter] = noteVal;
      delete noteDrafts[chapter];
      if ($('#note-status') && state.chapter + 1 === chapter) $('#note-status').textContent = 'Anotação salva.';
      toast('Anotação salva com sucesso.');
    } catch (error) { toast('Não foi possível salvar a anotação. Seu texto continua aqui para tentar novamente.'); }
    finally { el.disabled = false; }
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
        <textarea id="chapter-editor" name="body" style="min-height:260px">${esc(chapterText(c.body))}</textarea>
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

  // --- AÇÕES DO PAINEL MASTER ---
  if (a === 'master-new-client') {
    masterClientModal();
    return;
  }
  if (a === 'master-manage-client') {
    masterClientDetailModal(id);
    return;
  }
  if (a === 'master-toggle-client') {
    try {
      const res = await api.patch('/master/clients/' + id + '/toggle');
      toast('Status do cliente alterado para ' + (res.status === 'active' ? 'Liberado' : 'Suspenso'));
      if ($('#modal').open) await masterClientDetailModal(id);
      else await render();
    } catch (err) {
      toast(err.message);
    }
    return;
  }
  if (a === 'master-resend-invite') {
    try {
      const res = await api.post('/master/clients/' + id + '/resend-invite');
      toast(res.message || 'Convite reenviado com sucesso.');
    } catch (err) {
      toast(err.message);
    }
    return;
  }
  if (a === 'master-revoke-access') {
    const tenantId = el.dataset.tenant;
    const accessId = el.dataset.access;
    if (confirm('Deseja realmente revogar este produto específico? Se o cliente possuir outros produtos ativos, eles serão mantidos.')) {
      try {
        await api.delete('/master/clients/' + tenantId + '/accesses/' + accessId);
        toast('Produto revogado com sucesso.');
        $('#modal').close();
        render();
      } catch (err) {
        toast(err.message);
      }
    }
    return;
  }
  if (a === 'master-new-integration') {
    masterIntegrationModal();
    return;
  }
  if (a === 'master-configure-integration' || a === 'master-edit-integration') {
    try {
      let integ = masterState.integrations.find(x => String(x.id) === String(id));
      if (!integ) {
        const list = await api.get('/master/integrations');
        masterState.integrations = list;
        integ = list.find(x => String(x.id) === String(id));
      }
      masterIntegrationModal(integ || null);
    } catch (err) {
      toast(err.message);
    }
    return;
  }
  if (a === 'master-toggle-integration') {
    try {
      await api.patch('/master/integrations/' + id + '/toggle');
      toast('Status da integração atualizado com sucesso.');
      render();
    } catch (err) {
      toast(err.message);
    }
    return;
  }
  if (a === 'master-copy-webhook-url') {
    const url = el.dataset.url;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(() => {
        toast('URL do webhook copiada para a área de transferência!');
      }).catch(() => {
        prompt('Copie a URL abaixo:', url);
      });
    } else {
      prompt('Copie a URL abaixo:', url);
    }
    return;
  }
  if (a === 'master-goto-failed-events') {
    masterState.eventStatus = 'failed';
    go('master/events');
    return;
  }
  if (a === 'master-review-event') {
    masterReviewEventModal(id);
    return;
  }
  if (a === 'master-reprocess-event') {
    try {
      const res = await api.post('/master/events/' + id + '/reprocess');
      toast(res.message || 'Evento reprocessado com sucesso!');
      $('#modal').close();
      render();
    } catch (err) {
      toast(err.message);
    }
    return;
  }
  if (a === 'master-clients-page') {
    const p = Number(el.dataset.page);
    if (p >= 1 && p <= masterState.clientTotalPages) {
      masterState.clientPage = p;
      render();
    }
    return;
  }
  if (a === 'master-events-page') {
    const p = Number(el.dataset.page);
    if (p >= 1 && p <= masterState.eventTotalPages) {
      masterState.eventPage = p;
      render();
    }
    return;
  }
  } catch (error) {
    toast(error.message || 'Não foi possível concluir a ação. Tente novamente.');
  } finally {
    delete el.dataset.pending;
    el.disabled = wasDisabled;
  }
});

document.addEventListener('submit', async e => {
  const form = e.target.closest('[data-form]');
  if (!form) return;
  e.preventDefault();
  if (form.dataset.submitting) return;
  form.querySelector('.form-feedback')?.remove();
  form.dataset.submitting = 'true';
  form.setAttribute('aria-busy', 'true');
  const submitButtons = [...form.querySelectorAll('button[type="submit"]')];
  const labels = submitButtons.map(button => button.innerHTML);
  submitButtons.forEach(button => { button.disabled = true; button.textContent = 'Aguarde…'; });
  try {
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
      if (res.user.role === 'admin') {
        go('master/overview');
      } else {
        go('home');
      }
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
    if (d.new_password.length < 6 || d.new_password !== d.confirm_password) {
      formError(form, 'Informe uma senha com pelo menos 6 caracteres e confirme a mesma senha.');
      return;
    }
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
      formError(form, 'Informe um saldo válido.');
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
      formError(form, 'Preencha uma descrição e um valor maior que zero.');
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
      const payload = {
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
      };
      if (d.id) await api.put('/admin/suppliers/' + d.id, payload);
      else await api.post('/admin/suppliers', payload);
      $('#modal').close();
      const res = await api.get('/suppliers', { search: state.search, page: state.page, limit: 12 });
      S.suppliers = res.suppliers;
      state.totalSuppliers = res.total;
      state.totalPages = res.total_pages;
      render();
      toast(d.id ? 'Fornecedor atualizado com sucesso.' : 'Fornecedor cadastrado com sucesso.');
    } catch (err) {
      toast(err.message);
    }
    return;
  }

  if (kind === 'chapter') {
    try {
      await api.put('/admin/chapters/' + d.index, {
        title: d.title.trim(),
        body: d.body === chapterText(S.chapters[Number(d.index)].body) ? S.chapters[Number(d.index)].body : d.body.split(/\n\s*\n/).map(p => '<p>' + esc(p) + '</p>').join(''),
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
      formError(form, 'Adicione pelo menos um produto ao mês.');
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

  if (kind === 'master-client') {
    try {
      const res = await api.post('/master/clients', {
        name: d.name.trim(),
        email: d.email.trim(),
        store: d.store.trim(),
        offer: d.offer,
        status: d.status,
        expires_at: d.expires_at || null,
        send_invite: form.querySelector('[name="send_invite"]')?.checked ? true : false
      });
      $('#modal').close();
      const inviteMsg = res.invite_message ? ' ' + res.invite_message : '';
      toast((res.is_existing ? 'Oferta vinculada à conta existente!' : 'Cliente cadastrado com sucesso!') + inviteMsg);
      render();
    } catch (err) {
      toast(err.message);
    }
    return;
  }

  if (kind === 'master-integration') {
    const lines = (d.offer_mappings_text || '').split('\n');
    const mappings = Object.create(null);
    for (const line of lines) {
      if (!line.trim()) continue;
      const parts = line.split('=');
      if (parts.length === 2) {
        const k = parts[0].trim();
        const v = parts[1].trim();
        if (!k || !['kit-plus', 'kit-essencial'].includes(v) || Object.hasOwn(mappings, k)) {
          formError(form, 'Cada código externo deve aparecer uma vez e apontar para kit-plus ou kit-essencial.');
          return;
        }
        mappings[k] = v;
      } else { formError(form, 'Use uma oferta por linha: codigo_externo = kit-plus ou kit-essencial.'); return; }
    }
    const payload = {
      name: d.name.trim(),
      provider: d.provider,
      environment: d.environment,
      status: d.status,
      offer_mappings: mappings
    };
    if (d.secret_key?.trim()) payload.secret_key = d.secret_key.trim();
    try {
      if (d.id) {
        await api.put('/master/integrations/' + d.id, payload);
        toast('Integração atualizada com sucesso!');
      } else {
        await api.post('/master/integrations', payload);
        toast('Nova integração cadastrada com sucesso!');
      }
      $('#modal').close();
      render();
    } catch (err) {
      toast(err.message);
    }
    return;
  }

  if (kind === 'master-settings') {
    try {
      await api.post('/master/settings', {
        support_email: d.support_email.trim(),
        support_whatsapp: d.support_whatsapp.trim()
      });
      toast('Configurações do Master salvas com sucesso!');
      render();
    } catch (err) {
      toast(err.message);
    }
    return;
  }
  } catch (error) {
    formError(form, error.message || 'Não foi possível concluir a ação.');
  } finally {
    delete form.dataset.submitting;
    form.removeAttribute('aria-busy');
    submitButtons.forEach((button, i) => { button.disabled = false; button.innerHTML = labels[i]; });
  }
});

// Inicialização da aplicação
render();
