# VTD - Financeiro guiado, cartões e faturas

- Data: 2026-10-31
- Status: Implementação autorizada pelo usuário e concluída localmente; homologação MySQL pendente antes de publicar.
- Pedido: gamificar os formulários de lançamentos, cadastrar cartões de instituições brasileiras com logos e melhorar o financeiro.
- Base: aplicação atual em HTML/CSS/JavaScript e PHP/PDO, mantendo o UP System e a identidade amarela e preta.
- Plano de execução: ../plans/2026-10-31-vtd-financeiro-cartoes.md

## 1. Situação atual

O formulário transactionModal, em app.js, apresenta descrição, valor, tipo, categoria, vencimento e status em uma janela. O financeiro já oferece resumo, lançamentos, contas a pagar/receber, categorias, relatórios, agenda e recorrências.

FinanceController e FinanceService persistem valores em centavos, separam dados por tenant e distinguem vencimento de data efetiva de quitação. Não há entidades de cartão, compra parcelada ou fatura. As melhorias de densidade do Master já existentes na árvore de trabalho devem ser preservadas.

Os documentos de 2026-10-25 são históricos: não executar novamente seu plano de criação do backend. Em especial, preservar a regra atual de manter date como vencimento e settled_at como pagamento, mesmo onde o documento antigo sugira substituir date.

## 2. Experiência de lançamento

Fluxo guiado de três etapas:
1. O que aconteceu: entrada/saída, descrição e valor em reais.
2. Como organizar: categoria e forma de pagamento; cartão e parcelas aparecem somente quando aplicáveis.
3. Conferir e salvar: datas, situação e resumo completo.

- Barra de progresso baseada no preenchimento; etapas acessíveis por teclado e identificadas por texto.
- Voltar não apaga dados; erros aparecem junto ao campo e mantêm o formulário aberto.
- Modo rápido disponível para usuários recorrentes, sem obrigação de percorrer três telas.
- Ao trocar a forma de pagamento, limpar vínculos incompatíveis e explicar a mudança.
- Para compra no cartão, solicitar data da compra e apresentar a fatura calculada. Pagamento é registrado pela fatura.
- Máscara brasileira de moeda; conversão validada para centavos inteiros, sem depender de ponto flutuante para cálculos.
- Impedir salvamento duplicado e só confirmar sucesso após resposta válida do servidor.
- Feedback discreto de conclusão, respeitando redução de movimento.
- Gamificação voltada à organização: progresso do formulário e checklist opcional de revisão dos pendentes. Sem recompensas por valor gasto nem contadores fictícios.
- Não acrescentar metas persistentes ou um sistema de pontos nesta primeira entrega.
- Reutilizar o diálogo atual, campos, botões, tokens e padrões de validação. Não introduzir um framework.

## 3. Cartões e instituições

Nova seção Meus cartões dentro do financeiro.

Campos: instituição, apelido, bandeira, últimos quatro dígitos opcionais, limite em centavos, dia de fechamento, dia de vencimento e estado ativo/arquivado. Permitir vários cartões da mesma instituição.

Controle manual é a premissa desta proposta. Conexão automática com bancos/Open Finance não integra esta entrega. Não solicitar número completo, CVV, senha ou credenciais bancárias.

Catálogo pesquisável por nome e apelidos da marca:
- Referência cadastral: https://bcb.gov.br/meubc/encontreinstituicao
- Distinguir instituição/emissor de bandeira; não assumir que todas as instituições do cadastro emitem cartões.
- Buscar cobertura dos bancos, cooperativas e emissores brasileiros; registrar data e fonte da revisão.
- Manifesto com ID estável, nome, aliases, caminho do logo e fonte oficial da imagem.
- Logos locais, verificados e legíveis nas superfícies do VTD; não depender de carregamento remoto em cada acesso.
- Sem logo validado, usar nome e identificação neutra. A cobertura efetiva de logos deve ser informada, sem anunciar 100% antes da verificação.
- Opção Outra instituição com nome obrigatório; ausência no catálogo não bloqueia cadastro.
- Identidade do banco restrita à representação do cartão e à seleção; estrutura do sistema continua amarela e preta.

Limite disponível é estimado pelos registros cadastrados, inclusive parcelas futuras ainda pendentes. Informar isso na interface. Não apresentá-lo como consulta ao banco.

## 4. Compras, parcelas e faturas

### Regra de contabilização

Cada parcela gera exatamente um lançamento de saída pendente no livro financeiro existente, com categoria e vencimento da fatura. A compra associa essas parcelas e a fatura agrupa seus lançamentos.

Quitar a fatura liquida seus lançamentos pendentes em uma operação atômica, com a mesma data efetiva, sem criar outra despesa pelo total da fatura. Assim:
- compra/parcelas pendentes entram uma vez nas previsões;
- saídas realizadas entram uma vez, na data efetiva de pagamento;
- despesas continuam atribuídas às categorias das parcelas;
- o total da fatura é uma agregação, não uma segunda despesa.

Exemplo de teste: compra de R$ 100,00 em três parcelas resulta em 3334, 3333 e 3333 centavos; soma exata de 10000. Quitar a primeira fatura movimenta 3334 centavos, nunca 6668.

