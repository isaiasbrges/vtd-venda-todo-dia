const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
function ui() { const ctx=vm.createContext({console}); vm.runInContext(fs.readFileSync(require('node:path').join(__dirname,'../finance-ui.js'),'utf8'),ctx); return ctx.FinanceUI; }
test('moeda brasileira e centavos exatos, sem arredondar entrada inválida',()=>{const f=ui(); for(const [value,cents] of [['1.234,56',123456],['100,50',10050],['0,01',1],['100',10000],['100.50',10050]]) assert.equal(f.moneyCents(value),cents); for(const value of ['','1,234','-1','NaN','1e3','1.2.3']) assert.equal(f.moneyCents(value),null);});
test('etapas não avançam com valor ou descrição inválidos',()=>{const f=ui(); assert.ok(f.validateStep({description:'',value:'100'},0)); assert.ok(f.validateStep({description:'Venda',value:'0'},0)); assert.equal(f.validateStep({description:'Venda',value:'10,50'},0),''); assert.ok(f.validateStep({category_id:'',method:'cash'},1));});
