const fs = require('fs');
const path = require('path');
const decimal = require('../../../resources/js/thiscoveryForms.formula.js');
const cases = JSON.parse(fs.readFileSync(path.join(__dirname, 'decimal.json'), 'utf8'));
let failed = 0;
for (const entry of cases) {
    const args = entry.args;
    let got;
    if (entry.op === 'round') {
        got = decimal.round(String(args[0]), args[1]);
    } else if (entry.op === 'cmp') {
        got = decimal.cmp(String(args[0]), String(args[1]));
    } else if (entry.op === 'canonical') {
        got = decimal.canonical(String(args[0]));
    } else {
        got = decimal[entry.op](String(args[0]), String(args[1]));
    }
    if (got !== entry.expect) {
        console.error(entry.op, JSON.stringify(args), 'got', JSON.stringify(got), 'expect', JSON.stringify(entry.expect));
        failed++;
    }
}
if (failed) {
    console.error('FAIL ' + failed);
    process.exit(1);
}
console.log('PASS');
