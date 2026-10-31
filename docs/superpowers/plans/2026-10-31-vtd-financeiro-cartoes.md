# VTD - Plano de execução do financeiro guiado e cartões

- Data: 2026-10-31
- Status: Implementado e verificado localmente; homologação MySQL e testes concorrentes pendentes antes da publicação.
- Especificação: ../specs/2026-10-31-vtd-financeiro-cartoes-design.md
- Objetivo: melhorar lançamentos e organização financeira, acrescentando cadastro manual de cartões, parcelas e faturas, sem perda de dados.
- Método: tarefas pequenas, testes de comportamento antes da lógica nova e verificação após cada entrega. Não reexecutar o plano histórico de criação do backend.

## Restrições globais

- Preservar UP System, identidade visual, navegação existente e ajustes locais do Master.
- Valores em centavos inteiros e todos os acessos financeiros isolados por tenant.
- Reutilizar middleware de plano, autorização, CSRF, cliente de API e auditoria.
- Não conectar bancos, recolher credenciais nem publicar durante a preparação.
- Novas migrações somente após validação em cópia isolada; nunca rodar seeds na produção.
- Não reescrever app.js ou substituir o frontend por outro framework.

## Tarefa 1 - Congelar a referência e preparar testes isolados

Arquivos: tests/frontend.test.cjs, tests/bootstrap.php (novo), tests/test_finance_cards.php e tests/test_finance_cards_access.php (novos), docs/FINANCEIRO-CARTOES-DEPLOY.md (novo).

- [x] Registrar estado atual dos testes, saldos e comportamentos de vencimento/quitação.
- [x] Preparar fixtures independentes de produção, com duas lojas Plus e uma Essential.
- [x] Diferenciar falhas anteriores das novas; verificar a incompatibilidade da migração 003 com SQLite.
- [x] Documentar critérios de aceite e os comandos de testes aplicáveis a cada driver.

Aceite: testes da funcionalidade nova não abrem configuração real nem dependem de seeds ou credenciais de produção.

## Tarefa 2 - Entregar o formulário guiado com o contrato atual

Arquivos: app.js, finance-ui.js (novo), finance-v2.css, index.html, tests/frontend.test.cjs.

- [x] Escrever testes para ida/volta entre etapas, preservação de campos, erros e bloqueio de envio repetido.
- [x] Implementar estado e apresentação do formulário em finance-ui.js, usando componentes existentes.
- [x] Integrar transactionModal e submissão em app.js sem alterar os endpoints atuais.
- [x] Acrescentar modo rápido, resumo, valores brasileiros e feedback de conclusão.
- [x] Tornar categorias acessíveis durante o fluxo sem perder o lançamento ainda não salvo.
- [x] Mostrar a opção cartão somente após sua persistência e APIs estarem disponíveis.
- [x] Conferir teclado, leitor de tela, redução de movimento e proporções de notebook/celular.

Aceite: criar e editar entrada/saída preserva comportamento, dados e tratamento de erro existentes. Interface do cartão não simula salvamento.

## Tarefa 3 - Preparar a migração incremental de cartões

Arquivos: backend/src/Database/Migrator.php, backend/migrations/004_finance_cards.sql (novo, ou variantes por driver se necessárias), tests/test_finance_cards.php (novo), docs/FINANCEIRO-CARTOES-DEPLOY.md.

- [x] Escrever testes de atualização de banco existente, instalação limpa e segunda execução.
- [x] Acrescentar controle de versão/aplicação que reconheça instalações anteriores e não repita ALTER da migração 003.
- [x] Criar entidades, índices, relações e restrições descritos na especificação, preservando tabelas e registros existentes.
- [ ] Validar comportamento real em MySQL/MariaDB e SQLite, incluindo falha parcial de DDL e retomada.
- [x] Conferir saldos e contagens anteriores/posteriores; registrar procedimento de backup e reversão compatível com dados novos.

Aceite: nenhuma perda ou mudança de saldo dos lançamentos anteriores; aplicação repetida não duplica estrutura ou dados. Não presumir que DDL MySQL é reversível por rollback.

## Tarefa 4 - Catálogo de instituições e cadastro persistente de cartões

Arquivos: assets/banks/ (novo), assets/banks/manifest.json (novo), backend/resources/banks.json (novo), backend/src/Controllers/FinanceCardsController.php (novo), backend/src/Services/FinanceCardsService.php (novo), api.php, finance-ui.js, app.js, finance-v2.css, tests/test_finance_cards.php.

- [x] Definir IDs estáveis e coletar instituições/emissores a partir da referência do Banco Central.
- [x] Verificar logos oficiais, fontes, variantes e cobertura; separar bandeira de instituição.
- [x] Implementar pesquisa com aliases, logo local e alternativa neutra; permitir Outra instituição.
- [x] Testar isolamento, plano Essential, IDs de outra loja e validações de dias/valores.
- [x] Implementar listar/criar/editar/arquivar cartões com persistência e mensagens reais da API.
- [x] Criar a seção Meus cartões e o seletor dentro do lançamento.
- [x] Impedir gravação de número completo, CVV, senha ou dados não previstos no contrato.

Aceite: cartão salvo reaparece ao recarregar e em outra sessão da mesma loja; nenhuma outra loja o acessa. Publicar a cobertura verificada, sem alegar logos de todos sem conferência.

## Tarefa 5 - Motor de ciclos e parcelas

Arquivos: backend/src/Services/FinanceCardsService.php, backend/src/Services/FinanceService.php, backend/src/Controllers/FinanceCardsController.php, backend/src/Controllers/FinanceController.php, api.php, tests/test_finance_cards.php, tests/test_finance_module.php.

