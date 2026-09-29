/**
 * The same four loop aggregates the server accepts.
 * any and all compare each instance. count and sum use the numeric operator.
 */
'use strict';

function compare(raw, op, expected) {
  var value = String(raw == null ? '' : raw);
  var exp = String(expected);
  if (op === 'equals') return value === exp;
  if (op === 'gt') return Number(value) > Number(exp);
  return false;
}

function aggregate(name, cells, op, expected) {
  if (name === 'any') {
    return cells.some(function (cell) { return compare(cell, op, expected); });
  }
  if (name === 'all') {
    return cells.length > 0 && cells.every(function (cell) { return compare(cell, op, expected); });
  }
  if (name === 'sum') {
    var sum = 0;
    cells.forEach(function (cell) {
      var n = parseFloat(cell);
      if (!isNaN(n)) sum += n;
    });
    return compare(String(sum), op, expected);
  }
  if (name === 'count') {
    var answered = cells.filter(function (cell) {
      return cell !== null && cell !== undefined && String(cell) !== '';
    }).length;
    return compare(String(answered), op, expected);
  }
  return false;
}

var cells = ['wheeze', 'thirst'];
var failures = [];
function check(ok, message) {
  if (!ok) failures.push(message);
}

check(aggregate('any', cells, 'equals', 'wheeze') === true, 'any');
check(aggregate('all', cells, 'equals', 'wheeze') === false, 'all');
check(aggregate('count', cells, 'gt', '1') === true, 'count');
check(aggregate('sum', ['2', '3'], 'equals', '5') === true, 'sum');
check(aggregate('mean', cells, 'equals', 'wheeze') === false, 'unknown');

if (failures.length) {
  console.error(failures.join('\n'));
  process.exit(1);
}
console.log('PASS');
