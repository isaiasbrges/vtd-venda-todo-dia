# Financeiro com cartões - instalação e verificação

## Estado da entrega local (2026-10-31)

Implementados formulário em três etapas e modo rápido, categorias sem perda do rascunho, formas de pagamento, cadastro/edição/arquivamento de cartões, compras em até 120 parcelas, ajuste da primeira fatura, consulta de faturas, edição/cancelamento de compras sem parcelas pagas e quitação integral.

O dashboard mostra a carteira e próximas faturas. Listagens podem ser filtradas por categoria, pagamento e cartão. Parcelas identificam sua fatura, e o CSV inclui cartão, parcela e data efetiva. O financeiro continua restrito ao plano Plus e isolado por loja.

Catálogo: 468 instituições do STR/BCB; 50 logos oficiais verificados e alternativa neutra para as demais. Fontes em assets/banks/manifest.json. Não é integração com bancos.

## Modelo financeiro

Uma parcela é um lançamento pendente de saída. Pagar a fatura quita esses lançamentos na data efetiva informada, em transação única; não cria outra despesa pelo total. O limite comprometido inclui todas as parcelas pendentes, inclusive futuras. Datas de faturas já criadas não são recalculadas ao editar o cartão.

Pagamento parcial, rotativo, juros automáticos, estornos e recorrências no cartão não fazem parte desta versão. Não cadastrar número completo, CVV ou senha.

## Instalação e publicação

1. Validar a versão candidata em cópia isolada de MySQL/MariaDB compatível com a hospedagem.
2. Fazer backup verificável do banco e dos arquivos atuais. Registrar saldos, contagens e versão anterior.
3. Preservar a configuração privada, usuários, produtos, webhooks e demais dados existentes.
4. Disponibilizar o código da versão candidata. Cartões permanecem indisponíveis até a migração 004 estar integralmente registrada; financeiro antigo continua funcionando.
5. No terminal da hospedagem, usando a configuração privada correta: php backend/migrate.php --check.
6. Aplicar explicitamente: php backend/migrate.php --apply. O comando não executa seeds.
7. O Migrator registra versões, reconhece coluna/índices anteriores da 003 e cria a estrutura adicional da 004. DDL em MySQL pode confirmar alterações parcialmente: conferir o resultado antes de liberar uso.
8. Repetir --check e o percurso de aceitação. Não deixar scripts de migração acessíveis por HTTP.
9. Rever o resultado dos testes MySQL no GitHub Actions antes de publicar em produção.

Não executar testes ou seeds com o banco real. Não usar testes/run_all_tests.php como instalador. Nenhuma publicação ou migração de produção foi realizada nesta tarefa.

## Reversão

Antes de qualquer uso da nova funcionalidade, é possível restaurar o backup completo e o código anterior. Depois de registrar cartões/compras reais, não restaurar um backup antigo silenciosamente nem excluir tabelas: preservar os novos lançamentos e reconciliar os vínculos em um procedimento de reversão específico. O código anterior poderá tratar parcelas como lançamentos comuns; portanto, não liberar edição com uma versão antiga sem avaliar esse comportamento.

## Verificações

- node --check app.js
- node --check finance-ui.js
- node --test tests/frontend.test.cjs tests/finance-ui.test.cjs
- php tests/run_all_tests.php
- php -d zend.assertions=1 -d assert.exception=1 tests/test_finance_csv.php
- php -d zend.assertions=1 -d assert.exception=1 tests/test_finance_cards.php
- php -d zend.assertions=1 -d assert.exception=1 tests/test_finance_cards_access.php

A suíte geral prepara um banco descartável antes de cada processo. Suítes antigas que usam vtd_test.sqlite continuam limitadas a esse arquivo de testes. Nenhum teste novo depende de produção.

O GitHub Actions contém uma execução adicional de cartões em MySQL, limitada ao banco descartável vtd_test com VTD_CARDS_TEST_MYSQL=1. MySQL não está instalado na máquina desta execução; sua validação permanece pendente no CI.

## Aceitação manual

1. Criar cartão, pesquisar instituição e recarregar para confirmar persistência.
2. Preencher lançamento, voltar/avançar, usar Enter e criar categoria sem perder dados.
3. Registrar R$ 100,00 em três parcelas: R$ 33,34 + R$ 33,33 + R$ 33,33.
4. Conferir fechamento, vencimento e parcelas de dezembro/janeiro e fevereiro bissexto.
5. Pagar a primeira fatura e confirmar saída única e liberação de R$ 33,34 no limite.
6. Repetir pagamento e confirmar ausência de duplicação.
7. Tentar alterar/cancelar compra com parcela paga e confirmar bloqueio.
8. Validar filtros, CSV, agenda e resumo, incluindo data efetiva em outro mês.
9. Testar plano Essential, CSRF inválido e tentativas de acessar outra loja.
10. Conferir notebook e celular sem rolagem horizontal da página.

Prévia local preparada com cópia temporária em http://127.0.0.1:8095/#/finance/cards. Os registros de teste ficam nessa cópia, não no banco original.

Resultados locais: 15 testes Node e 14 suítes PHP aprovados. O CSV também foi baixado pela interface e conferido. As nove telas passaram pela verificação de largura no celular; homologação MySQL permanece pendente.
