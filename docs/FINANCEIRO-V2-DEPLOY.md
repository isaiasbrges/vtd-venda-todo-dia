# VTD Financeiro V2 — Implantação Hostinger

## Escopo
- Sem alteração nas contas existentes, usuários, senhas e integração Greenn.
- PHP 8.3 / MySQL 8, frontend JavaScript puro.
- Data `date` = vencimento/competência; `settled_at` = data real de quitação.
- Entradas e saídas realizadas seguem `settled_at` (recuo para `date` em registros históricos).
- Agenda de pendências por vencimento, indicador de atrasados, alerta de saldo previsto negativo.
- Recorrências mensais manuais, geração por competência e proteção contra duplicação.

## Banco de dados
1. Faça backup completo do MySQL e dos arquivos.
2. Abra phpMyAdmin na base do VTD.
3. Aplique **uma única vez** o script `backend/migrations/003_finance_v2.mysql.sql`.
4. Confira que existem `financial_recurring_rules`, `financial_transactions.recurrence_rule_id` e os novos índices.
5. **Não execute** `runSeeds()` em produção — ele inclui usuários de demonstração.

O novo script é para MySQL, não deve ser usado em SQLite. Como utiliza ALTER e CREATE INDEX, **não é idempotente**: não execute novamente após sucesso.

## Deploy
Depois de aplicar SQL, publique juntos:
- `backend/src/Services/FinanceService.php`
- `backend/src/Controllers/FinanceV2Controller.php`
- `backend/src/Controllers/FinanceController.php` (mantido compatível)
- `api.php`
- `app.js`
- `finance-v2.css`
- `index.html`

Preserve `vtd-runtime/config.php` fora do diretório público. Se o navegador estiver usando cache antigo, recarregue com Ctrl+F5.

## Funcionamento
- Financeiro → Agenda: calendário, vencidos, próximos compromissos, indicadores.
- Financeiro → Recorrências: cadastrar despesa/receita mensal, gerar os lançamentos do mês e desativar.
- Lançamento recorrente é inicialmente `pending`, editável e quitável pelo fluxo existente.
- Gerar novamente no mesmo mês não duplica a mesma regra e vencimento.
- Vencimentos nos dias 29–31 ajustam automaticamente para o último dia de meses curtos.
- Desativar recorrência não apaga lançamentos já gerados.
- Geração é por clique no mês escolhido, **não** executada por cron de background.

## Testes de aceitação manuais
1. Criar uma categoria e depois uma despesa; confirmar que categoria de outro tenant é rejeitada.
2. Criar uma despesa vencendo em setembro, quitá-la em outubro: o saldo de outubro reflete o pagamento; vencimento de setembro permanece na ficha do lançamento.
3. Criar uma receita paga em data histórica e confirmar saldo daquele mês.
4. Criar recorrência de R$ 100, dia 31; gerar fevereiro e conferir vencimento no último dia.
5. Clicar 'Gerar' duas vezes e confirmar apenas um lançamento na mesma competência.
6. Trocar o mês no seletor: agenda e recorrências permanecem coerentes.
7. Desativar recorrência, mudar mês e confirmar que não há novas gerações.
8. Atrasar um lançamento pendente e conferir alerta e contagem.
9. Testar usuários de lojas diferentes e confirmar isolamento.
10. Conferir visual do calendário em celular (320 px) e computador.

## Limitações e próximos incrementos
- A agenda hoje destaca os compromissos pendentes com datas até o fim do mês selecionado; histórico antigo pode surgir como vencido.
- O calendário mostra vencimentos pendentes e não substitui um extrato contábil completo.
- Não há envio automático de alertas por e-mail ou WhatsApp; apenas alertas no dashboard.
- A API usa proteção existente de sessão/CSRF e segregação por tenant.
- Para deduplicação absolutamente segura em execução concorrente, o índice único de regra/data no banco é obrigatório.
- Validar em homologação antes de aplicar no banco de produção.
