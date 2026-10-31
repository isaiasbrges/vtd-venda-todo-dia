# VTD Black Friday — landing de venda

Versão local de 31/10/2026. Oferta aprovada: **R$ 197 por seis meses, pagamento único**, com controle financeiro manual incluído. Nenhuma publicação foi executada. A hero, os botões de compra e o CTA fixo mobile não exibem preço; o valor permanece na seção da oferta e nas condições comerciais.

## Arquivos e organização

No repositório, a fonte é `vendas.html` e a versão publicada em `/lp/` é gerada em `lp/index.html` por `node tools/landing/sync-pages.cjs`. O aplicativo continua usando seu próprio `index.html`. O pacote independente usa `landing-vtd/index.html`, com os mesmos caminhos relativos.

- `assets/landing/black-friday.css`: estilos exclusivos da landing, sem alterar o design system do painel.
- `assets/landing/black-friday.js`: galeria, filtros da amostra, menu e controles do vídeo.
- `assets/images/`: dez capturas reais do VTD, versão menor do dashboard, logo otimizada, poster e imagem de compartilhamento.
- `assets/videos/vtd-demonstracao.mp4`: gravação real de 56,28 segundos, H.264, 1920×1080, sem áudio, aproximadamente 1,7 MB e com faststart.
- `assets/videos/vtd-demonstracao.vtt`: descrições das etapas da demonstração.
- `assets/icons/`: favicon, ícone Apple e símbolos SVG dos controles.
- `assets/logo-vtd.png`: logo original preservada.
- `design-system/fonts/`: duas fontes locais Inter e sua licença OFL.
- `validacao.json`: resultados dos testes de navegador da versão entregue.

## Publicação na Hostinger

O ZIP contém somente a landing e seus assets. Não contém banco, credenciais, configurações PHP, código de API nem autenticação.

**Proteja a aplicação existente:** não substitua o `public_html/index.html` do sistema pelo `index.html` deste pacote.

Para manter o endereço da landing utilizado pelo código (`https://vendatododia.online/vendas.html`):

1. Faça backup dos arquivos da landing atual e seus assets.
2. Envie o conteúdo de `landing-vtd/index.html` com o nome **vendas.html** em `public_html`.
3. Mescle as pastas de assets enviando apenas os arquivos do pacote e preservando os demais arquivos do sistema. Faça o mesmo com as fontes, sem apagar o design system existente.
4. Verifique os itens comerciais abaixo antes de disponibilizar a oferta.
5. Confira a página, o vídeo, as imagens e o checkout no domínio definitivo.

Também é possível hospedar o pacote inteiro em uma subpasta, por exemplo `public_html/black-friday/`. Nesse caso, mantenha o nome `index.html` e **ajuste canonical, og:url e URLs públicas de og:image/JSON-LD para o endereço definitivo**. A imagem de compartilhamento deve apontar para o caminho em que foi enviada. Os links do aplicativo continuam absolutos para o domínio principal.

Não foi criado redirecionamento e não foi feita alteração de DNS, webhook, ofertas Greenn ou produção. A publicação depende da aprovação do proprietário.

## Vídeo do Matheus e identidade do player

Fonte autorizada: `https://youtube.com/shorts/Z13Tw3LQ97E`. Incorporação oficial pelo domínio `youtube-nocookie.com`, utilizando a IFrame API.

- Proporção vertical 9:16, largura discreta no desktop e responsiva no celular.
- Início automático **sem som**, quando a seção se aproxima da área visível. O navegador ainda pode bloquear autoplay.
- Capa, moldura, controles, botão de áudio e barra de progresso com identidade VTD.
- Play/pause, áudio, busca de tempo e tela cheia controlam o vídeo real pela API.
- Os controles nativos ficam desativados pelo parâmetro oficial `controls=0`.
- A área do vídeo não é cortada, ampliada artificialmente ou coberta para esconder marcas.
- Elementos do YouTube ainda podem aparecer em determinadas situações (pausa, avisos, finalização). A incorporação **não garante remoção de toda marca externa**. Para um player totalmente próprio, é necessário fornecer o arquivo original autorizado e hospedá-lo como mídia local.
- O link para o vídeo original permanece disponível como fallback. Nenhum download do YouTube foi feito.

Referências oficiais: https://developers.google.com/youtube/player_parameters e https://developers.google.com/youtube/iframe_api_reference.

O vídeo demonstrativo local é um segundo vídeo, independente da apresentação do Matheus. Ele usa controles nativos e `preload="none"`; só carrega quando solicitado. O servidor estático da publicação deve servir MP4 e permitir requisições HTTP Range para avanço na linha do tempo. O servidor PHP embutido de desenvolvimento não oferece a mesma resposta de intervalos do servidor de hospedagem; o pacote é verificado também em servidor estático com Range.

## Origem das imagens e gravação

Capturas produzidas com Playwright no VTD real, em navegador isolado, usando banco SQLite temporário e conta fictícia. Não foram usados dados financeiros, credenciais nem informações pessoais de clientes reais. Os contatos de fornecedores são os registros comerciais existentes no material do produto.

A demonstração mostra dashboard, busca/filtros/favoritos, calendário/checklists, guia/tarefas/anotações, financeiro e a oferta. As faixas de texto na gravação são legendas editoriais, não recursos adicionados à interface do sistema. Gravação com Playwright; conversão/compressão e poster com FFmpeg. Não foi usada música.

