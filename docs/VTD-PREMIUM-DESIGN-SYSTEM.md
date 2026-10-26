# VTD Premium — Dark Glass + Amarelo

## Objetivo
Aplicar o mockup aprovado em todo o VTD sem migrar framework, sem mudar contratos das APIs e sem reescrever os módulos. Compatível com HTML/CSS/JS puro, PHP 8.3 e MySQL 8.

## Tokens principais
- Fundo: `#080b0c`
- Superfície: `#15191b`
- Cards glass: `rgba(22,26,28,.82)`
- Amarelo de ação: `#ffdc18`
- Texto principal: `#f7f8f2`
- Texto secundário: `#9a9fa4`
- Sucesso: `#50d394`
- Erro: `#ff776f`

Todos os tokens e componentes estão em `vtd-premium.css`, carregado **após** os estilos existentes, com exceção dos scripts.

## Alterações
- Sidebar e header com transparência sutil e destaque amarelo nos links ativos.
- Login próprio (sem sidebar/header), dark premium e campos legíveis.
- Homepage com hero e cards dark glass.
- Dashboard financeiro com mensagem personalizada, projeção com dados reais e cards de saldo.
- Agenda, recorrências, lançamentos e relatórios com visual consistente, campos, filtros e tabelas.
- Painel Master com boas-vindas personalizadas, cards de indicadores e status coerentes.
- Tela de clientes, fornecedores, conta e módulos existentes recebem a mesma camada de componentes.
- Animações discretas na entrada e estados de hover, respeitando `prefers-reduced-motion`.
- Layout responsivo com tratamento para 320px e up.
- Corrigida sintaxe já quebrada no `app.js` (trecho duplicado/incompleto do handler de recorrências).

## Deploy na Hostinger
1. Faça backup dos arquivos atuais.
2. Publique o ultimo commit do repositório incluindo: `index.html`, `vtd-premium.css`, `app.js` e `master.js`.
3. Não há migração de banco para este redesign. **Não execute novamente o SQL do Financeiro V2**.
4. Não sobrescreva `vtd-runtime/config.php`; senhas e dados de SMTP não foram alterados.
5. Atualize a página com cache limpo após o deploy; `index.html` já contém versão nos links críticos.

## Checklist de aceitação
- Login e recuperação de senha com layout mobile/desktop.
- Dashboard do lojista, navegação de fornecedores, guia e calendário comercial.
- Financeiro: carregamento da visão geral, cartões, gráfico e avisos de vencimento.
- Financeiro: adicionar/editar/quitar lançamento, gerar uma recorrência duas vezes (sem duplicar).
- Agenda: alternar mês, visualizar os compromissos nos dias corretos.
- Master: abrir visão geral, clientes, integrações e logs.
- Modais: abertura, salvamento, fechamento, erro de validação.
- Visualizar telas a 320, 375, 768, 1024 e 1440px.
- Testar navegação por teclado e opção de movimento reduzido.
- Conferir console JS e logs PHP após o deploy.

## Cuidados
Este trabalho é predominantemente visual; métricas exibidas continuam vindo da API e não foram substituídas por dados de demonstração. Ainda é necessária validação funcional em ambiente de homologação antes de liberar a implantação a todos os clientes.
