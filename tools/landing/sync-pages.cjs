// Mantém a versão de /lp/ sincronizada, reutilizando os assets da raiz.
const fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../..');const source=fs.readFileSync(path.join(root,'vendas.html'),'utf8');
const output=source.replace(/\b(src|href|poster|srcset)="([^"]*)"/g,(_,attribute,value)=>attribute+'="'+value.replace(/(^|,\s*)(assets\/|design-system\/)/g,'$1../$2')+'"');
const target=path.join(root,'lp/index.html');
if(process.argv.includes('--check')){if(!fs.existsSync(target)||fs.readFileSync(target,'utf8')!==output){console.error('Execute node tools/landing/sync-pages.cjs antes de publicar.');process.exit(1);}console.log('Landing /lp/ sincronizada.');}
else{fs.mkdirSync(path.dirname(target),{recursive:true});fs.writeFileSync(target,output);console.log('Landing /lp/ gerada com assets compartilhados.');}
