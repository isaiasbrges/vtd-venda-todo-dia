const fs = require('fs');
const path = require('path');

const dataJsPath = path.resolve(__dirname, '../../data.js');
let code = fs.readFileSync(dataJsPath, 'utf8');

// Adiciona exportação
code += '\nmodule.exports = { ORIGINAL_SUPPLIERS, CHAPTERS, MONTHS };\n';

const m = { exports: {} };
const fn = new Function('module', 'exports', code);
fn(m, m.exports);

const data = {
    suppliers: m.exports.ORIGINAL_SUPPLIERS || [],
    chapters: m.exports.CHAPTERS || [],
    months: m.exports.MONTHS || []
};

const outputPath = path.resolve(__dirname, 'initial_data.json');
fs.writeFileSync(outputPath, JSON.stringify(data, null, 2), 'utf8');

console.log(`Exported successfully: ${data.suppliers.length} suppliers, ${data.chapters.length} chapters, ${data.months.length} months.`);
