# VTD · adaptação do UP System

Origem: design system Software UP — Yellow Glass fornecido pelo usuário em 30/10/2026.

- `tokens.json` e `tokens.css`: paleta, escalas, tipografia e tokens originais.
- `fonts/`: fontes locais Inter/Inter Display e licença OFL.
- `vtd.css`: adapta os componentes existentes aos tokens semânticos do UP.
- `dashboard.css`: composição dos dashboards do lojista e administrador, login e detalhes responsivos.

A ordem de carregamento é: estilos legados → tokens.css → vtd.css → dashboard.css. O tema anterior `vtd-premium.css` não é carregado pelo aplicativo.

O tema padrão é claro, com cinza frio, branco, amarelo de ação e sidebar escura. A logo VTD é preservada. Indicadores e estados do dashboard usam os dados existentes da API. A arte de caixas e setas é decorativa e feita em CSS/SVG; não representa métricas.

Para validar: `node --test tests/frontend.test.cjs`. Verificar também o layout em desktop e celular, menu móvel, navegação, checklist e foco de teclado.