A logo foi mantida; foram geradas versões recortadas e redimensionadas do arquivo original. As imagens são WebP e a imagem de compartilhamento é JPEG 1200×630. A composição de apresentação usa as capturas reais, sem inventar telas.

## Pendências comerciais antes da publicação

1. **Preço no checkout:** o endereço oficial respondeu HTTP 200, mas em 31/10/2026 ainda apresentou **R$ 97,00**. O proprietário confirmou manter **R$ 197 na landing** e ajustar o checkout. Revalidar a correspondência antes de vender. Não foi enviado formulário nem realizada compra.
2. **Liberação de acesso:** conferir no painel Master/Greenn o mapeamento desta oferta para o produto/plano que concede **Plus por seis meses**, pois o financeiro exige Plus. Nenhuma configuração dessa integração foi alterada. Validar a jornada de pagamento e entrega em ambiente autorizado antes do lançamento.
3. Confirmar contato oficial de suporte, termos de uso, privacidade, início do prazo e regras de renovação. Não foram inventados contatos, horários, garantias ou condições. A página encaminha dúvidas aos contatos disponibilizados no checkout/compra; links legais específicos aguardam definição.
4. Capturas representam o código local atual. Publicar primeiro os recursos efetivamente contratados e conferir sua disponibilidade no ambiente que atenderá compradores. A landing agora mostra cartões/faturas e o formulário de lançamento existentes no código local. Antes de publicar essas telas, aplicar a migração 004 e homologar o módulo descrito em `docs/FINANCEIRO-CARTOES-DEPLOY.md` no ambiente que atenderá compradores. Conexão bancária automática não é oferecida.

## Testes

Verificação de navegador com Playwright/Chrome em sessão sem autenticação:

- 1920×1080, 1440×900, 1366×768, 1024×768, 768×1024, 390×844, 375×812 e 360×800.
- Ausência de rolagem horizontal, recortes laterais e distorção do player vertical; CTA principal visível na primeira dobra dos notebooks.
- Os quatro CTAs de entrada levam à seção de pagamento (`#pagamento`) na própria página. Somente o botão “FINALIZAR MINHA COMPRA” dessa seção abre o checkout oficial; abertura testada por interceptação e acesso externo conferido separadamente, sem comprar.
- Filtros da amostra, teclado, galeria ampliada, anterior/próxima, Escape, retorno de foco, dez perguntas do FAQ e menu mobile.
- YouTube real: reprodução automática sem som, pausar/reproduzir, áudio, busca de tempo e tela cheia.
- Reprodução, duração e avanço do vídeo demonstrativo local; integridade dos assets e suas respostas HTTP.
- Página pública e links/FAQ utilizáveis sem JavaScript; respeito a movimento reduzido.
- Sem erros JavaScript da página ou assets locais com erro nas verificações registradas.

Também passaram os 15 testes existentes do frontend (`node --test tests/frontend.test.cjs tests/finance-ui.test.cjs`) e a verificação de sintaxe do JavaScript da landing. Esta página é estática: não há etapa de build ou dependências de runtime.

Os testes não equivalem a uma compra real nem certificam a integração de pagamento/entrega em produção. O relatório final registra os resultados do pacote extraído. Testes reais em iPhone/Safari físico e múltiplas redes não foram realizados.

Para repetir os testes no repositório, use `tools/landing/verify.cjs`, com Playwright instalado em ambiente de desenvolvimento e as variáveis `PLAYWRIGHT_MODULE` e `CHROME_PATH` se necessário. `tools/landing/preview.cjs` serve apenas o pacote estático exportado em localhost, com suporte a Range; não requer dependências.

## Revisão visual: Matheus, cartões e lançamentos

A seção sobre Matheus utiliza a foto indicada pelo proprietário (`https://matheusdaloja.com/listadefornecedores/assets/matheus-caminhao.jpg`), salva localmente em WebP e em uma versão menor. O texto segue a referência fornecida e o relato existente no Guia; a citação foi fornecida pelo proprietário na imagem de referência. Não foram adicionadas estatísticas ou promessas de resultados.

As novas capturas `gestao-cartoes.webp` e `novo-lancamento.webp` foram feitas na interface real, com dois cartões e uma compra de demonstração em banco temporário isolado. Elas aparecem junto ao financeiro e na galeria ampliável. Não foram alterados cartões, compras ou dados de produção.

A landing bloqueia o menu de contexto e o arraste sobre imagens e links da galeria. `draggable="false"`, estilos de toque e eventos delegados dificultam o salvamento casual. A ampliação, o teclado e a seleção de texto continuam disponíveis. **Isso não é proteção contra clonagem completa:** arquivos públicos ainda podem ser acessados pelo navegador, pelas ferramentas de desenvolvimento ou por captura de tela. Nenhuma promessa de DRM ou de bloqueio absoluto foi feita. A proteção está restrita à página de vendas e não interfere nos formulários financeiros.

A publicação Git mantém o sistema na raiz e atualiza a landing em `/lp/` e `vendas.html`, utilizando assets compartilhados na raiz. O canonical das duas versões aponta para `https://vendatododia.online/lp/`. Não editar `lp/index.html` manualmente: após alterar `vendas.html`, executar o sincronizador. A verificação `--check` no CI detecta uma cópia desatualizada.
