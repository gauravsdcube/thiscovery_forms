/**
 * Browser evaluator for formula trees (ADR-010). A line-by-line port of
 * services/formula/Evaluator.php and Context.php: every operator, function and
 * type rule must give the same value and type as PHP (checked by
 * tests/standalone/FormulaParityTest.php). The server result is the one stored.
 */
(function (root, factory) {
    var api = factory(root.thiscoveryFormula || (typeof require === 'function' ? require('./thiscoveryForms.formula.js') : {}));
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }
    root.thiscoveryFormula = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function (decimal) {
    var STEPS = 10000;
    var RESULT_TEXT = 2000;
    var CHOICE_TYPES = ['checkbox', 'radio', 'dropdown', 'ranking'];

    // ----- values -------------------------------------------------------------
    function empty() { return { t: 'empty' }; }
    function num(v) { return { t: 'number', v: v }; }
    function text(v) { return { t: 'text', v: String(v) }; }
    function bool(v) { return { t: 'bool', v: !!v }; }
    function date(v) { return { t: 'date', v: v }; }
    function list(items) { return { t: 'list', v: items }; }
    function isEmpty(value) { return !value || value.t === 'empty'; }
    function truth(value) {
        if (isEmpty(value)) return false;
        if (value.t === 'bool') return value.v === true;
        if (value.t === 'number') return decimal.cmp(value.v, '0') !== 0;
        if (value.t === 'text') return value.v !== '';
        if (value.t === 'list') return value.v.length > 0;
        return true;
    }

    // ----- context (mirror of Context.php) ------------------------------------
    function trim(s) { return String(s).replace(/^\s+|\s+$/g, ''); }
    function leafOf(textValue, type) {
        if (textValue === '') return empty();
        if (type === 'checkbox' && textValue === '0') return empty();
        if (CHOICE_TYPES.indexOf(type) !== -1) return text(textValue);
        if (type === 'number' || type === 'calculated' || type === '') {
            var number = decimal.canonical(textValue);
            if (number !== null && (type !== '' || !/[^\d.\-]/.test(textValue))) return num(number);
            if (type === 'number' || type === 'calculated') return empty();
        }
        if ((type === 'date' || type === '') && /^\d{4}-\d{2}-\d{2}$/.test(textValue)) return date(textValue);
        return text(textValue);
    }
    function scalar(raw, type) {
        type = type || '';
        if (raw === undefined || raw === null || raw === '') return empty();
        if (typeof raw === 'boolean') return bool(raw);
        if (Array.isArray(raw) || typeof raw === 'object') {
            // Mirrors Context::scalar: in a list, "0" is the option coded 0 (LOG-10).
            var itemType = type === 'checkbox' ? 'radio' : type;
            var items = [];
            Object.keys(raw).forEach(function (key) {
                var item = raw[key];
                if (item !== null && typeof item === 'object') return;
                var t = trim(item === undefined ? '' : item);
                if (t !== '') items.push(leafOf(t, itemType));
            });
            return list(items);
        }
        return leafOf(trim(raw), type);
    }
    function isInstanceMap(raw) {
        return raw !== null && typeof raw === 'object' && !Array.isArray(raw) && Object.keys(raw).length > 0;
    }

    var domFields = null;
    function fieldsFromDom() {
        if (domFields) return domFields;
        domFields = [];
        if (typeof document === 'undefined') return domFields;
        var seen = {};
        var nodes = document.querySelectorAll('[data-cf-field-id][data-cf-field-type]');
        for (var i = 0; i < nodes.length; i++) {
            var id = String(nodes[i].getAttribute('data-cf-field-id') || '');
            if (!id || seen[id]) continue;
            seen[id] = true;
            domFields.push({
                id: id,
                name: String(nodes[i].getAttribute('data-cf-variable') || ''),
                type: String(nodes[i].getAttribute('data-cf-field-type') || '')
            });
        }
        return domFields;
    }
    function scoresFromDom() {
        if (typeof document === 'undefined') return {};
        var node = document.querySelector('[data-cf-choice-scores]');
        if (!node) return {};
        try {
            var parsed = JSON.parse(node.getAttribute('data-cf-choice-scores') || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }
    function configValue(key) {
        var cfg = typeof window !== 'undefined' && window.thiscoveryFormulaConfig ? window.thiscoveryFormulaConfig : {};
        return cfg[key];
    }

    function context(values) {
        values = values || {};
        var ctx = {
            fields: {}, instances: {}, rows: {}, vars: {}, panel: {}, meta: {}, url: {},
            arm: empty(),
            today: '',
            scores: values.__scores || scoresFromDom(),
            named: values.__named || configValue('named') || {},
            steps: 0
        };
        var today = values.__today || configValue('today') || '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(String(today))) ctx.today = String(today);
        var fields = values.__fields || fieldsFromDom();
        // Loop members' arrays are always instance maps, whatever the key shape (V3-15).
        var loops = {};
        (values.__loops || configValue('loops') || []).forEach(function (loopId) {
            loops[String(loopId)] = true;
        });
        fields.forEach(function (meta) {
            var id = String(meta.id);
            var name = trim(meta.name || '') || ('id' + id);
            var raw = values[name];
            if (raw === undefined) raw = values[id];
            if (raw === undefined) raw = values['id' + id];
            remember(ctx, name, id, String(meta.type || ''), raw === undefined ? null : raw, loops[id] === true);
        });
        Object.keys(values).forEach(function (key) {
            if (key.indexOf('__') === 0) return;
            var raw = values[key];
            if (key.indexOf('panel.') === 0) ctx.panel[key.slice(6)] = scalar(raw);
            else if (key === 'arm') ctx.arm = scalar(raw);
            else if (key.indexOf('var:') === 0) ctx.vars[key.slice(4)] = scalar(raw);
            else if (key.indexOf('meta:') === 0) ctx.meta[key.slice(5)] = scalar(raw);
            else if (key.indexOf('url:') === 0) ctx.url[key.slice(4)] = scalar(raw);
            else if (!Object.prototype.hasOwnProperty.call(ctx.fields, key)) ctx.fields[key] = scalar(raw);
        });
        return ctx;
    }
    function remember(ctx, name, id, type, raw, loop) {
        var value;
        var grid = type === 'grid_single' || type === 'grid_multi';
        if (Array.isArray(raw) && raw.length && (loop || grid)) {
            // A PHP list with codes 0..n arrives as an array: re-key it by position.
            var keyed = {};
            raw.forEach(function (cell, index) { keyed[String(index)] = cell; });
            raw = keyed;
        }
        if (isInstanceMap(raw)) {
            var items = [];
            Object.keys(raw).forEach(function (key) {
                var cell = scalar(raw[key], grid ? 'radio' : type);
                items.push({ key: String(key), value: cell });
                if (grid) {
                    ctx.rows[name] = ctx.rows[name] || {};
                    ctx.rows[name][String(key)] = cell;
                }
            });
            ctx.instances[name] = items;
            value = list(items.map(function (item) { return item.value; }));
        } else {
            value = scalar(raw, type);
        }
        [name, id, 'id' + id].forEach(function (key) {
            ctx.fields[key] = value;
            if (ctx.instances[name]) ctx.instances[key] = ctx.instances[name];
            if (ctx.rows[name]) ctx.rows[key] = ctx.rows[name];
        });
    }

    // ----- evaluator (mirror of Evaluator.php) ---------------------------------
    function evaluate(node, ctx, fnStack) {
        fnStack = fnStack || [];
        ctx.steps++;
        if (ctx.steps > STEPS) return empty();
        if (!node || typeof node !== 'object') return empty();
        var op = String(node.op || '');
        var rawArgs = Array.isArray(node.args) ? node.args : [];
        if (op === 'lit') return literal(node);
        if (op === 'fn') {
            var name = String(node.name || '');
            var body = ctx.named[name];
            if (!body || typeof body !== 'object' || fnStack.indexOf(name) !== -1) return empty();
            return evaluate(body, ctx, fnStack.concat([name]));
        }
        if (op === 'ref') return reference(node, ctx);
        var args = rawArgs.map(function (arg) { return evaluate(arg, ctx, fnStack); });
        if (op === 'list') return list(args);
        var a0 = args[0] || empty();
        var a1 = args[1] || empty();
        switch (op) {
            case 'add': case 'sub': case 'mul': case 'div': case 'mod': case 'pow': case 'neg':
                return arithmetic(op, args);
            case 'eq': case 'ne': case 'gt': case 'gte': case 'lt': case 'lte':
                return bool(compare(op, a0, a1));
            case 'and':
                return bool(args.length > 0 && args.every(truth));
            case 'or':
                return bool(args.some(truth));
            case 'not':
                return bool(!truth(a0));
            case 'in':
                return bool(inList(a0, a1, false));
            case 'not_in':
                return bool(inList(a0, a1, true));
            case 'sum': case 'mean': case 'min': case 'max': case 'count': case 'count_eq': case 'count_answered': case 'count_selected':
                return aggregate(op, args);
            case 'round': case 'floor': case 'ceil': case 'abs': case 'sqrt':
                return unaryNumber(op, args);
            case 'if':
                return truth(a0) ? a1 : (args[2] || empty());
            case 'coalesce':
                for (var c = 0; c < args.length; c++) {
                    if (!isEmpty(args[c])) return args[c];
                }
                return empty();
            case 'ifempty':
                return isEmpty(a0) ? a1 : a0;
            case 'min_valid':
                return minValid(args);
            case 'concat':
                return concat(args);
            case 'lower': case 'upper': case 'length':
                return textOp(op, a0);
            case 'contains_text':
                return bool(containsText(a0, a1));
            case 'today':
                return ctx.today ? date(ctx.today) : empty();
            case 'date_diff':
                return dateDiff(args);
            case 'add_days': case 'add_months':
                return shiftDate(op, args);
            case 'year': case 'month':
                return datePart(op, a0);
            case 'any_eq': case 'all_eq':
                return bool(quantify(op, args));
            case 'between':
                return bool(between(args));
            case 'is_empty':
                return bool(isEmpty(a0));
            case 'is_answered':
                return bool(answered(a0));
            case 'selected':
                return bool(!isEmpty(a1) && compare('eq', a0, a1));
            case 'selected_all': case 'selected_only':
                return bool(selectedSet(args, op === 'selected_only'));
            case 'code_of':
                return a0.t === 'text' || a0.t === 'number' ? a0 : empty();
            case 'score_of':
                return scoreOf(rawArgs, a0, ctx);
            default:
                return empty();
        }
    }

    function literal(node) {
        var kind = String(node.lit || '');
        if (kind === 'number') {
            var canonical = decimal.canonical(String(node.v === undefined ? '' : node.v));
            return canonical === null ? empty() : num(canonical);
        }
        if (kind === 'text') return text(node.v === undefined ? '' : node.v);
        if (kind === 'bool') return bool(!!node.v);
        if (kind === 'date') return date(String(node.v === undefined ? '' : node.v));
        if (kind === 'datetime') return text(node.v === undefined ? '' : node.v);
        return empty();
    }

    function reference(node, ctx) {
        var kind = String(node.ref || '');
        var name = String(node.name || '');
        if (kind === 'arm') return ctx.arm;
        if (kind === 'var') return ctx.vars[name] || empty();
        if (kind === 'panel') return ctx.panel[name] || empty();
        if (kind === 'meta') return ctx.meta[name] || empty();
        if (kind === 'url') return ctx.url[name] || empty();
        if (node.all) return list((ctx.instances[name] || []).map(function (item) { return item.value; }));
        if (node.instance !== undefined && node.instance !== null) {
            var items = ctx.instances[name] || [];
            for (var i = 0; i < items.length; i++) {
                if (items[i].key === String(node.instance)) return items[i].value;
            }
            return empty();
        }
        if (node.row !== undefined && node.row !== null) {
            return (ctx.rows[name] && ctx.rows[name][String(node.row)]) || empty();
        }
        return ctx.fields[name] || empty();
    }

    function arithmetic(op, args) {
        if (op === 'neg') {
            var value = args[0] || empty();
            if (value.t !== 'number') return empty();
            var neg = decimal.negate(value.v);
            return neg === null ? empty() : num(neg);
        }
        var left = args[0] || empty();
        var right = args[1] || empty();
        if (left.t !== 'number' || right.t !== 'number') return empty();
        var result = null;
        if (op === 'add' || op === 'sub' || op === 'mul' || op === 'div') result = decimal[op](left.v, right.v);
        else if (op === 'mod') result = mod(left.v, right.v);
        else if (op === 'pow') result = pow(left.v, right.v);
        return result === null ? empty() : num(result);
    }

    function mod(left, right) {
        if (decimal.cmp(right, '0') === 0) return null;
        var quotient = decimal.div(left, right);
        if (quotient === null) return null;
        var whole = decimal.round(quotient, 0);
        if (whole === null) return null;
        if (decimal.cmp(whole, quotient) > 0) whole = decimal.sub(whole, '1');
        var product = whole === null ? null : decimal.mul(whole, right);
        return product === null ? null : decimal.sub(left, product);
    }

    function pow(left, right) {
        if (decimal.canonical(right) === null || right.indexOf('.') !== -1) return null;
        var times = parseInt(right, 10);
        if (times < 0 || times > 20) return null;
        var result = '1';
        for (var i = 0; i < times; i++) {
            result = decimal.mul(result, left);
            if (result === null) return null;
        }
        return result;
    }

    function orderable(value) {
        if (value.t === 'number') return value.v;
        if (value.t === 'text') return decimal.canonical(value.v);
        return null;
    }

    function compare(op, left, right) {
        if (isEmpty(left) || isEmpty(right)) return op === 'ne';
        if (right.t === 'list' && left.t !== 'list') {
            var mirror = { eq: 'eq', ne: 'ne', gt: 'lt', gte: 'lte', lt: 'gt', lte: 'gte' };
            return compare(mirror[op] || op, right, left);
        }
        if (left.t === 'list') {
            var inner = op === 'ne' ? 'eq' : op;
            var any = left.v.some(function (item) { return compare(inner, item, right); });
            return op === 'ne' ? !any : any;
        }
        if (op === 'gt' || op === 'gte' || op === 'lt' || op === 'lte') {
            var l = orderable(left);
            var r = orderable(right);
            if (l !== null && r !== null) {
                left = num(l);
                right = num(r);
            }
        }
        var order = null;
        if (left.t === 'number' && right.t === 'number') order = decimal.cmp(left.v, right.v);
        else if (left.t === 'date' && right.t === 'date') order = left.v < right.v ? -1 : (left.v > right.v ? 1 : 0);
        if (order !== null) {
            if (op === 'eq') return order === 0;
            if (op === 'ne') return order !== 0;
            if (op === 'gt') return order > 0;
            if (op === 'gte') return order >= 0;
            if (op === 'lt') return order < 0;
            if (op === 'lte') return order <= 0;
            return false;
        }
        if (op === 'gt' || op === 'gte' || op === 'lt' || op === 'lte') return false;
        var same = sameText(left, right);
        return op === 'ne' ? !same : same;
    }

    function sameText(left, right) {
        var a = left.t === 'bool' ? (left.v ? 'true' : 'false') : String(left.v);
        var b = right.t === 'bool' ? (right.v ? 'true' : 'false') : String(right.v);
        return a === b;
    }

    function inList(left, right, negate) {
        if (isEmpty(left)) return negate;
        var items = right.t === 'list' ? right.v : [right];
        var found = items.some(function (item) { return compare('eq', left, item); });
        return negate ? !found : found;
    }

    function flatten(values) {
        var out = [];
        values.forEach(function (value) {
            if (value && value.t === 'list') value.v.forEach(function (item) { out.push(item); });
            else out.push(value || empty());
        });
        return out;
    }
    function allEmpty(values) {
        return values.every(isEmpty);
    }
    function answered(value) {
        if (isEmpty(value)) return false;
        if (value.t === 'list') return value.v.length > 0;
        return true;
    }

    function aggregate(op, args) {
        var flat = flatten(args);
        if (op === 'count_eq') {
            var needle = flat.pop();
            if (!needle || isEmpty(needle) || allEmpty(flat)) return empty();
            return num(String(flat.filter(function (item) { return !isEmpty(item) && compare('eq', item, needle); }).length));
        }
        if (op === 'count_answered' || op === 'count' || op === 'count_selected') {
            if (allEmpty(flat)) return empty();
            return num(String(flat.filter(answered).length));
        }
        var numbers = flat.filter(function (item) { return item.t === 'number'; }).map(function (item) { return item.v; });
        if (!numbers.length) return empty();
        if (op === 'min' || op === 'max') {
            var best = numbers[0];
            numbers.forEach(function (n) {
                var order = decimal.cmp(n, best);
                if ((op === 'min' && order < 0) || (op === 'max' && order > 0)) best = n;
            });
            return num(best);
        }
        var total = '0';
        numbers.forEach(function (n) {
            var next = decimal.add(total, n);
            if (next !== null) total = next;
        });
        if (op === 'mean') {
            var mean = decimal.div(total, String(numbers.length));
            return mean === null ? empty() : num(mean);
        }
        return num(total);
    }

    function unaryNumber(op, args) {
        var value = args[0] || empty();
        if (value.t !== 'number') return empty();
        var t = value.v;
        if (op === 'abs') {
            var abs = t.charAt(0) === '-' ? decimal.negate(t) : t;
            return abs === null ? empty() : num(abs);
        }
        if (op === 'round') {
            var places = args[1] && args[1].t === 'number' ? parseInt(args[1].v, 10) : 0;
            var rounded = decimal.round(t, places);
            return rounded === null ? empty() : num(rounded);
        }
        if (op === 'floor' || op === 'ceil') {
            var whole = decimal.round(t, 0);
            if (whole === null) return empty();
            var order = decimal.cmp(t, whole);
            if (op === 'floor' && order < 0) whole = decimal.sub(whole, '1');
            if (op === 'ceil' && order > 0) whole = decimal.add(whole, '1');
            return whole === null ? empty() : num(whole);
        }
        if (op === 'sqrt') {
            if (decimal.cmp(t, '0') < 0) return empty();
            if (decimal.cmp(t, '0') === 0) return num('0');
            var digits = t.replace(/^-/, '').split('.')[0];
            var guess = decimal.cmp(t, '1') < 0 ? '1' : '1' + new Array(Math.floor((digits.length + 1) / 2) + 1).join('0');
            for (var i = 0; i < 200; i++) {
                var div = decimal.div(t, guess);
                var sum = div === null ? null : decimal.add(guess, div);
                var next = sum === null ? null : decimal.div(sum, '2');
                if (next === null || decimal.cmp(next, guess) >= 0) break;
                guess = next;
            }
            return num(guess);
        }
        return empty();
    }

    function minValid(args) {
        var need = args[0] || empty();
        if (need.t !== 'number') return empty();
        var numbers = flatten(args.slice(1)).filter(function (item) { return item.t === 'number'; });
        if (numbers.length < parseInt(need.v, 10)) return empty();
        var total = '0';
        numbers.forEach(function (item) {
            var next = decimal.add(total, item.v);
            if (next !== null) total = next;
        });
        return num(total);
    }

    function concat(args) {
        var out = '';
        flatten(args).forEach(function (value) {
            if (!isEmpty(value)) out += textOf(value);
        });
        return text(out.slice(0, RESULT_TEXT));
    }

    // Text form of a value, as PHP's (string) cast gives it: true is "1", false is "".
    function textOf(value) {
        return value.t === 'bool' ? (value.v ? '1' : '') : String(value.v);
    }
    function textOp(op, value) {
        if (isEmpty(value) || value.t === 'list') return empty();
        var t = textOf(value);
        if (op === 'lower') return text(t.toLowerCase());
        if (op === 'upper') return text(t.toUpperCase());
        if (op === 'length') return num(String(Array.from(t).length));
        return empty();
    }

    function containsText(haystack, needle) {
        if (isEmpty(needle) || needle.t === 'list' || textOf(needle) === '' || isEmpty(haystack)) return false;
        var n = textOf(needle).toLowerCase();
        if (haystack.t === 'list') {
            // Mirror of Evaluator::containsText: on a list, one of the items is the text (V3-54).
            return haystack.v.some(function (item) { return !isEmpty(item) && item.t !== 'list' && textOf(item).toLowerCase() === n; });
        }
        return textOf(haystack).toLowerCase().indexOf(n) !== -1;
    }

    // ----- dates: strict ISO calendar dates, UTC -------------------------------
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function isoDate(value) {
        if (!value || value.t !== 'date') return null;
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value.v));
        if (!m) return null;
        var y = parseInt(m[1], 10);
        var mo = parseInt(m[2], 10);
        var d = parseInt(m[3], 10);
        if (mo < 1 || mo > 12 || d < 1) return null;
        var dim = new Date(Date.UTC(y, mo, 0)).getUTCDate();
        if (d > dim) return null;
        return { y: y, m: mo, d: d };
    }
    function format(y, m, d) {
        var year = String(y);
        while (year.length < 4) year = '0' + year;
        return year + '-' + pad(m) + '-' + pad(d);
    }
    function dateDiff(args) {
        var start = isoDate(args[0]);
        var end = isoDate(args[1]);
        var unit = String(args[2] && args[2].v !== undefined ? args[2].v : '').toLowerCase();
        if (!start || !end) return empty();
        if (unit === 'days') {
            var ms = Date.UTC(end.y, end.m - 1, end.d) - Date.UTC(start.y, start.m - 1, start.d);
            return num(String(Math.round(ms / 86400000)));
        }
        var total = (end.y - start.y) * 12 + (end.m - start.m);
        if (end.d < start.d) total--;
        if (unit === 'months') return num(String(total));
        if (unit === 'years') {
            var whole = total < 0 ? -Math.floor(-total / 12) : Math.floor(total / 12);
            if (total < 0 && total % 12 !== 0) whole--;
            return num(String(whole));
        }
        return empty();
    }
    function shiftDate(op, args) {
        var base = isoDate(args[0]);
        var amount = args[1];
        if (!base || !amount || amount.t !== 'number' || amount.v.indexOf('.') !== -1) return empty();
        var steps = parseInt(amount.v, 10);
        if ((op === 'add_days' && Math.abs(steps) > 36500) || (op === 'add_months' && Math.abs(steps) > 1200)) return empty();
        if (op === 'add_days') {
            var next = new Date(Date.UTC(base.y, base.m - 1, base.d + steps));
            return date(format(next.getUTCFullYear(), next.getUTCMonth() + 1, next.getUTCDate()));
        }
        var monthIndex = base.m - 1 + steps;
        var year = base.y + Math.floor(monthIndex / 12);
        var month = ((monthIndex % 12) + 12) % 12;
        var dim = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
        return date(format(year, month + 1, Math.min(base.d, dim)));
    }
    function datePart(op, value) {
        var d = isoDate(value);
        if (!d) return empty();
        return num(String(op === 'year' ? d.y : d.m));
    }

    function quantify(op, args) {
        var needle = args[1] || empty();
        var items = flatten([args[0] || empty()]);
        if (op === 'all_eq' && items.length === 0) return false;
        var seen = false;
        for (var i = 0; i < items.length; i++) {
            if (isEmpty(items[i])) continue;
            seen = true;
            var hit = compare('eq', items[i], needle);
            if (op === 'any_eq' && hit) return true;
            if (op === 'all_eq' && !hit) return false;
        }
        return op === 'all_eq' && seen;
    }

    // Mirrors Evaluator::selectedSet (LOG-10).
    function selectedSet(args, exact) {
        var filled = function (v) { return !isEmpty(v); };
        var chosen = flatten([args[0] || empty()]).filter(filled);
        var wanted = flatten(args.slice(1)).filter(filled);
        if (chosen.length === 0 || wanted.length === 0) return false;
        var within = function (item, pool) {
            return pool.some(function (other) { return compare('eq', item, other); });
        };
        for (var i = 0; i < wanted.length; i++) {
            if (!within(wanted[i], chosen)) return false;
        }
        if (exact) {
            for (var j = 0; j < chosen.length; j++) {
                if (!within(chosen[j], wanted)) return false;
            }
        }
        return true;
    }

    function between(args) {
        var value = args[0] || empty();
        var low = args[1] || empty();
        var high = args[2] || empty();
        if (isEmpty(value) || isEmpty(low) || isEmpty(high)) return false;
        if (compare('gt', low, high)) {
            var swap = low;
            low = high;
            high = swap;
        }
        return compare('gte', value, low) && compare('lte', value, high);
    }

    function scoreOf(rawArgs, value, ctx) {
        var refNode = rawArgs[0];
        var name = refNode && refNode.name ? String(refNode.name) : '';
        if (isEmpty(value) || name === '') return empty();
        var code = String(value.v);
        var table = ctx.scores && ctx.scores[name] ? ctx.scores[name] : null;
        if (table && table[code] !== undefined) return num(String(table[code]));
        // Mirror of Evaluator::scoreOf: an unscored option of a scored question scores nothing (V3-42).
        if (table && Object.keys(table).length) return empty();
        var number = decimal.canonical(code);
        return number === null ? empty() : num(number);
    }

    // ----- public API -----------------------------------------------------------
    function compile(rule) {
        if (!rule) return null;
        if (rule.op) return rule;
        if (rule.when && typeof rule.when === 'object') return rule.when;
        return null;
    }
    function safeEvaluate(tree, values) {
        try {
            return evaluate(tree, context(values));
        } catch (e) {
            // One bad formula must never stop the rest of the page updating.
            if (typeof console !== 'undefined' && console.error) console.error('Thiscovery Forms formula error', e);
            return empty();
        }
    }
    decimal.ruleTruth = function (rule, values) {
        var tree = compile(rule);
        return tree ? truth(safeEvaluate(tree, values)) : false;
    };
    decimal.evaluateTree = function (tree, values) {
        return safeEvaluate(tree, values);
    };
    decimal.treeTruth = function (tree, values) {
        return truth(safeEvaluate(tree, values));
    };
    decimal.resetFieldCache = function () {
        domFields = null;
    };
    return decimal;
}));
