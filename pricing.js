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
    return `<section class="pricing" aria-labelledby="pricing-title"><header class="pricing-heading"><h1 id="pricing-title">Precificação</h1><p>Descubra por quanto vender cada peça, considerando os seus custos e a margem que deseja.</p></header><div class="pricing-layout"><form id="pricing-form" novalidate><fieldset><legend>Quanto custa cada peça?</legend><div class="pricing-fields">${field('cost','Valor pago ao fornecedor por uma unidade.','',true)}${field('quantity','Peças que dividem o frete desta compra.','1',true)}${field('freight','Frete total da compra. Dividiremos pela quantidade.')}${field('packaging','Sacola, caixa ou embalagem de uma unidade.')}${field('extra','Outros custos variáveis de uma unidade.')}</div></fieldset><fieldset><legend>Taxas e margem sobre a venda</legend><div class="pricing-fields">${field('fee','Percentual total cobrado pelo meio de pagamento.')}${field('tax','Percentual aplicável à sua venda. Informe sua alíquota.')}${field('margin','Percentual do preço de venda que você quer que sobre.','',true)}${field('discount','Desconto para simular sobre o preço sugerido.')}</div></fieldset><p class="pricing-tip">Os campos opcionais em branco contam como zero. Inclua todos os custos aplicáveis para uma simulação útil.</p><div class="pricing-actions"><button type="submit" class="btn primary">Calcular preço</button><button type="button" class="btn" data-pricing-example>Usar exemplo</button><button type="reset" class="btn">Limpar</button></div><p class="pricing-errors" role="alert" id="pricing-errors"></p></form><aside class="pricing-results" aria-live="polite" aria-atomic="true"><div id="pricing-result"><h2>Seu preço começa pelos custos</h2><p>Preencha os valores e clique em calcular. Você verá o preço sugerido, a composição da venda e o efeito de um desconto.</p><p>Quer entender primeiro? Use o exemplo de uma peça de R$ 30.</p></div></aside></div><details class="pricing-explanation"><summary>Como o cálculo funciona?</summary><p>Preço = custo por peça ÷ [1 − (taxas + impostos + margem desejada) ÷ 100]. O custo por peça inclui compra, frete rateado, embalagem e outros custos variáveis informados.</p><p>Margem é um percentual do preço de venda, não um acréscimo sobre o custo. A margem de contribuição ainda precisa pagar aluguel, salários e outras despesas fixas; não representa lucro líquido.</p><p>O preço sugerido é arredondado para cima ao centavo. O frete é dividido igualmente entre as peças. Se os produtos têm pesos ou custos de transporte diferentes, informe o custo unitário já rateado usando quantidade 1.</p><p>Esta simulação não registra produtos, estoque nem lançamentos financeiros. Os dados não são salvos ao sair da página.</p></details></section>`;
  }
  function resultHTML(r) {
    const rows = [['Compra por peça', r.values.cost], ['Frete por peça', r.freightUnit], ['Embalagem', r.values.packaging], ['Outros custos', r.values.extra], ['Taxa de pagamento', r.fees], ['Impostos', r.taxes]];
    return `<p class="pricing-result-label">Preço sugerido por peça</p><strong class="pricing-price">${brl(r.price)}</strong><p>Para uma margem de contribuição de ${pct(r.values.margin)}.</p><dl class="pricing-breakdown">${rows.map(([k,v]) => `<div><dt>${k}</dt><dd>${brl(v)}</dd></div>`).join('')}<div class="pricing-total"><dt>Sobra por peça</dt><dd>${brl(r.contribution)}</dd></div></dl><p class="pricing-note">Margem efetiva: ${pct(r.margin)}. Essa sobra ainda precisa cobrir as despesas fixas da loja.</p><section class="pricing-comparison"><h2>Sem taxa de pagamento</h2><strong>${brl(r.noFeePrice)}</strong><p>Preço para a mesma margem, mantendo os impostos. Use para Pix ou dinheiro somente se não houver taxa.</p></section><section class="pricing-comparison"><h2>Com ${pct(r.values.discount)} de desconto</h2><strong>${brl(r.discounted)}</strong><p>Sobra por peça: <b>${brl(r.discountContribution)}</b> · Margem: ${pct(r.discountMargin)}</p>${r.discountContribution < -1e-8 ? '<p class="pricing-warning">Este desconto não cobre os custos e taxas informados.</p>' : r.discountMargin !== null && r.discountMargin + 0.001 < r.values.margin ? '<p class="pricing-warning">O desconto reduz a margem abaixo da meta.</p>' : ''}<p>Preço mínimo para cobrir apenas os custos variáveis: <b>${brl(r.floor)}</b>. Não cobre as despesas fixas nem garante lucro.</p></section>`;
  }
  function clearErrors(form) {
    Object.keys(labels).forEach(k => { form.elements[k].removeAttribute('aria-invalid'); document.getElementById(`pricing-${k}-error`).textContent = ''; });
    document.getElementById('pricing-errors').textContent = '';
  }
  function submit(form) {
    clearErrors(form);
    const raw = Object.fromEntries(Object.keys(labels).map(k => [k, form.elements[k].value]));
    const r = calculate(raw);
    const keys = Object.keys(r.errors);
    if (keys.length) {
      keys.forEach(k => { form.elements[k].setAttribute('aria-invalid','true'); document.getElementById(`pricing-${k}-error`).textContent = r.errors[k]; });
      document.getElementById('pricing-errors').textContent = 'Confira os campos destacados antes de calcular.';
      document.getElementById('pricing-result').textContent = 'Corrija os valores para ver uma nova simulação.';
      form.elements[keys[0]].focus();
      return;
    }
    document.getElementById('pricing-result').innerHTML = resultHTML(r);
  }
  if (typeof document !== 'undefined') {
    document.addEventListener('submit', e => { if (e.target.id === 'pricing-form') { e.preventDefault(); submit(e.target); } });
    document.addEventListener('input', e => { if (e.target.form?.id === 'pricing-form') document.getElementById('pricing-result').textContent = 'Valores alterados. Clique em Calcular preço para atualizar a simulação.'; });
    document.addEventListener('reset', e => { if (e.target.id === 'pricing-form') { clearErrors(e.target); document.getElementById('pricing-result').textContent = 'Preencha os valores para calcular uma nova simulação.'; } });
    document.addEventListener('click', e => {
      if (!e.target.closest('[data-pricing-example]')) return;
      const form = document.getElementById('pricing-form');
      const values = { cost:'30',quantity:'10',freight:'50',packaging:'2',extra:'0',fee:'5',tax:'4',margin:'30',discount:'10' };
      Object.entries(values).forEach(([k,v]) => { form.elements[k].value = v; });
      submit(form);
    });
  }
  const api = { parse, calculate, render };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  root.VTDPricing = api;
})(typeof window !== 'undefined' ? window : globalThis);
