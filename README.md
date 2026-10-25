# VTD — Front completo

Interface demonstrativa em HTML, CSS e JavaScript, sem build ou dependências npm.

## Como abrir

1. Extraia o ZIP mantendo a pasta `assets` junto dos arquivos HTML.
2. Abra `index.html` no navegador. Se o navegador limitar o armazenamento em arquivos locais, use um servidor local.
3. No Antigravity, abra a pasta extraída e utilize a prévia/servidor estático de sua preferência.
4. Alternativa com Python instalado: execute `python -m http.server 8080` na pasta e visite `http://localhost:8080`.
5. Escolha **Explorar Kit + Financeiro**, **Explorar Kit Essencial** ou **Conhecer a administração**. Não é necessário usuário ou senha.
6. A página comercial está em `vendas.html`.

## Arquivos

- `index.html`: entrada da plataforma.
- `styles.css`: identidade visual, componentes e layout responsivo.
- `app.js`: navegação, telas, formulários, cálculos e persistência demonstrativa.
- `data.js`: base de 234 fornecedores, 12 meses e 8 capítulos dos materiais enviados.
- `assets/`: imagens WebP usadas pela plataforma e pela página comercial.
- `vendas.html`: página de vendas com duas ofertas e checkout ainda não configurado.
- `INTEGRACAO-ANTIGRAVITY.md`: orientação para implementar o backend.

## Telas e funções

### Acesso e conta

- Entrada demonstrativa das duas ofertas e da administração.
- Recuperação de acesso simulada: não envia mensagens.
- Perfil local, alternância da oferta, exportação JSON e restauração dos dados iniciais.

### Kit

- Início com links para os materiais e checklist.
- Base completa de 234 fornecedores com busca, filtros, paginação, favoritos e contatos.
- Links externos levam aos canais que constam no material original. Dados não foram revalidados.
- Calendário com 12 meses, sugestões e checklists persistentes.
- Guia com 8 capítulos, tarefas, anotações e conclusão.
- Ajuda com orientação de uso.

### Financeiro

- Dashboard calculado a partir dos lançamentos.
- Saldo inicial, entradas recebidas, saídas pagas, saldo realizado e projetado.
- Entradas e saídas com criação, edição e exclusão confirmada.
- Contas pendentes a pagar e receber, registro de quitação com data efetiva.
- Categorias, filtro de mês, gráficos e exportação CSV.
- Exemplos financeiros em outubro de 2026; são fictícios.
- Sem estoque, PDV, emissão fiscal ou integração bancária.

### Administração

- Visão geral e edição dos fornecedores.
- Cadastro de clientes fictícios, simulação de oferta e suspensão/liberação.
- Edição em texto simples dos capítulos e tarefas.
- Edição das orientações e dos produtos de cada mês.
- Não há upload de imagens nem edição dos banners nesta versão.

## Limites desta entrega

Este pacote é o FRONT, e não um sistema de produção. Todos os dados estão no navegador, sob a chave `vtd-front-v1` do localStorage. A mesma base local é compartilhada entre os modos demonstrativos. Não existe banco, API, autenticação, separação entre lojas, webhook ou processamento de pagamento. Esconder o módulo financeiro no Essencial é apenas uma simulação visual.

Não coloque dados reais nesta demonstração. O código e os materiais são entregues ao navegador; precisam ser protegidos pelo backend antes de qualquer comercialização. A recuperação de senha não manda e-mail. O admin não possui autenticação.

A fonte é solicitada ao Google Fonts quando há internet; sem conexão, a interface utiliza fontes alternativas do sistema. As imagens são locais. A página de vendas contém apenas prévias ilustrativas de telas e botões de compra indisponíveis até configurar valores e checkout.

## Regras financeiras da demonstração

Valores monetários são armazenados em centavos inteiros. Apenas lançamentos realizados alteram o saldo atual. Pendentes compõem a projeção. O saldo inicial em Minha conta é anterior a todos os lançamentos; o saldo de abertura de cada mês inclui o acumulado realizado nos meses anteriores. Ao quitar uma conta, informe a data efetiva: ela passa a compor o realizado daquele mês. Não há suporte a transferências entre contas nesta versão.

## Verificações executadas

- Sintaxe dos dois arquivos JavaScript.
- Navegação e layout desktop; layout inicial mobile sem overflow horizontal em 375 px.
- Busca WIWU, inclusão nos favoritos e exibição dos contatos.
- Checklist de novembro, anotação e persistência após recarregar.
- Saldo inicial de R$ 4.700,00 atualizado para R$ 4.800,00 após entrada realizada de R$ 100,00.
- Bloqueio demonstrativo do financeiro ao alternar para Kit Essencial.
- Cadastro de cliente fictício na administração.

As alterações feitas durante a verificação não estão incorporadas ao pacote; os exemplos iniciais permanecem no código.
