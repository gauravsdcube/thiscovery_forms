/**
 * Evaluates the shared formula trees. Expected results are in the JSON file.
 */
const fs = require('fs');
const path = require('path');
const formula = require('../../../resources/js/thiscoveryForms.formula.rules.js');
const cases = JSON.parse(fs.readFileSync(path.join(__dirname, 'expressions.json'), 'utf8'));

function show(value) {
    if (!value || value.t === 'empty') {
        return 'empty';
    }
    if (value.t === 'bool') {
        return value.v ? 'bool:true' : 'bool:false';
    }
    return value.t + ':' + value.v;
}

let failed = 0;
cases.forEach(function (row, index) {
    const got = show(formula.evaluateTree(row.tree, row.values));
    if (got !== row.expect) {
        if (failed < 12) {
            console.error(index, got, row.expect);
        }
        failed++;
    }
});
if (failed) {
    console.error('FAIL ' + failed);
    process.exit(1);
}
console.log('PASS ' + cases.length);
