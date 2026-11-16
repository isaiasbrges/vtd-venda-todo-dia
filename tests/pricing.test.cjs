const test = require('node:test');
const assert = require('node:assert/strict');
const {calculate,parse} = require('../pricing.js');
const base = {cost:30,quantity:10,freight:50,packaging:2,extra:0,fee:5,tax:4,margin:30,discount:10};
test('rateia frete e calcula margem sobre venda, não markup',()=>{
 const r=calculate(base);assert.deepEqual(r.errors,{});assert.equal(r.cost,37);assert.equal(r.price,60.66);
 assert.ok(r.margin>=30);assert.ok(r.margin<30.02);assert.equal(r.noFeePrice,56.07);
 assert.ok(Math.abs(r.price-r.cost-r.fees-r.taxes-r.contribution)<1e-9);
});
test('custo 100 e margem 50% exigem preço 200',()=>{assert.equal(calculate({...base,cost:100,freight:0,packaging:0,fee:0,tax:0,margin:50}).price,200)});
test('aceita decimais brasileiros e rejeita entradas ambíguas/inválidas',()=>{
 assert.equal(parse('1.234,56'),1234.56);assert.equal(parse('12,50'),12.5);assert.equal(parse('12.50'),12.5);
 for(const x of ['', '1.234', '1e2', 'NaN','Infinity','-3','2abc'])assert.ok(Number.isNaN(parse(x)));
});
test('não substitui custo ou margem ausente por zero',()=>{
 assert.ok(calculate({...base,cost:''}).errors.cost);assert.ok(calculate({...base,margin:''}).errors.margin);
 assert.equal(calculate({...base,margin:0}).errors.margin,undefined);
});
test('bloqueia quantidade zero/fracionada e taxas+margem inviáveis',()=>{
 for(const quantity of [0,-1,1.5])assert.ok(calculate({...base,quantity}).errors.quantity);
 for(const margin of [91,99,100])assert.ok(calculate({...base,margin}).errors.margin);
 assert.ok(calculate({...base,fee:-1}).errors.fee);
});
test('desconto recalcula taxas e alerta contribuição negativa',()=>{
 const r=calculate({...base,discount:50});assert.equal(r.discounted,30.33);assert.ok(r.discountContribution<0);
 assert.ok(Math.abs(r.discountContribution-(30.33*.91-37))<1e-9);
 assert.ok(calculate({...base,discount:100}).errors.discount);
});
test('campos opcionais vazios equivalem a zero',()=>{
 const r=calculate({cost:'10',quantity:'1',margin:'20'});assert.equal(r.price,12.5);assert.equal(r.contribution,2.5);
});
test('arredondamento não reduz margem e preço mínimo cobre custos variáveis',()=>{
 for(const cost of [0.01,0.1,1,39.99,1000])for(const margin of [0,15,30,70]){
 const r=calculate({...base,cost,margin});assert.ok(r.margin+1e-8>=margin);
 assert.ok(r.floor*.91+1e-8>=r.cost);assert.ok((r.floor-.01)*.91<r.cost+1e-8);
 }
});
