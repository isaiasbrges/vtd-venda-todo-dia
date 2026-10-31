/* Financeiro VTD: fluxos guiados, cartões e faturas. Reutiliza o shell e a API. */
var FinanceUI = (() => {
  'use strict';
  let draft = null, step = 0, quick = false, banksCache = null, selectedBank = null;
  const methods = [['cash','Dinheiro'],['pix','Pix'],['debit','Débito'],['transfer','Transferência'],['unspecified','Não informado']];
  const brands = [['visa','Visa'],['mastercard','Mastercard'],['elo','Elo'],['amex','American Express'],['hipercard','Hipercard'],['other','Outra']];
  const key = () => crypto.randomUUID();
  function moneyCents(value) {
    let s=String(value ?? '').trim();
    if (/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/.test(s)) s=s.replace(/\./g,'');
    if (!/^\d+([,.]\d{1,2})?$/.test(s)) return null;
    const [whole,part='']=s.replace(',','.').split('.');
    const cents=Number(whole)*100+Number(part.padEnd(2,'0'));
    return Number.isSafeInteger(cents)&&cents<=1000000000000 ? cents : null;
  }
  function validateStep(d,n) {
    if(n===0 && (!String(d.description||'').trim() || String(d.description).trim().length>190 || !(moneyCents(d.value)>0))) return 'Informe uma descrição de até 190 caracteres e um valor maior que zero.';
    if(n===1 && !d.category_id) return 'Escolha uma categoria para organizar o lançamento.';
    if(n===1 && d.method==='credit' && (!d.card_id || !Number.isInteger(Number(d.installments)) || Number(d.installments)<1 || Number(d.installments)>120 || Number(d.installments)>moneyCents(d.value))) return 'Escolha o cartão e de 1 a 120 parcelas, de pelo menos um centavo.';
    if(n===2 && !/^\d{4}-\d{2}-\d{2}$/.test(d.date||'')) return 'Informe uma data válida.';
    return '';
  }
  function logo(bank) {
    return bank?.logo ? '<img class="bank-logo" src="'+esc(bank.logo)+'" alt="" loading="lazy">' : '<span class="bank-logo bank-monogram" aria-hidden="true">'+esc((bank?.name||'Banco').slice(0,2).toUpperCase())+'</span>';
  }
  function bankFor(card) { return banksCache?.find(b=>b.id===card.bank_id)||{name:card.bank_name,logo:card.logo}; }
  function cards() { return S.creditCards?.cards || []; }
  async function banks() { if(!banksCache) banksCache=await api.get('/finance/banks'); return banksCache; }
  function capture() {
    const form=document.querySelector('form[data-form="finance-transaction"]');
    if(form && draft) Object.assign(draft,Object.fromEntries(new FormData(form)));
  }
  function openTransaction(t = {}, options = {}) {
    const date=state.financeMonth===today().slice(0,7)?today():state.financeMonth+'-01';
    draft={id:t.id||'',description:t.description||'',value:t.value ? (t.value/100).toFixed(2).replace('.',',') : '',type:t.type||'expense',category_id:t.category_id||'',method:t.payment_method==='credit'?'credit':(t.payment_method||'unspecified'),date:t.date||date,status:t.status||'pending',card_id:options.cardId||'',installments:'1',first_cycle:'',operation_key:key(),...options};
    if(options.cardId) draft.method='credit';
    step=0; quick=false; drawWizard();
  }
  function wizardSummary() {
    const cat=S.categories.find(c=>String(c.id)===String(draft.category_id));
    const card=cards().find(c=>c.id===draft.card_id);
    return '<div class="finance-review"><span class="eyebrow">Confira seu lançamento</span><strong class="finance-review-value">'+money(moneyCents(draft.value)||0)+'</strong><h3>'+esc(draft.description||'Seu lançamento')+'</h3><dl><div><dt>Tipo</dt><dd>'+(draft.type==='income'?'Entrada':'Saída')+'</dd></div><div><dt>Categoria</dt><dd>'+esc(cat?.name||'Selecione uma categoria')+'</dd></div><div><dt>Pagamento</dt><dd>'+esc(draft.method==='credit'?(card?.nickname||'Selecione um cartão'):(methods.find(m=>m[0]===draft.method)?.[1]||''))+'</dd></div>'+(draft.method==='credit'?'<div><dt>Parcelamento</dt><dd>'+Number(draft.installments)+'x · soma exata do valor</dd></div>':'')+'</dl></div>';
  }
  function drawWizard() {
    const d=draft, credit=d.method==='credit'&&d.type==='expense';
    const active=cards().filter(c=>Number(c.is_active));
    const steps=['O que aconteceu','Como organizar','Conferir e salvar'];
    const progress='<div class="finance-steps" aria-label="Etapas do lançamento">'+steps.map((label,i)=>'<span class="'+(i===step?'current':i<step?'complete':'')+'" '+(i===step?'aria-current="step"':'')+'><b>'+(i<step?'✓':i+1)+'</b>'+label+'</span>').join('')+'</div><progress class="finance-progress" value="'+(step+1)+'" max="3" aria-label="Progresso do lançamento"></progress>';
    const baseMethods=S.creditCards?.available?methods:[['unspecified','Não informado']];
    const paymentMethods=d.purchase_id?[['credit','Cartão de crédito']]:baseMethods.concat(S.creditCards?.available&&active.length&&!d.id?[['credit','Cartão de crédito']]:[]);
    const sections=[
      '<div class="finance-type-picks">'+[['expense','Saída','down'],['income','Entrada','up']].filter(x=>!d.purchase_id||x[0]==='expense').map(([value,label,i])=>'<button type="button" class="finance-type '+(d.type===value?'selected':'')+'" data-action="finance-type" data-id="'+value+'" aria-pressed="'+(d.type===value)+'">'+icon(i)+label+'</button>').join('')+'</div><input type="hidden" name="type" value="'+esc(d.type)+'">'+field('O que você está registrando?','description',d.description,'text',true,'Ex.: venda da loja ou reposição de estoque')+field('Valor em R$','value',d.value,'text',true,'0,00'),
      select('Categoria','category_id',[['','Selecione uma categoria'],...S.categories.map(c=>[c.id,c.name])],d.category_id)+'<button type="button" class="finance-text-button" data-action="finance-category">+ Criar categoria sem perder este lançamento</button>'+select('Forma de pagamento','method',paymentMethods,d.method)+(credit?select('Cartão','card_id',[['','Selecione seu cartão'],...active.filter(c=>!d.purchase_id||c.id===d.card_id).map(c=>[c.id,c.nickname+' · '+c.bank_name])],d.card_id)+field('Quantidade de parcelas','installments',d.installments,'number'):'')+(!active.length&&S.creditCards?.available?'<p class="storage-note">Você pode cadastrar cartões em Meus cartões.</p>':''),
      (quick?'':wizardSummary())+field(credit?'Data da compra':'Data de vencimento','date',d.date,'date')+(credit?field('Primeiro ciclo da fatura (opcional)','first_cycle',d.first_cycle,'month',false)+'<p class="storage-note">O ciclo é identificado pelo mês do fechamento. Deixe vazio para calcular automaticamente.</p><div id="finance-cycle-preview" class="finance-cycle-note" role="status">Confira o cartão e a data para ver a primeira fatura.</div>':select('Situação','status',[['pending','Pendente'],['paid',d.type==='income'?'Já recebido':'Já pago']],d.status)+'<p class="storage-note">Ao registrar como realizado, a data informada será a data efetiva. Para quitar depois, mantenha pendente.</p>')
    ];
    const content=progress+'<div class="finance-wizard-tools"><span class="small muted">'+(quick?'Todos os campos em uma tela':'Etapa '+(step+1)+' de 3')+'</span><button type="button" class="finance-text-button" data-action="finance-quick">'+(quick?'Usar etapas guiadas':'Usar modo rápido')+'</button></div>'+sections.map((html,i)=>'<fieldset class="finance-step-fields" data-step="'+i+'" '+(!quick&&i!==step?'hidden':'')+'><legend class="sr-only">'+steps[i]+'</legend>'+html+'</fieldset>').join('')+'<div class="finance-wizard-controls">'+(step>0&&!quick?btn('Voltar','finance-back'):'<span></span>')+(!quick&&step<2?btn('Continuar →','finance-next','primary'):'<span class="small muted">Tudo conferido? Salve para concluir.</span>')+'</div>';
    modal(d.id?'Editar lançamento':d.purchase_id?'Editar compra':'Novo lançamento',content,'Salvar lançamento','finance-transaction');
    const form=document.querySelector('form[data-form="finance-transaction"]');form.noValidate=true;
    form.querySelector('[name=value]')?.setAttribute('inputmode','decimal');
    form.querySelector('[name=description]')?.setAttribute('maxlength','190');
    const n=form.querySelector('[name=installments]');if(n){n.min='1';n.max='120';}
    form.querySelector('button[type=submit]').hidden=!quick&&step<2;
    form.querySelector('[name=id]')?.remove();
    form.querySelector('.finance-step-fields:not([hidden]) input:not([type=hidden]), .finance-step-fields:not([hidden]) select')?.focus();
    if(credit&&(quick||step===2)) previewCycle();
  }
  let previewSequence=0;
  async function previewCycle() {
    capture(); const seq=++previewSequence, target=document.getElementById('finance-cycle-preview');
    if(!target||!draft?.card_id||!draft.date) return;
    target.textContent='Calculando primeira fatura…';
    try {const c=await api.post('/finance/card-purchases/preview',{card_id:draft.card_id,purchase_date:draft.date,first_cycle:draft.first_cycle||null});if(seq!==previewSequence||!target.isConnected)return;target.textContent='Primeira fatura: fechamento '+day(c.closing_date)+' · vencimento '+day(c.due_date)+'. As próximas parcelas seguem nos meses seguintes.';}
    catch(e){if(seq===previewSequence&&target.isConnected)target.textContent=e.message;}
  }
  async function submitTransaction(form,d) {
    capture();
    if(!quick&&step<2){const error=validateStep(draft,step);if(error){formError(form,error);return;}step++;drawWizard();return;}
    for(let n=0;n<3;n++){const error=validateStep(draft,n);if(error){step=n;drawWizard();formError(document.querySelector('form[data-form="finance-transaction"]'),error);return;}}
    const credit=draft.method==='credit'&&draft.type==='expense';
    const payload={description:draft.description.trim(),value_cents:moneyCents(draft.value),category_id:Number(draft.category_id)};
    if(credit) {
      Object.assign(payload,{card_id:draft.card_id,installments:Number(draft.installments),purchase_date:draft.date,first_cycle:draft.first_cycle||null,operation_key:draft.operation_key});
      if(draft.purchase_id) await api.put('/finance/card-purchases/'+encodeURIComponent(draft.purchase_id),payload);
      else await api.post('/finance/card-purchases',payload);
    } else {
      Object.assign(payload,{type:draft.type,date:draft.date,status:draft.status||'pending',payment_method:draft.method});
      if(draft.id) await api.put('/finance/transactions/'+encodeURIComponent(draft.id),payload);
      else await api.post('/finance/transactions',payload);
    }
    draft=null; await saved('✓ Lançamento organizado e salvo.');
  }
  async function saved(message) { $('#modal').close(); await loadFinanceData();await render();toast(message); }
  async function openCard(id) {
    await banks(); const c=cards().find(x=>x.id===id)||{};
    selectedBank=c.bank_id?(banksCache.find(b=>b.id===c.bank_id)||{id:'other',name:c.bank_name}):null;
    modal(id?'Editar cartão':'Adicionar cartão',
      '<input type="hidden" name="id" value="'+esc(id||'')+'"><input type="hidden" name="bank_id" value="'+esc(c.bank_id||'')+'"><div id="finance-bank-selected" class="finance-bank-selected">'+(selectedBank?logo(selectedBank)+'<strong>'+esc(selectedBank.name)+'</strong>':'Escolha a instituição do seu cartão')+'</div><label class="finance-bank-search-label" for="finance-bank-search">Buscar instituição</label><input id="finance-bank-search" type="search" placeholder="Nome do banco ou código" autocomplete="off"><div id="finance-bank-results" class="finance-bank-results" role="group" aria-label="Instituições disponíveis"></div><div class="fields">'+'<div id="finance-custom-bank" '+(c.bank_id==='other'?'':'hidden')+'>'+field('Outra instituição: nome','bank_name',c.bank_id==='other'?c.bank_name:'','text',c.bank_id==='other')+'</div>'+field('Apelido do cartão','nickname',c.nickname||'','text',true,'Ex.: compras da loja')+select('Bandeira','brand',brands,c.brand||'mastercard')+field('Últimos quatro dígitos (opcional)','last_four',c.last_four||'','text',false,'1234')+field('Limite em R$','limit',((c.limit_cents||0)/100).toFixed(2).replace('.',','),'text')+field('Dia do fechamento','closing_day',c.closing_day||25,'number')+field('Dia do vencimento','due_day',c.due_day||5,'number')+'</div><p class="storage-note">Controle manual. O limite disponível considera somente os registros do VTD. Alterar datas não muda faturas já criadas.</p>',
      'Salvar cartão','finance-card');
    const f=document.querySelector('form[data-form="finance-card"]');
    f.querySelector('[name=limit]').setAttribute('inputmode','decimal');f.querySelector('[name=last_four]').maxLength=4;
    for(const name of ['closing_day','due_day']){f.querySelector('[name='+name+']').min=1;f.querySelector('[name='+name+']').max=31;}
    bankResults('');
  }
  const normalized=s=>s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  function bankResults(search) {
    const target=document.getElementById('finance-bank-results');if(!target)return;
    const matches=(banksCache||[]).filter(b=>normalized([b.name,...(b.aliases||[])].join(' ')).includes(normalized(search)));
    target.innerHTML=matches.slice(0,12).map(b=>'<button type="button" class="finance-bank-option" data-action="finance-bank" data-id="'+esc(b.id)+'">'+logo(b)+'<span>'+esc(b.name)+'</span></button>').join('')+'<button type="button" class="finance-bank-option" data-action="finance-bank" data-id="other">+ Outra instituição</button><small class="muted">'+matches.length+' resultado(s) · digite para refinar a busca</small>';
  }
  function renderCards() {
    if(!S.creditCards?.available) return '<section class="panel"><h2>Meus cartões</h2><p>'+esc(S.creditCards?.message||'Não foi possível carregar seus cartões.')+'</p>'+btn('Tentar novamente','finance-refresh')+'</section>';
    return '<section class="finance-card-intro"><div><span class="eyebrow">SUA CARTEIRA, ORGANIZADA</span><h2>Sua carteira</h2><p class="muted">Acompanhe limites, compras e faturas em um só lugar.</p></div>'+btn(icon('plus')+' Adicionar cartão','finance-new-card','primary')+'</section><div class="finance-credit-grid">'+cards().map(c=>'<section class="panel finance-credit-card '+(!Number(c.is_active)?'archived':'')+'"><div class="finance-credit-top">'+logo(bankFor(c))+'<span>'+esc(c.bank_name)+'</span><span class="tag">'+(Number(c.is_active)?'Ativo':'Arquivado')+'</span></div><h3>'+esc(c.nickname)+'</h3><p class="finance-card-digits">'+esc(c.brand.toUpperCase())+' · '+(c.last_four?'•••• '+esc(c.last_four):'Final não informado')+'</p><div class="finance-credit-balance"><span>Disponível estimado</span><strong>'+money(Number(c.available_cents))+'</strong><small>Limite '+money(Number(c.limit_cents))+' · comprometido '+money(Number(c.committed_cents))+'</small></div>'+(c.next_invoice?'<p class="finance-next-invoice">Próxima fatura <strong>'+money(c.next_invoice.total_cents)+'</strong> · '+day(c.next_invoice.due_date)+'</p>':'')+'<progress class="finance-limit-progress" max="'+Math.max(1,Number(c.limit_cents))+'" value="'+Math.min(Number(c.committed_cents),Math.max(1,Number(c.limit_cents)))+'" aria-label="Limite utilizado"></progress><div class="finance-credit-dates"><span>Fecha dia <b>'+Number(c.closing_day)+'</b></span><span>Vence dia <b>'+Number(c.due_day)+'</b></span></div><div class="finance-card-buttons">'+btn('Ver faturas','finance-invoices','small','data-id="'+esc(c.id)+'"')+(Number(c.is_active)?btn('Registrar compra','finance-buy','small primary','data-id="'+esc(c.id)+'"')+btn('Editar','finance-edit-card','small','data-id="'+esc(c.id)+'"')+btn('Arquivar','finance-archive-card','small','data-id="'+esc(c.id)+'"'):'')+'</div></section>').join('')+'</div>'+(!cards().length?'<section class="panel empty">'+icon('wallet')+'<h3>Seu primeiro cartão começa aqui</h3><p>Cadastre o cartão para organizar compras à vista ou parceladas.</p>'+btn('Adicionar meu cartão','finance-new-card','primary')+'</section>':'')+'<p class="storage-note">Os valores são calculados com seus registros manuais no VTD; não são uma consulta ao banco.</p>';
  }
  function overview() {
    if(!S.creditCards?.available)return '';
    const active=cards().filter(c=>Number(c.is_active));
    const bills=cards().filter(c=>c.next_invoice).sort((a,b)=>a.next_invoice.due_date.localeCompare(b.next_invoice.due_date)).slice(0,3);
    return '<section class="panel finance-wallet-summary"><div><span class="eyebrow">CARTÕES DE CRÉDITO</span><h2>'+active.length+' cartão(ões) na sua carteira</h2><p class="small muted">'+money(cards().reduce((s,c)=>s+Number(c.committed_cents),0))+' em parcelas pendentes, incluindo os próximos meses.</p>'+bills.map(c=>'<button type="button" class="finance-next-bill" data-action="finance-invoice" data-id="'+esc(c.next_invoice.id)+'">'+esc(c.nickname)+' · '+day(c.next_invoice.due_date)+' · '+money(c.next_invoice.total_cents)+' →</button>').join('')+'</div><a class="btn" href="#/finance/cards">Abrir minha carteira →</a></section>';
  }
  async function openInvoices(id) {
    const list=await api.get('/finance/cards/'+encodeURIComponent(id)+'/invoices');
    const c=cards().find(x=>x.id===id);
    modal('Faturas · '+(c?.nickname||'Cartão'),list.length?'<div class="finance-invoice-list">'+list.map(f=>'<button type="button" data-action="finance-invoice" data-id="'+esc(f.id)+'"><span><strong>Vencimento '+day(f.due_date)+'</strong><small>Fechamento '+day(f.closing_date)+'</small></span><span><b>'+money(f.total_cents)+'</b><small class="'+(f.is_settled?'positive':'')+'">'+(f.is_settled?'Paga':f.due_date<today()?'Vencida':'Em aberto')+' →</small></span></button>').join('')+'</div>':'<div class="empty"><h3>Ainda não há faturas</h3><p>Registre a primeira compra para acompanhar os vencimentos.</p></div>');
  }
  async function openInvoice(id) {
    const f=await api.get('/finance/card-invoices/'+encodeURIComponent(id));
    modal('Fatura · '+f.card.nickname,'<div class="finance-invoice-total"><span>Vencimento '+day(f.due_date)+'</span><strong>'+money(f.total_cents)+'</strong><span class="tag '+(Number(f.is_settled)?'green':'yellow')+'">'+(Number(f.is_settled)?'Paga':'Em aberto')+'</span></div><div class="finance-invoice-list">'+f.items.map(i=>'<div class="finance-invoice-item"><span><strong>'+esc(i.description)+'</strong><small>Compra em '+day(i.purchase_date)+'</small></span><b>'+money(Number(i.value_cents))+'</b>'+(!Number(f.is_settled)?'<span class="finance-card-buttons">'+btn('Editar compra','finance-edit-purchase','small','data-id="'+esc(i.purchase_id)+'"')+btn('Cancelar compra','finance-cancel-purchase','small danger','data-id="'+esc(i.purchase_id)+'"')+'</span>':'')+'</div>').join('')+'</div><div class="finance-card-buttons">'+btn('Todas as faturas','finance-invoices','','data-id="'+esc(f.card_id)+'"')+(!Number(f.is_settled)?btn('Registrar pagamento integral','finance-pay','primary','data-id="'+esc(id)+'"'):'')+'</div>');
  }
  async function action(a,id,el) {
    if(a==='close'&&draft&&document.querySelector('form[data-form="finance-category"]')){drawWizard();return true;}
    if(!a.startsWith('finance-'))return false;
    if(a==='finance-next'||a==='finance-back'||a==='finance-quick'||a==='finance-type') {
      capture(); if(a==='finance-next'){const error=validateStep(draft,step);if(error){formError(document.querySelector('form[data-form="finance-transaction"]'),error);return true;}step=Math.min(2,step+1);}
      if(a==='finance-back')step=Math.max(0,step-1);
      if(a==='finance-quick')quick=!quick;
      if(a==='finance-type'){draft.type=id;if(id==='income'&&draft.method==='credit')draft.method='unspecified';}
      drawWizard();return true;
    }
    if(a==='finance-category'){capture();modal('Criar categoria',field('Nome da categoria','name'),'Adicionar e voltar','finance-category');return true;}
    if(a==='finance-new-card'||a==='finance-edit-card')await openCard(id);
    if(a==='finance-bank'){selectedBank=id==='other'?{id:'other',name:'Outra instituição'}:banksCache.find(b=>b.id===id);const f=el.closest('form');f.querySelector('[name=bank_id]').value=selectedBank.id;document.getElementById('finance-bank-selected').innerHTML=logo(selectedBank)+'<strong>'+esc(selectedBank.name)+'</strong>';f.querySelector('[name=bank_name]').required=id==='other';document.getElementById('finance-custom-bank').hidden=id!=='other';if(id==='other')f.querySelector('[name=bank_name]').focus();}
    if(a==='finance-buy')openTransaction({}, {cardId:id});
    if(a==='finance-invoices')await openInvoices(id);
    if(a==='finance-invoice')await openInvoice(id);
    if(a==='finance-pay')modal('Registrar pagamento da fatura','<input type="hidden" name="invoice_id" value="'+esc(id)+'"><input type="hidden" name="operation_key" value="'+key()+'">'+field('Data efetiva do pagamento','effective_date',today(),'date')+'<p class="storage-note">O saldo pendente será pago integralmente. Esta operação quita as parcelas sem duplicar despesas.</p>','Confirmar pagamento','finance-payment');
    if(a==='finance-archive-card')modal('Arquivar cartão?','<p>O histórico e as faturas serão mantidos. Novas compras ficarão desativadas.</p><input type="hidden" name="id" value="'+esc(id)+'">','Arquivar cartão','finance-archive');
    if(a==='finance-cancel-purchase')modal('Cancelar compra?','<p>As parcelas pendentes desta compra serão removidas. Compras com parcela paga são preservadas.</p><input type="hidden" name="id" value="'+esc(id)+'">','Confirmar cancelamento','finance-cancel');
    if(a==='finance-edit-purchase'){const p=await api.get('/finance/card-purchases/'+encodeURIComponent(id));openTransaction({description:p.description,value:Number(p.value_cents),category_id:p.category_id,date:p.purchase_date},{cardId:p.card_id,purchase_id:p.id,installments:String(p.installments),operation_key:p.operation_key,first_cycle:p.first_cycle});}
    if(a==='finance-refresh'){await loadFinanceData();render();}
    return true;
  }
  async function submit(kind,form,d) {
    if(!kind.startsWith('finance-'))return false;
    if(kind==='finance-transaction')await submitTransaction(form,d);
    if(kind==='finance-card') {
      const limit=moneyCents(d.limit);if(limit===null){formError(form,'Informe um limite válido em reais.');return true;}
      const payload={bank_id:d.bank_id,bank_name:d.bank_name,nickname:d.nickname,brand:d.brand,last_four:d.last_four,limit_cents:limit,closing_day:Number(d.closing_day),due_day:Number(d.due_day)};
      if(d.id)await api.put('/finance/cards/'+encodeURIComponent(d.id),payload);else await api.post('/finance/cards',payload);
      await saved('Cartão salvo na sua carteira.');
    }
    if(kind==='finance-category') {
      const c=await api.post('/finance/categories',{name:d.name.trim()});
      S.categories=await api.get('/finance/categories');draft.category_id=c.id;drawWizard();
    }
    if(kind==='finance-payment'){await api.post('/finance/card-invoices/'+encodeURIComponent(d.invoice_id)+'/settle',{effective_date:d.effective_date,operation_key:d.operation_key});await saved('✓ Fatura paga. Seu saldo foi atualizado.');}
    if(kind==='finance-archive'){await api.post('/finance/cards/'+encodeURIComponent(d.id)+'/archive',{});await saved('Cartão arquivado. Histórico preservado.');}
    if(kind==='finance-cancel'){await api.post('/finance/card-purchases/'+encodeURIComponent(d.id)+'/cancel',{});await saved('Compra cancelada e parcelas removidas.');}
    return true;
  }
  function input(e) {
    if(e.target.id==='finance-bank-search')bankResults(e.target.value);
  }
  function change(e) {
    if(e.target.closest?.('form[data-form="finance-transaction"]')) {
      capture();
      if(e.target.name==='method'&&draft.method!=='credit'){draft.card_id='';draft.installments='1';draft.first_cycle='';}
      if(['method','card_id'].includes(e.target.name))drawWizard();
      else if(['date','first_cycle'].includes(e.target.name))previewCycle();
    }
    if(e.target.dataset?.financeFilter){state[e.target.dataset.financeFilter]=e.target.value;render();}
  }
  function filters() {
    return '<div class="finance-extra-filters"><label>Categoria<select data-finance-filter="txCategory"><option value="">Todas</option>'+S.categories.map(c=>'<option value="'+Number(c.id)+'" '+(String(state.txCategory)===String(c.id)?'selected':'')+'>'+esc(c.name)+'</option>').join('')+'</select></label><label>Pagamento<select data-finance-filter="txMethod"><option value="">Todos</option>'+methods.concat([['credit','Crédito']]).map(([v,l])=>'<option value="'+v+'" '+(state.txMethod===v?'selected':'')+'>'+l+'</option>').join('')+'</select></label><label>Cartão<select data-finance-filter="txCard"><option value="">Todos</option>'+cards().map(c=>'<option value="'+esc(c.id)+'" '+(state.txCard===c.id?'selected':'')+'>'+esc(c.nickname)+'</option>').join('')+'</select></label></div>';
  }
  function filtered(list) { return list.filter(t=>(!state.txCategory||String(t.category_id)===String(state.txCategory))&&(!state.txMethod||t.payment_method===state.txMethod)&&(!state.txCard||t.card?.card_id===state.txCard)); }
  function reset() {
    draft=null;selectedBank=null;step=0;quick=false;previewSequence++;
    if(typeof state!=='undefined'){state.txCategory='';state.txMethod='';state.txCard='';}
  }
  return {reset,moneyCents,validateStep,openTransaction,action,submit,input,change,renderCards,overview,filters,filtered};
})();
