# Precificação VTD

Implementação em `pricing.js` e `pricing.css`, integrada em `app.js` e `index.html`.
Rota: `#/pricing`, na sessão autenticada do lojista. Disponível no menu e no início; no celular o Guia continua no menu lateral.

## Escopo
Simulação por unidade: compra, frete total dividido pela quantidade, embalagem, outros custos variáveis, taxas de pagamento, impostos sobre venda, margem de contribuição e desconto.
Preço = custo unitário / (1 - (taxas + impostos + margem) / 100).
Preço sugerido arredondado para cima ao centavo. O preço mínimo cobre somente os custos variáveis informados. Não representa lucro líquido nem equilíbrio da loja.
Percentuais tributários são informados pelo usuário. Não há estimativa automática de regime tributário ou taxas de cartão.

Dados apenas no formulário: não há gravação em banco nem persistência entre navegações. Não cria produtos, estoque ou lançamentos financeiros.

## Verificação
23 testes Node passaram, incluindo os testes existentes de frontend e financeiro.
Teste de navegador da rota integrada com API simulada: larguras 1366, 768, 390 e 360; sem rolagem horizontal ou erros JavaScript; cálculo, invalidação de resultado anterior, erro de margem, desconto e limpeza conferidos.
Autenticação e banco reais não foram utilizados nessa verificação.

## Entrega
A prévia independente fica em outputs/vtd-precificacao/index.html e usa o mesmo componente.
O pacote de integração contém os quatro arquivos alterados e o teste de cálculo. Não é um site completo e ainda não foi publicado.
Antes de aplicar o pacote em produção, comparar app.js e index.html com a versão vigente para preservar alterações concorrentes e fazer backup dos arquivos substituídos.
