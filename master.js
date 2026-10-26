'use strict';

/**
 * PAINEL MASTER DO VTD — VENDA TODO DIA
 * Central de operações com métricas em tempo real, gestão de clientes,
 * catálogo de produtos, integrações de checkout e auditoria de webhooks.
 */

const masterState = {
  activeTab: 'overview',
  overview: null,
  clients: [],
  clientTotal: 0,
  clientPage: 1,
  clientLimit: 15,
  clientTotalPages: 1,
  clientSearch: '',
  clientOffer: '',
  clientStatus: '',
  products: [],
  integrations: [],
  events: [],
  eventTotal: 0,
  eventPage: 1,
  eventLimit: 20,
  eventTotalPages: 1,
  eventStatus: '',
  eventIntegrationId: '',
  eventSearch: '',
  settings: null,
  isLoading: false
};

const masterIcons = {
  home: 'M3 10 12 3l9 7v11h-6v-7H9v7H3z',
  users: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2 M9 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M23 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
  package: 'M16.5 9.4 7.55 4.24 M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z M3.27 6.96 12 12.01l8.73-5.05 M12 22.08V12',
  link: 'M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71 M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71',
  list: 'M8 6h13 M8 12h13 M8 18h13 M3 6h.01 M3 12h.01 M3 18h.01',
  truck: 'M3 6h11v12H3z M14 10h4l3 4v4h-7 M7 18a2 2 0 1 0 .01 0 M17 18a2 2 0 1 0 .01 0',
  calendar: 'M4 5h16v16H4z M4 10h16 M8 3v4 M16 3v4',
  book: 'M3 4c4-1 7 0 9 2 2-2 5-3 9-2v16c-4-1-7 0-9 2-2-2-5-3-9-2z M12 6v16',
  settings: 'M12 8a4 4 0 1 0 .01 0 M12 2v3 M12 19v3 M2 12h3 M19 12h3 M5 5l2 2 M17 17l2 2 M5 19l2-2 M17 7l2-2',
  external: 'M14 3h7v7 M21 3 11 13 M10 3H3v18h18v-7',
  logout: 'M10 3H4v18h6 M9 12h13 M17 7l5 5-5 5',
  alert: 'M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z M12 9v4 M12 17h.01',
  copy: 'M20 9h-9a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2z M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1',
  refresh: 'M21.5 2v6h-6 M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67',
  dots: 'M12 12h.01 M19 12h.01 M5 12h.01',
  check: 'm5 12 5 5L20 7',
  plus: 'M12 4v16 M4 12h16',
  search: 'M10 3a7 7 0 1 0 .01 0 M15 15l6 6'
};

const mIcon = n => `<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="${masterIcons[n] || masterIcons.home}"/></svg>`;

