(function (root) {
  'use strict';
  const labels = { cost: 'Custo de compra', quantity: 'Quantidade de peças', freight: 'Frete da compra', packaging: 'Embalagem por peça', extra: 'Outros custos por peça', fee: 'Taxa de pagamento', tax: 'Impostos sobre a venda', margin: 'Margem desejada', discount: 'Desconto' };
  const optional = ['freight', 'packaging', 'extra', 'fee', 'tax', 'discount'];
  function parse(value) {
    if (typeof value === 'number') return Number.isFinite(value) ? value : NaN;
    const s = String(value ?? '').trim();
    if (!s) return NaN;
    if (/^\d{1,3}(\.\d{3})+,\d{1,2}$/.test(s)) return Number(s.replaceAll('.', '').replace(',', '.'));
    return /^\d+(?:[.,]\d{1,2})?$/.test(s) ? Number(s.replace(',', '.')) : NaN;
  }
  const ceilCent = n => Math.ceil(n * 100 - 1e-8) / 100;
  function calculate(raw) {
    const v = {}, errors = {};
    for (const key of Object.keys(labels)) {
      v[key] = optional.includes(key) && String(raw[key] ?? '').trim() === '' ? 0 : parse(raw[key]);
      if (!Number.isFinite(v[key]) || v[key] < 0 || v[key] > 100000000) errors[key] = `${labels[key]}: informe um número válido, maior ou igual a zero.`;
    }
    if (!(v.cost > 0)) errors.cost = 'Informe o custo de compra por peça, maior que zero.';
    if (!Number.isInteger(v.quantity) || v.quantity < 1) errors.quantity = 'Informe a quantidade inteira de peças da compra, a partir de 1.';
    for (const key of ['fee', 'tax', 'margin', 'discount']) {
      if (v[key] >= 100) errors[key] = `${labels[key]} deve ser menor que 100%.`;
    }
    if (v.fee + v.tax + v.margin >= 100) errors.margin = 'A soma de taxas, impostos e margem precisa ser menor que 100%.';
    if (Object.keys(errors).length) return { errors };
    const cost = v.cost + v.freight / v.quantity + v.packaging + v.extra;
    const rate = (v.fee + v.tax) / 100;
    const price = ceilCent(cost / (1 - rate - v.margin / 100));
    if (!Number.isFinite(price) || price > 100000000) return { errors: { margin: 'O preço calculado ficou muito alto. Revise os custos, taxas e margem.' } };
    const floor = ceilCent(cost / (1 - rate));
    const contribution = price * (1 - rate) - cost;
    const discounted = Math.round(price * (1 - v.discount / 100) * 100) / 100;
    const discountContribution = discounted * (1 - rate) - cost;
    return { errors: {}, cost, freightUnit: v.freight / v.quantity, price, floor,
      fees: price * v.fee / 100, taxes: price * v.tax / 100, contribution,
      margin: contribution / price * 100,
      noFeePrice: ceilCent(cost / (1 - v.tax / 100 - v.margin / 100)),
      discounted, discountContribution, discountMargin: discounted ? discountContribution / discounted * 100 : null,
      maxDiscount: Math.max(0, (1 - floor / price) * 100), values: v };
  }
  const brl = v => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const pct = v => v === null ? 'Não se aplica' : `${v.toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`;
  function field(name, hint, value = '', required = false) {
    return `<div class="pricing-field"><label for="pricing-${name}">${labels[name]}${['fee','tax','margin','discount'].includes(name) ? ' (%)' : name === 'quantity' ? '' : ' (R$)'}</label><input id="pricing-${name}" name="${name}" type="text" inputmode="${name === 'quantity' ? 'numeric' : 'decimal'}" value="${value}" ${required ? 'required' : ''} autocomplete="off" aria-describedby="pricing-${name}-hint pricing-${name}-error"><small id="pricing-${name}-hint">${hint}</small><span class="pricing-field-error" id="pricing-${name}-error"></span></div>`;
  }
  function render() {
    return `<section class="pricing" aria-labelledby="pricing-title"><header class="pricing-heading"><div><h1 id="pricing-title">Seu preço, na prática.</h1><p>Ajuste os valores e veja na hora quanto sobra em cada venda.</p></div><button type="button" class="btn" data-pricing-example>Experimentar com exemplo</button></header>
    <div class="pricing-mobile-summary"><span>Preço por peça</span><strong id="pricing-mobile-price">—</strong></div>
    <div class="pricing-layout"><form id="pricing-form" novalidate>
    <section class="pricing-step"><h2>Quanto você paga?</h2><div class="pricing-cost">${field('cost','Custo de uma unidade, sem frete.','',true)}</div>
    <details class="pricing-options"><summary>Adicionar frete, embalagem e outros custos</summary><div class="pricing-fields">${field('quantity','Quantidade que divide o frete.','1',true)}${field('freight','Frete total da compra.')}${field('packaging','Custo por unidade.')}${field('extra','Outros custos variáveis por unidade.')}</div></details></section>
    <section class="pricing-step"><div class="pricing-section-title"><h2>Quanto você quer que sobre?</h2><span>Sobre o valor da venda</span></div>
    ${field('margin','Margem de contribuição desejada. Ainda precisa cobrir as despesas fixas.','30',true)}
    <input class="pricing-slider" type="range" min="0" max="80" step="1" value="30" id="pricing-margin-range" aria-label="Ajustar margem desejada" aria-describedby="pricing-margin-hint">
    <div class="pricing-presets" aria-label="Sugestões de margem">${[20,30,40,50].map(n=>`<button type="button" data-pricing-margin="${n}" aria-pressed="${n===30}">${n}%</button>`).join('')}</div>
    </section>
    <details class="pricing-step pricing-options" open><summary>Taxas de pagamento e impostos</summary><p class="pricing-tip">Informe os percentuais da sua operação. Campos em branco contam como zero.</p><div class="pricing-fields">${field('fee','Taxa total do meio de pagamento.')}${field('tax','Alíquota aplicável à sua venda.')}</div></details>
    <section class="pricing-step"><h2>E se você der um desconto?</h2>${field('discount','Simule o desconto sobre o preço sugerido.','0')}<input class="pricing-slider" type="range" min="0" max="70" step="1" value="0" id="pricing-discount-range" aria-label="Ajustar desconto"></section>
    <div class="pricing-actions"><button type="reset" class="btn">Nova simulação</button><span>Cálculo automático enquanto você digita</span></div><p class="pricing-errors" id="pricing-errors" role="status"></p></form>
    <aside class="pricing-results"><div id="pricing-result"><div class="pricing-empty"><span class="pricing-empty-symbol" aria-hidden="true">R$</span><h2>Comece pelo custo da peça.</h2><p>Seu preço, sua margem e o efeito de um desconto aparecem aqui, sem precisar clicar em calcular.</p><small>Use o exemplo para explorar a calculadora.</small></div></div></aside></div>
    <p id="pricing-announcement" class="pricing-sr" aria-live="polite" aria-atomic="true"></p>
    <details class="pricing-explanation"><summary>Entenda os números</summary><p>Preço = custo por peça ÷ [1 − (taxas + impostos + margem desejada) ÷ 100]. O custo inclui compra, frete dividido igualmente pela quantidade, embalagem e outros custos variáveis informados.</p><p>Margem é um percentual do preço de venda, não um acréscimo sobre o custo. A sobra é a margem de contribuição: ainda precisa cobrir aluguel, salários e outras despesas fixas. Não é lucro líquido.</p><p>O preço sugerido é arredondado para cima ao centavo. Para um rateio diferente de frete, use quantidade 1 e informe o frete por unidade. Os percentuais e custos são informados por você.</p><p>A simulação não cria produtos nem lançamentos financeiros e não fica salva ao sair da página.</p></details></section>`;
  }
  function resultHTML(r) {
    const below = r.discountContribution < -1e-8;
    const lowMargin = r.discountMargin !== null && r.discountMargin + .001 < r.values.margin;
    const rows = [['Compra',r.values.cost],['Frete rateado',r.freightUnit],['Embalagem',r.values.packaging],['Outros custos',r.values.extra],['Taxa de pagamento',r.fees],['Impostos',r.taxes]];
    return `<div class="pricing-ticket"><span>Preço sugerido por peça</span><strong class="pricing-price">${brl(r.price)}</strong><div class="pricing-ticket-bottom"><span>Sobra por peça<strong>${brl(r.contribution)}</strong></span><span>Margem efetiva<strong>${pct(r.margin)}</strong></span></div></div>
    <div class="pricing-result-body"><h2>Para onde vai cada venda</h2><div class="pricing-bar" aria-hidden="true"><i style="width:${r.cost/r.price*100}%"></i><i style="width:${(r.fees+r.taxes)/r.price*100}%"></i><i style="width:${r.margin}%"></i></div><dl class="pricing-allocation"><div><dt>Custos</dt><dd>${brl(r.cost)}</dd></div><div><dt>Taxas e impostos</dt><dd>${brl(r.fees+r.taxes)}</dd></div><div><dt>Sobra</dt><dd>${brl(r.contribution)}</dd></div></dl><p class="pricing-note">A sobra ainda precisa pagar as despesas fixas. Não é lucro líquido.</p>
    <div class="pricing-discount ${below?'is-negative':''}"><div><h2>Com ${pct(r.values.discount)} de desconto</h2><strong>${brl(r.discounted)}</strong></div><p>Sobra <b>${brl(r.discountContribution)}</b> por peça · Margem ${pct(r.discountMargin)}</p>${below?'<p class="pricing-warning">Atenção: esse desconto não cobre os custos informados.</p>':lowMargin?'<p class="pricing-warning">A margem fica abaixo da sua meta.</p>':'<p>A margem desejada está preservada.</p>'}</div>
    <div class="pricing-no-fee"><span>Preço sem taxa de pagamento<strong>${brl(r.noFeePrice)}</strong></span><p>Mesma margem e impostos. Só use no Pix ou dinheiro se não houver taxa.</p></div>
    <details class="pricing-cost-detail"><summary>Ver composição e preço mínimo</summary><dl class="pricing-breakdown">${rows.map(([label,value])=>`<div><dt>${label}</dt><dd>${brl(value)}</dd></div>`).join('')}</dl><p>Preço mínimo para cobrir os custos variáveis: <b>${brl(r.floor)}</b>. Não cobre despesas fixas nem garante lucro.</p></details></div>`;
  }
  let announcementTimer;
  function clearErrors(form) {
    Object.keys(labels).forEach(k => { form.elements[k].removeAttribute('aria-invalid'); document.getElementById(`pricing-${k}-error`).textContent = ''; });
    document.getElementById('pricing-errors').textContent = '';
  }
  function update(form) {
    clearErrors(form);
    const raw = Object.fromEntries(Object.keys(labels).map(k => [k, form.elements[k].value]));
    const r = calculate(raw);
    const keys = Object.keys(r.errors);
    const result = document.getElementById('pricing-result');
    clearTimeout(announcementTimer);
    document.getElementById('pricing-announcement').textContent = '';
    for (const key of ['margin','discount']) {
      const val = parse(form.elements[key].value);
      const slider = document.getElementById(`pricing-${key}-range`);
      if (Number.isFinite(val)) slider.value = Math.min(Number(slider.max),val);
      slider.setAttribute('aria-valuetext',Number.isFinite(val)?pct(val):'Informe um percentual válido');
    }
    form.querySelectorAll('[data-pricing-margin]').forEach(button=>button.setAttribute('aria-pressed',String(Number(button.dataset.pricingMargin)===parse(raw.margin))));
    if (keys.length) {
      keys.forEach(k => {
        if (k==='cost' && raw.cost.trim()==='') return;
        form.elements[k].setAttribute('aria-invalid','true');
        document.getElementById(`pricing-${k}-error`).textContent=r.errors[k];
        const details=form.elements[k].closest('details');if(details)details.open=true;
      });
      document.getElementById('pricing-mobile-price').textContent='—';
      result.innerHTML='<div class="pricing-empty"><span class="pricing-empty-symbol" aria-hidden="true">R$</span><h2>'+(!raw.cost.trim()?'Informe o custo da peça.':'Vamos ajustar os valores?')+'</h2><p>'+(!raw.cost.trim()?'Digite quanto você paga por uma unidade para começar.':'Confira os campos destacados. O resultado volta assim que os valores forem válidos.')+'</p></div>';
      return;
    }
    const detailsOpen = result.querySelector('details')?.open;
    result.innerHTML=resultHTML(r);
    if(detailsOpen)result.querySelector('details').open=true;
    document.getElementById('pricing-mobile-price').textContent=brl(r.price);
    announcementTimer=setTimeout(()=>{
      const el=document.getElementById('pricing-announcement');
      if(el)el.textContent=`Preço sugerido ${brl(r.price)}. Sobra por peça ${brl(r.contribution)}.`;
    },500);
  }
  if (typeof document !== 'undefined') {
    document.addEventListener('submit',e=>{if(e.target.id==='pricing-form'){e.preventDefault();update(e.target);}});
    document.addEventListener('input',e=>{
      if(e.target.form?.id!=='pricing-form')return;
      const form=e.target.form;
      if(e.target.id==='pricing-margin-range')form.elements.margin.value=e.target.value;
      if(e.target.id==='pricing-discount-range')form.elements.discount.value=e.target.value;
      update(form);
    });
    document.addEventListener('reset',e=>{if(e.target.id==='pricing-form'){clearTimeout(announcementTimer);setTimeout(()=>{if(e.target.isConnected)update(e.target);},0);}});
    document.addEventListener('click',e=>{
      const example=e.target.closest('[data-pricing-example]');
      const preset=e.target.closest('[data-pricing-margin]');
      if(!example&&!preset)return;
      const form=document.getElementById('pricing-form');if(!form)return;
      if(example){
        const values={cost:'30',quantity:'10',freight:'50',packaging:'2',extra:'0',fee:'5',tax:'4',margin:'30',discount:'10'};
        Object.entries(values).forEach(([key,value])=>{form.elements[key].value=value;});
        form.querySelector('.pricing-options').open=true;
      }else{form.elements.margin.value=preset.dataset.pricingMargin;}
      update(form);
    });
  }
  const api = { parse, calculate, render };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  root.VTDPricing = api;
})(typeof window !== 'undefined' ? window : globalThis);
