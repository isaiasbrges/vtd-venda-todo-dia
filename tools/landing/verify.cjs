/* Teste de navegador da landing, sem login e sem alterações em APIs/bancos.
 * Requer Playwright já disponível. Configure PLAYWRIGHT_MODULE se não estiver no projeto.
 * node tools/landing/verify.cjs http://127.0.0.1:8095/vendas.html caminho-dos-resultados
 */
const assert=require('node:assert/strict');
const fs=require('node:fs/promises');const path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const BASE=process.argv[2]||'http://127.0.0.1:8095/vendas.html';
const OUT=path.resolve(process.argv[3]||'landing-qa');
const CHECKOUT='https://payfast.greenn.com.br/6sxyvkf';
let activeBrowser;
const sizes=[[1920,1080],[1440,900],[1366,768],[1024,768],[768,1024],[390,844],[375,812],[360,800]];
(async()=>{
 await fs.mkdir(OUT,{recursive:true});
 const browser=activeBrowser=await chromium.launch({headless:true,...(process.env.CHROME_PATH?{executablePath:process.env.CHROME_PATH}:{})});
 const context=await browser.newContext({viewport:{width:1366,height:768},locale:'pt-BR'});
 const page=await context.newPage();const errors=[],badResponses=[],results=[];
 page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.url().startsWith(new URL(BASE).origin)&&r.status()>=400)badResponses.push({url:r.url(),status:r.status()});});
 async function open(){await page.goto(BASE,{waitUntil:'domcontentloaded'});await page.evaluate(()=>document.fonts.ready);}
 for(const [width,height]of sizes){
  await page.setViewportSize({width,height});await open();
  await page.evaluate(()=>{for(const img of document.images)img.loading='eager';});
  await page.waitForFunction(()=>[...document.images].filter(i=>i.id!=='lightbox-image').every(i=>i.complete&&i.naturalWidth>0));
  const layout=await page.evaluate(()=>{
   const w=document.documentElement.clientWidth;const clipped=[...document.querySelectorAll('main h1,main h2,main h3,main a,main figure,.header-inner,.purchase-dock')].filter(el=>el.getClientRects().length).filter(el=>{const r=el.getBoundingClientRect();return r.left < -1||r.right>w+1;}).map(el=>({tag:el.tagName,text:el.textContent.trim().slice(0,50),rect:el.getBoundingClientRect().toJSON()}));
   const cta=document.querySelector('.hero .buy').getBoundingClientRect();const stage=document.querySelector('.portrait-stage').getBoundingClientRect();
   return{width:w,scrollWidth:document.documentElement.scrollWidth,clipped,heroCtaBottom:cta.bottom,playerRatio:stage.width/stage.height,headerHeight:document.querySelector('.site-header').offsetHeight,dockVisible:getComputedStyle(document.querySelector('.purchase-dock')).display!=='none'};
  });
  assert.ok(layout.scrollWidth<=width,`Rolagem horizontal em ${width}: ${JSON.stringify(layout)}`);assert.equal(layout.clipped.length,0,JSON.stringify(layout.clipped));assert.ok(Math.abs(layout.playerRatio-9/16)<0.01);
  if(width>=1366)assert.ok(layout.heroCtaBottom<height,`CTA fora da primeira dobra em ${width}`);
  if(width<=700)assert.ok(layout.dockVisible);
  await page.screenshot({path:path.join(OUT,`landing-${width}x${height}.png`),fullPage:true});
  await page.screenshot({path:path.join(OUT,`hero-${width}x${height}.png`)});
  results.push({test:`Layout ${width}x${height}`,ok:true,...layout});
 }
 await page.setViewportSize({width:1366,height:768});await open();
 // CTAs de entrada navegam para o cartão de pagamento na mesma página.
 const paymentLinks=page.locator('[data-payment-link]');const paymentCount=await paymentLinks.count();
 for(let i=0;i<paymentCount;i++){
  const link=paymentLinks.nth(i);assert.equal(await link.getAttribute('href'),'#pagamento');assert.equal(await link.getAttribute('target'),null);
  if(!(await link.isVisible()))await page.setViewportSize({width:390,height:844});
  const tabCount=context.pages().length;await link.click();await page.waitForFunction(()=>{const r=document.querySelector('#pagamento').getBoundingClientRect();return location.hash==='#pagamento'&&r.top>=0&&r.top<120;});assert.equal(context.pages().length,tabCount);
  await page.setViewportSize({width:1366,height:768});
 }
 results.push({test:'CTAs de entrada levam ao pagamento na mesma página',ok:true,count:paymentCount});
 // Apenas o botão do cartão abre o checkout externo, sem enviar formulário.
 await context.route(CHECKOUT,route=>route.fulfill({status:200,contentType:'text/html',body:'<title>Destino de checkout validado</title>'}));
 const ctas=page.locator('[data-checkout]');const count=await ctas.count();assert.equal(count,1);
 for(let i=0;i<count;i++){
  const link=ctas.nth(i);assert.equal(await link.getAttribute('href'),CHECKOUT);assert.equal(await link.getAttribute('target'),'_blank');
  if(!(await link.isVisible()))await page.setViewportSize({width:390,height:844});
  const popupPromise=page.waitForEvent('popup');await link.click();const popup=await popupPromise;await popup.waitForLoadState('domcontentloaded');assert.equal(popup.url(),CHECKOUT);await popup.close();
  await page.setViewportSize({width:1366,height:768});
 }
 results.push({test:'Botão de finalizar compra abre o checkout oficial',ok:true,count});await context.unroute(CHECKOUT);
 await page.locator('#sample-tech').click();assert.equal(await page.locator('.sample-row:visible').count(),2);
 await page.locator('#sample-tech').press('ArrowLeft');assert.equal(await page.locator('#sample-toys').getAttribute('aria-selected'),'true');assert.equal(await page.locator('.sample-row:visible').count(),4);
 await page.locator('#sample-toys').press('Home');assert.equal(await page.locator('.sample-row:visible').count(),6);results.push({test:'Filtros da amostra e navegação por teclado',ok:true});
 const first=page.locator('.gallery [data-lightbox]').first();await first.click();assert.ok(await page.locator('#image-dialog').evaluate(d=>d.open));
 await page.locator('#lightbox-image').evaluate(img=>img.decode());const galleryCount=await page.locator('.gallery [data-lightbox]').count();assert.equal(await page.locator('[data-lightbox-count]').innerText(),'7 de '+galleryCount);
 await page.keyboard.press('ArrowRight');assert.equal(await page.locator('[data-lightbox-count]').innerText(),'8 de '+galleryCount);await page.keyboard.press('Escape');assert.equal(await page.locator('#image-dialog').evaluate(d=>d.open),false);assert.ok(await first.evaluate(el=>document.activeElement===el));results.push({test:'Galeria, teclas de navegação, Escape e retorno de foco',ok:true});
 const faqs=page.locator('.faq-list details');for(let i=0;i<await faqs.count();i++){await faqs.nth(i).locator('summary').click();assert.ok(await faqs.nth(i).evaluate(d=>d.open));await faqs.nth(i).locator('summary').click();}results.push({test:'Todas as perguntas do FAQ',ok:true,count:await faqs.count()});
 await page.setViewportSize({width:390,height:844});await page.locator('.mobile-menu summary').click();assert.ok(await page.locator('.mobile-menu').evaluate(d=>d.open));await page.locator('.mobile-menu a[href="#acesso"]').click();assert.equal(await page.locator('.mobile-menu').evaluate(d=>d.open),false);results.push({test:'Menu mobile e fechamento após navegação',ok:true});
 // Player real: API oficial, estado de reprodução, mute, play/pause e seek.
 await page.setViewportSize({width:1366,height:768});await open();await page.locator('#matheus-stage').scrollIntoViewIfNeeded();
 await page.waitForFunction(()=>window.YT?.get('matheus-embed')?.getPlayerState?.()===1,{},{timeout:25000});
 const provider=await page.evaluate(()=>{const p=YT.get('matheus-embed');return{muted:p.isMuted(),time:p.getCurrentTime(),duration:p.getDuration()};});assert.equal(provider.muted,true);assert.ok(provider.duration>0);
 await page.locator('[data-player-play]').click();await page.waitForFunction(()=>YT.get('matheus-embed').getPlayerState()===2);
 await page.locator('[data-player-mute]').click();await page.waitForFunction(()=>YT.get('matheus-embed').isMuted()===false);
 await page.locator('[data-player-mute]').click();await page.locator('[data-player-seek]').fill('20');await page.waitForTimeout(800);assert.ok(await page.evaluate(()=>YT.get('matheus-embed').getCurrentTime()>=19));
 await page.locator('[data-player-play]').click();await page.waitForFunction(()=>YT.get('matheus-embed').getPlayerState()===1);
 await page.locator('[data-player-full]').click();assert.ok(await page.evaluate(()=>!!document.fullscreenElement));await page.evaluate(()=>document.exitFullscreen());
 await page.screenshot({path:path.join(OUT,'player-vtd.png')});await page.locator('[data-player-play]').click();results.push({test:'YouTube real: autoplay sem som, pausar/reproduzir, som, busca e tela cheia',ok:true,...provider});
 await page.locator('#vtd-demo').scrollIntoViewIfNeeded();
 const localVideo=await page.locator('#vtd-demo').evaluate(async video=>{video.load();await new Promise((resolve,reject)=>{video.addEventListener('loadedmetadata',resolve,{once:true});video.addEventListener('error',()=>reject(new Error('Vídeo indisponível')),{once:true});});await video.play();await new Promise(r=>setTimeout(r,600));video.pause();await new Promise(resolve=>{video.addEventListener('seeked',resolve,{once:true});video.currentTime=40;});await new Promise(r=>setTimeout(r,100));return{duration:video.duration,width:video.videoWidth,height:video.videoHeight,currentTime:video.currentTime,error:video.error};});assert.ok(localVideo.duration>=45&&localVideo.duration<=60);assert.equal(localVideo.error,null);assert.equal(localVideo.width,1920);assert.ok(localVideo.currentTime>=39.5,'Avanço real do MP4 para 40 segundos');results.push({test:'MP4 local reproduz e permite avançar',ok:true,...localVideo});
 await page.emulateMedia({reducedMotion:'reduce'});assert.equal(await page.evaluate(()=>getComputedStyle(document.documentElement).scrollBehavior),'auto');results.push({test:'Movimento reduzido',ok:true});
 // Integridade de referências locais, inclusive poster, fontes, OG e faixas.
 const refs=await page.evaluate(()=>[...new Set([...document.querySelectorAll('[src],[poster],link[href]')].flatMap(el=>[el.getAttribute('src'),el.getAttribute('poster'),el.getAttribute('href')]).filter(Boolean))]);
 let checked=0;for(const ref of refs){const url=new URL(ref,BASE);if(url.origin!==new URL(BASE).origin||url.pathname===new URL(BASE).pathname)continue;const r=await context.request.get(url.href);assert.ok(r.ok(),`Asset ${url}: ${r.status()}`);checked++;}results.push({test:'Referências locais HTTP 200',ok:true,count:checked});
 const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});const fallback=await nojs.newPage();await fallback.goto(BASE,{waitUntil:'domcontentloaded'});assert.equal(await fallback.locator('.hero .buy').getAttribute('href'),'#pagamento');assert.equal(await fallback.locator('#pagamento [data-checkout]').getAttribute('href'),CHECKOUT);assert.equal(await fallback.locator('.gallery [data-lightbox]').count(),10);await fallback.locator('.faq-list summary').first().click();assert.ok(await fallback.locator('.faq-list details').first().evaluate(d=>d.open));await nojs.close();results.push({test:'Página pública sem JavaScript: oferta, links e FAQ disponíveis',ok:true});
 assert.equal(errors.length,0,JSON.stringify(errors));assert.equal(badResponses.length,0,JSON.stringify(badResponses));
 const checkout=await context.newPage();const checkoutResponse=await checkout.goto(CHECKOUT,{waitUntil:'domcontentloaded',timeout:45000});await checkout.waitForTimeout(4000);const checkoutText=await checkout.locator('body').innerText();const prices=checkoutText.match(/R\$\s*[\d.,]+/g)||[];results.push({test:'Checkout externo acessível (sem preencher ou enviar formulário)',ok:checkoutResponse.ok(),status:checkoutResponse.status(),prices:[...new Set(prices)].slice(0,6),title:await checkout.title()});await checkout.close();
 const report={timestamp:new Date().toISOString(),base:BASE,errors,badResponses,results};await fs.writeFile(path.join(OUT,'report.json'),JSON.stringify(report,null,2));console.log(JSON.stringify(report,null,2));await browser.close();
})().catch(async error=>{console.error(error);await activeBrowser?.close();process.exit(1)});