- [x] Testar centavos indivisíveis, parcelas futuras, ano bissexto e virada de ano.
- [x] Testar compra antes/no/depois do fechamento, inclusive escolha explícita da primeira fatura.
- [x] Gerar compra, parcelas e lançamentos pendentes em uma única transação.
- [x] Garantir que a soma das parcelas seja exatamente o valor da compra.
- [x] Preservar vencimento e data efetiva como conceitos separados.
- [x] Implementar edição/cancelamento de compra sem parcelas pagas e proteção das rotas genéricas.
- [ ] Confirmar repetição e concorrência sem duplicações; calcular limite comprometido considerando parcelas futuras.

Aceite: compra de 10000 centavos em três vezes produz 3334 + 3333 + 3333, sem movimentação realizada antes da quitação.

## Tarefa 6 - Faturas e pagamento integral

Arquivos: backend/src/Services/FinanceCardsService.php, backend/src/Controllers/FinanceCardsController.php, api.php, finance-ui.js, app.js, finance-v2.css, tests/test_finance_cards.php, tests/frontend.test.cjs.

- [x] Testar totalização por ciclo e apresentação de compras/parcelas da fatura.
- [x] Implementar consulta de faturas e tela de detalhe.
- [x] Quitar os lançamentos pendentes da fatura atomicamente, registrando pagamento e auditoria.
- [ ] Testar repetição, concorrência, tentativa de outra loja e mudança de mês da data efetiva.
- [x] Bloquear inclusão/alteração silenciosa em fatura quitada.
- [x] Manter diálogo aberto se ocorrer erro; atualizar saldo, fatura e limite somente após sucesso.

Aceite: quitar uma fatura de 3334 centavos acrescenta 3334 às saídas realizadas uma única vez, preserva a categoria e não cria despesa extra pelo total.

## Tarefa 7 - Integrar dashboard, filtros, agenda e relatórios

Arquivos: app.js, finance-ui.js, finance-v2.css, backend/src/Services/FinanceService.php, backend/src/Controllers/FinanceController.php, backend/src/Controllers/FinanceV2Controller.php (somente se necessário), tests/frontend.test.cjs, tests/test_finance_cards.php.

- [x] Exibir próximos vencimentos e faturas em composição compacta, reutilizando os indicadores existentes.
- [x] Integrar filtros por categoria, forma de pagamento e cartão, preservando filtros atuais.
- [x] Identificar parcelas na listagem e na agenda sem acrescentar uma despesa agregada da fatura.
- [x] Acrescentar informações de cartão/parcelas ao CSV e validar totais com os relatórios.
- [x] Diferenciar realizado de projetado e limite estimado de consulta bancária.
- [x] Verificar estados vazios, carregamento, falha e reenvio.

Aceite: totais de resumo, agenda, listagem e CSV conciliam sem somar novamente a fatura.

## Tarefa 8 - Revisão completa e preparação para publicação

Arquivos: tests/frontend.test.cjs, tests/test_finance_cards.php, tests/test_finance_cards.php, tests/run_all_tests.php, docs/FINANCEIRO-CARTOES-DEPLOY.md, README.md.

- [x] Executar node --check nos scripts alterados e node --test tests/frontend.test.cjs.
- [x] Validar sintaxe PHP e testes com assert ativo em banco isolado.
- [ ] Executar suítes financeiras e regressões pertinentes em MySQL e SQLite; registrar qualquer falha anterior separadamente.
- [x] Conferir 1366x768, 1440x900, 1920x1080, 390x844, largura de 320px e altura útil de 607px.
- [x] Validar o percurso completo: cadastrar cartão, recarregar, criar compra parcelada, conferir faturas, quitar e conciliar saldo.
- [x] Confirmar acesso Plus, bloqueio Essential e separação entre lojas.
- [x] Revisar os logos/cobertura e registrar capturas da experiência final.
- [x] Preparar diff, resultado dos testes, backup/migração e roteiro de publicação para revisão.

Aceite: resultado revisável e funcional em ambiente isolado. Nenhuma publicação ou migração em produção é executada por este documento.

## Pontos de revisão

Primeira entrega revisável: Tarefa 2. Cartões e faturas só são considerados concluídos após Tarefas 3 a 7 e a verificação da Tarefa 8. Nenhuma caixa deve ser marcada sem evidência de execução.

## Evidências locais

- Novo formulário validado no navegador: avançar/voltar, Enter, criação de categoria preservando descrição/valor e salvamento.
- Cartão criado pela interface e mantido após recarregar.
- Compra de R$ 100,00 dividida em 3334, 3333 e 3333 centavos; primeira parcela quitada em 2026-11-06.
- Listagem de novembro mostra apenas R$ 33,34 de saída dessa compra. Carteira mantém R$ 66,66 comprometidos.
- CSV baixado pelo navegador validado com cartão, parcela 1/3 e data efetiva. Corrigido aviso de escape do fputcsv em PHP 8.4 e acrescentado teste de regressão.
- 15 testes Node e 14 suítes PHP disponíveis; resultados finais registrados no guia de instalação.
- Nove telas financeiras verificadas em 1366x768, 1440x900, 1920x1080, 390x844 e 320x568; corrigidos os excessos de largura no celular.
- Catálogo contém 468 instituições, 50 logos locais verificados e nenhuma referência a imagem ausente.
- Sem instalação local de MySQL/MariaDB: execução específica foi adicionada ao GitHub Actions e permanece pendente. Idempotência sequencial foi testada; bloqueios/restrições foram implementados, mas ensaio concorrente em MySQL ainda deve ser executado.
- Banco original, configuração privada e site de produção não foram modificados. Prévia com cópia temporária: http://127.0.0.1:8095/#/finance/cards.
