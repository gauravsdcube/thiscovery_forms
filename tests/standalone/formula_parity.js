/**
 * Evaluates the cases written by FormulaParityTest.php with the browser evaluator and
 * reports every case whose value or type differs from PHP.
 * Usage: node formula_parity.js cases.json
 */
const fs = require('fs');
const path = require('path');
global.window = global;
require(path.join(__dirname, '../../resources/js/thiscoveryForms.formula.js'));
require(path.join(__dirname, '../../resources/js/thiscoveryForms.formula.rules.js'));
const F = global.thiscoveryFormula;

function norm(value) {
    if (!value || value.t === 'empty') return { t: 'empty', v: null };
    if (value.t === 'list') return { t: 'list', v: value.v.map(norm) };
    return { t: value.t, v: value.v };
}

const data = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const fails = [];
data.cases.forEach(function (c, i) {
    const values = Object.assign({}, data.values, { __fields: data.fields, __today: data.today, __scores: data.scores, __named: data.named });
    const got = norm(F.evaluateTree(c.tree, values));
    if (JSON.stringify(got) !== JSON.stringify(c.want)) {
        fails.push('#' + i + ' ' + c.label + '\n   php ' + JSON.stringify(c.want) + '\n   js  ' + JSON.stringify(got));
    }
});
if (fails.length) {
    console.error(fails.slice(0, 40).join('\n'));
    console.error(fails.length + ' of ' + data.cases.length + ' cases differ');
    process.exit(1);
}
console.log('PASS ' + data.cases.length);