const master = {
  async init() {
    // Carrega dados iniciais do overview
    try {
      const data = await api.get('/master/overview');
      masterState.overview = data;
    } catch (e) {
      console.error('Erro ao carregar overview do Master:', e);
    }
  },

  /**
   * Shell Principal com Sidebar Preta e Topbar
   */
  renderShell(body, route) {
    const section = route.replace(/^master\/?/, '').split('/')[0] || 'overview';
    
    const menuItems = [
      { id: 'overview', label: 'Visão geral', icon: 'home' },
      { id: 'clients', label: 'Clientes', icon: 'users' },
      { id: 'products', label: 'Produtos e acessos', icon: 'package' },
      { id: 'integrations', label: 'Integrações', icon: 'link' },
      { id: 'events', label: 'Eventos e logs', icon: 'list' }
    ];

    const contentItems = [
      { id: 'suppliers', label: 'Fornecedores', icon: 'truck' },
      { id: 'calendar', label: 'Calendário', icon: 'calendar' },
      { id: 'guide', label: 'Guia do Matheus', icon: 'book' }
    ];

    const currentTitle = {
      overview: 'Visão geral',
      clients: 'Clientes',
      products: 'Produtos e acessos',
      integrations: 'Integrações',
      events: 'Eventos e logs',
      suppliers: 'Fornecedores',
      calendar: 'Calendário comercial',
      guide: 'Guia do Matheus',
      settings: 'Configurações'
    }[section] || 'Painel Master';

    return `
      <aside class="master-sidebar" id="master-sidebar">
        <a class="master-logo" href="#/master/overview" aria-label="VTD Venda Todo Dia, Painel Master">
          ${brandImage()}
          <span class="master-brand-caption">PAINEL ADMINISTRATIVO</span>
        </a>

        <nav class="master-nav" aria-label="Navegação Master">
          ${menuItems.map(item => `
            <a href="#/master/${item.id}" class="${section === item.id ? 'active' : ''}">
              ${mIcon(item.icon)}
              <span>${item.label}</span>
            </a>
          `).join('')}

          <div class="master-nav-label">CONTEÚDOS</div>
          ${contentItems.map(item => `
            <a href="#/master/${item.id}" class="${section === item.id ? 'active' : ''}">
              ${mIcon(item.icon)}
              <span>${item.label}</span>
            </a>
          `).join('')}

          <div class="master-nav-label">CONFIGURAÇÕES</div>
          <a href="#/master/settings" class="${section === 'settings' ? 'active' : ''}">
            ${mIcon('settings')}
            <span>Configurações</span>
          </a>
          <a href="#/home">
            ${mIcon('external')}
            <span>Ver área do cliente</span>
          </a>
          <button data-action="logout">
            ${mIcon('logout')}
            <span>Sair</span>
          </button>
        </nav>
      </aside>

      <div class="master-shell">
        <header class="master-topbar">
          <button class="master-menu-toggle" data-action="master-menu" aria-controls="master-sidebar" aria-expanded="false" aria-label="Abrir menu administrativo">☰</button>
          <div class="master-crumb">
            ${mIcon('home')}
            <span>Master</span>
            <span>/</span>
            <span class="crumb-active">${currentTitle}</span>
          </div>
          <div class="master-top-right">
            <span class="master-badge-status">
              <span class="status-dot active"></span>
              Administração
            </span>
            <a href="#/account" class="master-avatar-btn" aria-label="Minha conta">${esc((S.name || 'Admin').split(' ').filter(Boolean).map(n => n[0]).slice(0, 2).join(''))}</a>
          </div>
        </header>

        <main class="master-content" id="main" tabindex="-1">
          ${body}
        </main>
      </div>
      <button class="nav-backdrop" data-action="close-menu" aria-label="Fechar menu administrativo" hidden></button>
    `;
  },

  /**
   * 1. VISÃO GERAL (TELA PRINCIPAL CONFORME MOCKUP ANEXADO)
   */
  renderOverview() {
    const data = masterState.overview || {
      metrics: { total_clients: 0, active_accesses: 0, active_integrations: 0, failed_events: 0 },
      recent_clients: [],
      integrations: [],
      recent_events: []
    };

    const m = data.metrics;

    return `
      <section class="master-welcome">
        <span class="master-welcome-eyebrow">PAINEL MASTER · VTD COMPLETO</span>
        <div class="master-header">
        <div>
          <h1>Bem-vindo de volta, ${esc((S.name || 'administrador').trim().split(/\s+/)[0])}! 👋</h1>
          <p>Sua operação em um só lugar. Acompanhe clientes, acessos e integrações do VTD.</p>
        </div>
        <div class="master-header-actions">
          <button class="btn-master-primary" data-action="master-new-client">
            ${mIcon('plus')} Criar cliente
          </button>
          <button class="btn-master-outline" data-action="master-new-integration">
            ${mIcon('link')} Nova integração
          </button>
        </div>
      </div>

      </section>

      <!-- 4 Cards de Métricas Reais -->
      <div class="master-metrics-grid">
        <div class="master-metric-card">
          <div class="master-metric-icon icon-yellow">
            ${mIcon('users')}
          </div>
          <div class="master-metric-info">
            <span>Clientes cadastrados</span>
            <strong>${m.total_clients}</strong>
          </div>
        </div>

        <div class="master-metric-card">
          <div class="master-metric-icon icon-green">
            ${mIcon('check')}
          </div>
          <div class="master-metric-info">
            <span>Acessos ativos</span>
            <strong>${m.active_accesses}</strong>
          </div>
        </div>

        <div class="master-metric-card">
          <div class="master-metric-icon icon-blue">
            ${mIcon('link')}
          </div>
          <div class="master-metric-info">
            <span>Integrações ativas</span>
            <strong>${m.active_integrations}</strong>
          </div>
        </div>

        <div class="master-metric-card">
          <div class="master-metric-icon icon-amber">
            ${mIcon('alert')}
          </div>
          <div class="master-metric-info">
            <span>Eventos com falha</span>
            <div class="master-metric-val-row">
              <strong>${m.failed_events}</strong>
              ${m.failed_events > 0 ? `<button class="btn-pill-review" data-action="master-goto-failed-events">Revisar</button>` : ''}
            </div>
          </div>
        </div>
      </div>

      <!-- Grid Principal: Clientes Recentes (2/3) + Integrações (1/3) -->
      <div class="master-main-grid">
        <!-- Card Clientes Recentes -->
        <section class="master-card">
          <div class="master-card-header">
            <h2>Clientes recentes</h2>
            <div class="master-filters-row">
              <div class="master-input-search">
                <span class="search-icon">${mIcon('search')}</span>
                <input type="search" id="master-quick-search" aria-label="Buscar clientes recentes" placeholder="Buscar por nome ou e-mail">
              </div>
              <select class="master-select-filter" id="master-quick-offer" aria-label="Oferta dos clientes recentes">
                <option value="">Todas as ofertas</option>
                <option value="plus">Kit + Financeiro</option>
                <option value="essential">Kit Essencial</option>
              </select>
              <select class="master-select-filter" id="master-quick-status" aria-label="Status dos clientes recentes">
                <option value="">Todos os status</option>
                <option value="active">Liberado</option>
                <option value="suspended">Suspenso</option>
              </select>
            </div>
          </div>

          <div class="master-table-wrapper">
            <table class="master-table">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Oferta</th>
                  <th>Acesso</th>
                  <th>Origem</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody id="master-recent-clients-tbody">
                ${data.recent_clients.map(c => `
                  <tr>
                    <td>
                      <div class="user-cell">
                        <div class="user-avatar-circle">${esc(c.initials)}</div>
                        <div>
                          <div class="user-meta-name">${esc(c.name)}</div>
                          <div class="user-meta-email">${esc(c.email)}</div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span class="badge-offer ${c.plan_code === 'plus' ? 'plus' : 'essential'}">
                        ${esc(c.offer)}
                      </span>
                    </td>
                    <td>
                      <span class="badge-status-pill ${c.status === 'active' ? 'green' : 'red'}">
                        ${esc(c.access)}
                      </span>
                    </td>
                    <td>${esc(c.origin)}</td>
                    <td>
                      <button class="btn-table-action" data-action="master-manage-client" data-id="${c.tenant_id}">
                        Gerenciar
                      </button>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>

          <div class="master-card-footer">
            <a href="#/master/clients" class="link-all-clients">
              Ver todos os clientes →
            </a>
          </div>
        </section>

        <!-- Card Integrações e Webhooks -->
        <section class="master-card">
          <div class="master-card-header">
            <h2>${mIcon('link')} Integrações e webhooks</h2>
          </div>

          <div class="master-integ-list">
            ${data.integrations.map(i => `
              <div class="master-integ-item">
                <div class="master-integ-header">
                  <div class="master-integ-title">
                    <span class="status-dot ${i.is_active ? 'active' : 'inactive'}"></span>
                    <span>${esc(i.name)}</span>
                    <span class="badge-status-pill ${i.is_active ? 'green' : 'gray'}">
                      ${i.is_active ? 'Ativo' : 'Inativo'}
                    </span>
                  </div>
                </div>
                <p class="master-integ-desc">${esc(i.summary)}</p>
                <div class="master-integ-time">Último evento: ${esc(i.last_event)}</div>
                <div class="master-integ-actions">
                  <button class="btn-table-action" data-action="master-configure-integration" data-id="${i.id}">
                    ${i.is_active ? 'Configurar' : 'Conectar'}
                  </button>
                </div>
              </div>
            `).join('')}
          </div>
        </section>
      </div>

      <!-- Card Inferior: Últimos Eventos -->
      <section class="master-card">
        <div class="master-card-header">
          <h2>${mIcon('list')} Últimos eventos</h2>
        </div>

        <div class="master-events-table">
          ${data.recent_events.map(e => `
            <div class="event-row">
              <div class="event-time">${esc(e.time)}</div>
              <div class="event-type-col">
                <strong>${esc(e.title)}</strong>
                <small>${esc(e.subtitle)}</small>
              </div>
              <div class="event-desc-col">${esc(e.description)}</div>
              <div>
                <span class="badge-status-pill ${e.status === 'success' ? 'green' : (e.status === 'failed' ? 'amber' : 'gray')}">
                  ${esc(e.badge)}
                </span>
              </div>
              <div class="event-action-col">
                ${e.is_failed ? `
                  <button class="btn-master-outline" style="padding:4px 10px; font-size:11px;" data-action="master-review-event" data-id="${e.id}">
                    Revisar evento
                  </button>
                ` : `
                  <button class="btn-table-action" data-action="master-review-event" data-id="${e.id}">
                    Detalhes
                  </button>
                `}
              </div>
            </div>
          `).join('')}
        </div>
      </section>
    `;
  },

  /**
   * 2. TELA COMPLETA DE CLIENTES COM BUSCA, FILTROS E PAGINAÇÃO
   */
  renderClients() {
    return `
      <div class="master-header">
        <div>
          <h1>Gestão de Clientes</h1>
          <p>Controle de lojas, ofertas liberadas, validades e envio de acessos.</p>
        </div>
        <div class="master-header-actions">
          <button class="btn-master-primary" data-action="master-new-client">
            ${mIcon('plus')} Criar cliente
          </button>
        </div>
      </div>

      <section class="master-card">
        <div class="master-card-header">
          <h2>Base de clientes cadastrados</h2>
          <div class="master-filters-row">
            <div class="master-input-search">
              <span class="search-icon">${mIcon('search')}</span>
              <input type="search" id="master-clients-search" aria-label="Buscar clientes" placeholder="Buscar por nome, email ou loja..." value="${esc(masterState.clientSearch)}">
            </div>
            <select class="master-select-filter" id="master-clients-filter-offer" aria-label="Filtrar clientes por oferta">
              <option value="">Todas as ofertas</option>
              <option value="kit-plus" ${masterState.clientOffer === 'kit-plus' ? 'selected' : ''}>Kit + Financeiro</option>
              <option value="kit-essencial" ${masterState.clientOffer === 'kit-essencial' ? 'selected' : ''}>Kit Essencial</option>
            </select>
            <select class="master-select-filter" id="master-clients-filter-status" aria-label="Filtrar clientes por status">
              <option value="">Todos os status</option>
              <option value="active" ${masterState.clientStatus === 'active' ? 'selected' : ''}>Liberado</option>
              <option value="suspended" ${masterState.clientStatus === 'suspended' ? 'selected' : ''}>Suspenso</option>
            </select>
          </div>
        </div>

        <div class="master-table-wrapper">
          <table class="master-table">
            <thead>
              <tr>
                <th>Cliente</th>
                <th>Loja</th>
                <th>Oferta liberada</th>
                <th>Status</th>
                <th>Origem</th>
                <th>Data</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              ${masterState.clients.length === 0 ? `
                <tr><td colspan="7" style="text-align:center; padding:30px; color:#888;">Nenhum cliente encontrado.</td></tr>
              ` : masterState.clients.map(c => `
                <tr>
                  <td>
                    <div class="user-cell">
                      <div class="user-avatar-circle">${esc(c.initials)}</div>
                      <div>
                        <div class="user-meta-name">${esc(c.name)}</div>
                        <div class="user-meta-email">${esc(c.email)}</div>
                      </div>
                    </div>
                  </td>
                  <td><b>${esc(c.store)}</b></td>
                  <td>
                    <span class="badge-offer ${c.plan === 'plus' ? 'plus' : 'essential'}">
                      ${esc(c.offer_name)}
                    </span>
                  </td>
                  <td>
                    <span class="badge-status-pill ${c.status === 'active' ? 'green' : 'red'}">
                      ${esc(c.access_label)}
                    </span>
                  </td>
                  <td>${esc(c.origin)}</td>
                  <td><small class="muted">${c.created_at ? day(c.created_at.slice(0, 10)) : '—'}</small></td>
                  <td>
                    <div style="display:flex; gap:6px;">
                      <button class="btn-table-action" data-action="master-manage-client" data-id="${c.tenant_id}">
                        Ficha
                      </button>
                      <button class="btn-table-action" data-action="master-toggle-client" data-id="${c.tenant_id}">
                        ${c.status === 'active' ? 'Suspender' : 'Reativar'}
                      </button>
                      <button class="btn-table-action" data-action="master-resend-invite" data-id="${c.tenant_id}" title="Reenviar convite por e-mail">
                        Reenviar
                      </button>
                    </div>
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>

        <div class="pagination" style="display:flex; justify-content:space-between; align-items:center; margin-top:20px;">
          <span class="muted small">Total: ${masterState.clientTotal} ${masterState.clientTotal === 1 ? 'cliente' : 'clientes'} (Página ${masterState.clientPage} de ${masterState.clientTotalPages})</span>
          <div style="display:flex; gap:8px;">
            <button class="btn-table-action" data-action="master-clients-page" data-page="${masterState.clientPage - 1}" ${masterState.clientPage <= 1 ? 'disabled' : ''}>Anterior</button>
            <button class="btn-table-action" data-action="master-clients-page" data-page="${masterState.clientPage + 1}" ${masterState.clientPage >= masterState.clientTotalPages ? 'disabled' : ''}>Próxima</button>
          </div>
        </div>
      </section>
    `;
  },

  /**
   * 3. PRODUTOS E ACESSOS
   */
  renderProducts() {
    const labels = { suppliers: 'Catálogo de fornecedores', favorites: 'Fornecedores favoritos', calendar: 'Calendário e checklists', guide: 'Guia, progresso e anotações', finance: 'Controle financeiro e exportação CSV' };
    return `<div class="master-header"><div><h1>Produtos e acessos</h1><p>Consulte as ofertas cadastradas e os acessos de cada produto.</p></div></div>
      <div class="master-products-grid">${masterState.products.length ? masterState.products.map(product => {
        let features = product.features || [];
        if (typeof features === 'string') { try { features = JSON.parse(features); } catch { features = []; } }
        if (!Array.isArray(features)) features = [];
        const active = Number(product.is_active) === 1;
        return `<section class="master-card">
          <div class="panel-head"><span class="badge-offer ${product.plan_level === 'plus' ? 'plus' : 'essential'}">${product.plan_level === 'plus' ? 'Completo' : 'Essencial'}</span><span class="badge-status-pill ${active ? 'green' : 'gray'}">${active ? 'Ativo' : 'Inativo'}</span></div>
          <h2>${esc(product.name)}</h2><p class="muted">${esc(product.description || '')}</p>
          <ul class="product-features">${features.map(feature => `<li>${esc(labels[feature] || feature)}</li>`).join('')}</ul>
          <p><strong>${Number(product.active_clients) || 0}</strong> lojas com acesso ativo</p>
        </section>`;
      }).join('') : '<section class="empty"><h2>Nenhum produto cadastrado</h2><p>Os produtos aparecerão aqui quando estiverem disponíveis.</p></section>'}</div>`;
  },
  /**
   * 4. INTEGRAÇÕES E WEBHOOKS
   */
  renderIntegrations() {
    return `
      <div class="master-header">
        <div>
          <h1>Integrações & Webhooks</h1>
          <p>Conecte seus checkouts (Greenn, Kiwify, etc.) e configure o provisionamento automático.</p>
        </div>
        <div class="master-header-actions">
          <button class="btn-master-primary" data-action="master-new-integration">
            ${mIcon('plus')} Nova integração
          </button>
        </div>
      </div>

      <div class="master-integ-list">
        ${masterState.integrations.length ? '' : '<section class="empty"><h2>Nenhuma integração cadastrada</h2><p>Use Nova integração para configurar seu checkout.</p></section>'}
        ${masterState.integrations.map(i => `
          <section class="master-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
              <div>
                <div style="display:flex; align-items:center; gap:10px;">
                  <span class="status-dot ${i.status === 'active' ? 'active' : 'inactive'}"></span>
                  <h2 style="font-size:20px; margin:0;">${esc(i.name)}</h2>
                  <span class="badge-status-pill ${i.status === 'active' ? 'green' : 'gray'}">
                    ${i.status === 'active' ? 'Ativo' : 'Inativo'}
                  </span>
                  <span class="badge-offer essential" style="text-transform:uppercase;">${esc(i.provider)}</span>
                </div>
                <p class="muted" style="margin-top:6px;">Ambiente: <b>${i.environment === 'sandbox' ? 'Testes' : 'Produção'}</b> | Eventos habilitados: ${Array.isArray(i.enabled_events) ? esc(i.enabled_events.join(', ')) : 'Padrão'}</p>
              </div>
              <div style="display:flex; gap:8px;">
                <button class="btn-table-action" data-action="master-edit-integration" data-id="${i.id}">
                  Configurar
                </button>
                <button class="btn-table-action" data-action="master-toggle-integration" data-id="${i.id}">
                  ${i.status === 'active' ? 'Desativar' : 'Ativar'}
                </button>
              </div>
            </div>

            <!-- URL de Recebimento com Botão Copiar -->
            <div style="background:#F3F4F6; border-radius:8px; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px;">
              <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-family:monospace; font-size:12px; color:#1F2937;">
                ${esc(i.webhook_url)}
              </div>
              <button class="btn-table-action" style="flex:none; background:#FFFFFF;" data-action="master-copy-webhook-url" data-url="${esc(i.webhook_url)}">
                ${mIcon('copy')} Copiar URL
              </button>
            </div>

            <div>
              <strong>Mapeamento de Ofertas:</strong>
              <div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
                ${Object.entries(i.offer_mappings || {}).map(([ext, intern]) => `
                  <span class="badge-status-pill ${intern === 'kit-plus' ? 'amber' : 'gray'}" style="font-family:monospace;">
                    Código Externo: <b>${esc(ext)}</b> → ${intern === 'kit-plus' ? 'Kit + Financeiro' : 'Kit Essencial'}
                  </span>
                `).join('')}
              </div>
            </div>
          </section>
        `).join('')}
      </div>
    `;
  },

  /**
   * 5. EVENTOS E LOGS
   */
  renderEvents() {
    return `
      <div class="master-header">
        <div>
          <h1>Eventos e Logs de Webhook</h1>
          <p>Auditoria detalhada de todos os eventos recebidos com payload sanitizado e reprocessamento seguro.</p>
        </div>
      </div>

      <section class="master-card">
        <div class="master-card-header">
          <h2>Registro de eventos</h2>
          <div class="master-filters-row">
            <select class="master-select-filter" id="master-events-filter-status" aria-label="Filtrar eventos por status">
              <option value="">Todos os status</option>
              <option value="success" ${masterState.eventStatus === 'success' ? 'selected' : ''}>Sucesso</option>
              <option value="failed" ${masterState.eventStatus === 'failed' ? 'selected' : ''}>Falha / Atenção</option>
              <option value="ignored" ${masterState.eventStatus === 'ignored' ? 'selected' : ''}>Ignorado</option>
            </select>
            <div class="master-input-search">
              <span class="search-icon">${mIcon('search')}</span>
              <input type="search" id="master-events-search" aria-label="Buscar eventos" placeholder="Buscar por pedido ou e-mail..." value="${esc(masterState.eventSearch)}">
            </div>
          </div>
        </div>

        <div class="master-table-wrapper">
          <table class="master-table">
            <thead>
              <tr>
                <th>Data / Hora</th>
                <th>Integração</th>
                <th>Tipo do Evento</th>
                <th>Referência Externa</th>
                <th>Cliente</th>
                <th>Resultado</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              ${masterState.events.length === 0 ? `
                <tr><td colspan="7" style="text-align:center; padding:30px; color:#888;">Nenhum evento registrado.</td></tr>
              ` : masterState.events.map(e => `
                <tr>
                  <td><small class="muted">${esc(e.created_at)}</small></td>
                  <td><b>${esc(e.integration_name || e.provider)}</b></td>
                  <td><span class="badge-offer essential" style="font-family:monospace;">${esc(e.event_type)}</span></td>
                  <td><b>${esc(e.external_reference || '—')}</b></td>
                  <td>${esc(e.client_email || '—')}</td>
                  <td>
                    <span class="badge-status-pill ${e.status === 'success' ? 'green' : (e.status === 'failed' ? 'red' : 'gray')}">
                      ${e.status === 'success' ? 'Sucesso' : (e.status === 'failed' ? 'Falha' : 'Ignorado')}
                    </span>
                    ${e.failure_reason ? `<div style="font-size:10px; color:#DC2626; margin-top:3px; max-width:200px; overflow:hidden; text-overflow:ellipsis;">${esc(e.failure_reason)}</div>` : ''}
                  </td>
                  <td>
                    <div style="display:flex; gap:6px;">
                      <button class="btn-table-action" data-action="master-review-event" data-id="${e.id}">
                        Inspecionar
                      </button>
                      <button class="btn-table-action" data-action="master-reprocess-event" data-id="${e.id}" title="Reprocessar evento com as regras atuais">
                        ${mIcon('refresh')} Reprocessar
                      </button>
                    </div>
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>

        <div class="pagination" style="display:flex; justify-content:space-between; align-items:center; margin-top:20px;">
          <span class="muted small">Total: ${masterState.eventTotal} eventos (Página ${masterState.eventPage} de ${masterState.eventTotalPages})</span>
          <div style="display:flex; gap:8px;">
            <button class="btn-table-action" data-action="master-events-page" data-page="${masterState.eventPage - 1}" ${masterState.eventPage <= 1 ? 'disabled' : ''}>Anterior</button>
            <button class="btn-table-action" data-action="master-events-page" data-page="${masterState.eventPage + 1}" ${masterState.eventPage >= masterState.eventTotalPages ? 'disabled' : ''}>Próxima</button>
          </div>
        </div>
      </section>
    `;
  },

  /**
   * 6. CONFIGURAÇÕES
   */
  renderSettings() {
    const s = masterState.settings || {
      support_email: 'suporte@vtd.com.br',
      support_whatsapp: '11999999999',
      mail_driver: 'smtp',
      mail_host: 'smtp.hostinger.com'
    };

    return `
      <div class="master-header">
        <div>
          <h1>Configurações do Master</h1>
          <p>Canais de atendimento, identidade da plataforma e serviços de e-mail.</p>
        </div>
      </div>

      <section class="master-card" style="max-width:700px;">
        <form data-form="master-settings">
          <div class="field" style="margin-bottom:16px;">
            <label for="s-email">E-mail oficial de suporte / atendimento</label>
            <input id="s-email" name="support_email" type="email" value="${esc(s.support_email)}" required>
          </div>

          <div class="field" style="margin-bottom:16px;">
            <label for="s-wa">WhatsApp de suporte ao cliente (com DDD)</label>
            <input id="s-wa" name="support_whatsapp" type="text" value="${esc(s.support_whatsapp)}" required>
          </div>

          <div style="background:#F9FAFB; border:1px solid #E5E7EB; border-radius:8px; padding:16px; margin:20px 0;">
            <strong>Status do Serviço de E-mail (Hostinger SMTP)</strong>
            <p class="muted small" style="margin-top:4px;">
              Driver: <b>${esc(s.mail_driver)}</b> | Servidor: <b>${esc(s.mail_host)}</b><br>
              As credenciais protegidas estão salvas no arquivo <code>backend/config/.env.php</code> fora da área pública.
            </p>
          </div>

          <button type="submit" class="btn-master-primary">
            Salvar preferências
          </button>
        </form>
      </section>
    `;
  }
};
