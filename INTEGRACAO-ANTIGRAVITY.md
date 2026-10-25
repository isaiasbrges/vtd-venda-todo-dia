# Continuação no Antigravity

Cole a orientação abaixo com esta pasta aberta:

> Analise este front completo do VTD e preserve sua identidade visual, rotas e conteúdo. O sistema atual é uma demonstração em HTML/CSS/JavaScript com localStorage. Primeiro apresente o plano de integração; depois implemente por etapas, sem redesenhar as telas.
>
> Backend desejado: PHP e MySQL via PDO, com configurações fora da área pública. Organize autenticação, regras de acesso, validação, persistência e tratamento de erros em camadas separadas.
>
> Importe os 234 fornecedores, 8 capítulos e 12 meses de `data.js`. Substitua localStorage por chamadas à API. Conteúdos compartilhados e dados particulares devem ficar separados. Financeiro deve pertencer a uma loja/organização e cada consulta deve validar o usuário autorizado no servidor.
>
> Ofertas: `essential` libera fornecedores, favoritos, calendário e guia; `plus` libera também financeiro. Permissões reais devem ser verificadas em toda rota da API. O seletor demonstrativo e os botões para entrar como admin precisam ser retirados do fluxo de produção.
>
> Implemente sessões seguras, hash de senha, recuperação com token temporário, proteção CSRF, consultas preparadas e limitação de tentativas. Não copie a simulação como autenticação real.
>
> Implemente primeiro login e leitura dos conteúdos, depois favoritos/checklists/progresso, financeiro e administração. Integração de checkout será a última etapa, após escolha do provedor. Não invente preço, segredo ou link.
>
> Valide regras de centavos inteiros, saldo realizado/projetado, datas efetivas de recebimento/pagamento e isolamento entre lojas. Defina histórico de alterações financeiras e estratégia de backup. Preserve a edição de conteúdos e os cadastros administrativos.
>
> Entregue migrações, documentação de configuração e testes de integração dos fluxos críticos. Confira desktop e mobile antes de concluir cada etapa.

## Grupos de API sugeridos (contrato a definir)

- Sessão e recuperação: `/api/auth/*`
- Conta, loja e permissões: `/api/me`
- Fornecedores e favoritos: `/api/suppliers`, `/api/favorites`
- Calendário e progresso: `/api/calendar`, `/api/checklists`
- Capítulos, notas e conclusão: `/api/chapters`, `/api/reading-progress`, `/api/notes`
- Financeiro: `/api/finance/transactions`, `/api/finance/categories`, `/api/finance/reports`
- Administração: `/api/admin/*`
- Checkout e eventos: `/api/webhooks/{provedor}` com assinatura validada e idempotência.

Esses endpoints são orientações de integração e NÃO existem neste ZIP.