### Ciclos e integridade

- Fechamento inclusivo: compra no dia de fechamento pertence ao ciclo que fecha nessa data; compra posterior segue para o próximo ciclo. Exibir a primeira fatura antes de salvar.
- Vencimento deve ocorrer após o fechamento do ciclo; ajustar dias inexistentes para o último dia do mês.
- Aceitar ajuste explícito da primeira fatura antes do salvamento para acomodar regras do emissor.
- Testar fevereiro, anos bissextos, dezembro/janeiro e datas com dias 29, 30 e 31.
- Guardar datas calculadas nas faturas e parcelas: editar o cadastro do cartão não altera o histórico silenciosamente.
- Primeira versão com pagamento integral do saldo pendente da fatura. Pagamento parcial, rotativo, juros automáticos e estornos exigem regras próprias e ficam para evolução posterior.
- Pagamento repetido e requisições concorrentes não duplicam lançamentos nem auditoria.
- Cartão com histórico é arquivado, não apagado em cascata.
- Compra parcelada tem edição/cancelamento pelo fluxo da compra, com bloqueio se houver parcela paga. Não editar ou excluir parcelas isoladas pelas rotas genéricas.
- Lançamentos antigos continuam sem vínculo com cartão; não inferir cartões a partir da descrição.

## 5. Persistência e contratos propostos

Novas entidades, todas isoladas por tenant:
- financial_credit_cards: cadastro do cartão e dias de ciclo.
- financial_card_purchases: descrição, data, total, quantidade de parcelas e chave de idempotência.
- financial_card_invoices: cartão, período, fechamento, vencimento e estado do ciclo.
- financial_card_installments: compra, número da parcela, fatura e transaction_id único.
- financial_card_invoice_payments: registro da quitação, data efetiva e chave de idempotência; não é uma segunda despesa.

O estado financeiro da fatura deve ser coerente com seus lançamentos e pagamentos. Impedir duplicidade por compra/parcela, cartão/ciclo e chave de operação. Relações entre entidades devem validar o mesmo tenant no servidor; IDs enviados pelo cliente não definem autorização.

Rotas propostas:
- GET /finance/banks
- GET/POST /finance/cards
- PUT /finance/cards/{id}
- POST /finance/cards/{id}/archive
- GET /finance/cards/{id}/invoices
- GET /finance/card-invoices/{id}
- POST /finance/card-purchases
- PUT /finance/card-purchases/{id}
- POST /finance/card-purchases/{id}/cancel
- POST /finance/card-invoices/{id}/settle

Reutilizar PlanMiddleware, sessão, CSRF, auditoria e acesso Plus. Preservar rotas e respostas existentes; acrescentar metadados de cartão sem quebrar clientes anteriores. Quitação de parcelas vinculadas só ocorre pela operação de fatura; impedir bypass pelas rotas genéricas.

## 6. Organização do financeiro

- Resumo compacto: saldo disponível, entradas/saídas realizadas e compromissos pendentes.
- Meus cartões e próximas faturas com atalhos para registrar compra e conferir o ciclo.
- Filtros por categoria, situação, forma de pagamento e cartão, com indicação dos filtros ativos.
- Sinalização textual de atrasos e de pendências; não depender apenas de cor.
- Reutilizar agenda, relatórios e recorrências existentes. Recorrências no cartão não fazem parte da primeira versão.
- Relatórios e CSV discriminam vínculos com cartões sem somar novamente totais das faturas.
- Conferir 1366x768, 1440x900, 1920x1080 e celular; também a altura útil de 607px.
- Manter rolagem principal da página e rolagem horizontal restrita às tabelas quando necessária.

## 7. Migração, verificação e entrega

- Testes apenas com bancos temporários ou ambiente de testes explicitamente isolado.
- Não executar seeds nem o executor genérico de migrações no banco real para instalar esta funcionalidade.
- O Migrator atual repete arquivos SQL, e a migração 003 usa sintaxe MySQL; preparar migração incremental com controle de versão e compatibilidade por driver antes de acrescentar novas tabelas.
- Migração nova não modifica dados de usuários, produtos, autenticação ou integrações existentes.
- Comparar saldos, contagens e lançamentos antigos antes/depois em uma cópia; testar segunda execução sem alterações.
- Validar isolamento entre lojas, bloqueio Essential, CSRF, centavos, ciclos, parcelas e quitação concorrente.
- Entrega inicial: formulário guiado integrado às rotas atuais, sem prometer cartão funcional antes da persistência.
- Entrega seguinte: cartões, compras, faturas e integrações de relatório completas em testes.
- Publicação e execução da migração em produção são uma etapa posterior, com backup e resultado revisável; este documento não executa publicação.

## 8. Decisões para a revisão

Premissas propostas: controle manual; três etapas com modo rápido; fatura paga integralmente na versão inicial; fechamento inclusivo; último dia válido para datas inexistentes; ausência de logo não impede cadastrar a instituição.

Implementação autorizada pelo usuário em 31/10/2026. A autorização não executa publicação nem alterações no banco de produção. Resultados e pendências estão no plano de execução.
