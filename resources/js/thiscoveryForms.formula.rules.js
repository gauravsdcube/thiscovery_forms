/**
 * Tree evaluator for preview. The server result is the one that is stored.
 */
(function (root, factory) {
    var api = factory(root.thiscoveryFormula || (typeof require === 'function' ? require('./thiscoveryForms.formula.js') : {}));
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }
    root.thiscoveryFormula = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function (decimal) {
    function empty() { return { t: 'empty' }; }
    function num(v) { return { t: 'number', v: v }; }
    function text(v) { return { t: 'text', v: String(v) }; }
    function truth(value) {
        if (!value || value.t === 'empty') return false;
        if (value.t === 'bool') return value.v === true;
        if (value.t === 'number') return decimal.cmp(value.v, '0') !== 0;
        if (value.t === 'text') return value.v !== '' && value.v !== '0';
        if (value.t === 'list') return value.v.length > 0;
        return true;
    }
    function leaf(raw, type) {
        if (raw === undefined || raw === null || raw === '') return empty();
        if (Array.isArray(raw)) {
            var items = [];
            raw.forEach(function (item) {
                var one = leaf(item, type);
                if (one.t !== 'empty') items.push(one);
            });
            return { t: 'list', v: items };
        }
        if (raw && typeof raw === 'object') {
            var cells = [];
            Object.keys(raw).forEach(function (key) {
                cells.push(leaf(raw[key], type));
            });
            return { t: 'list', v: cells };
        }
        var value = String(raw).replace(/^\s+|\s+$/g, '');
        if (value === '') return empty();
        if (type === 'checkbox' && value === '0') return empty();
        if (type === 'checkbox' || type === 'radio' || type === 'dropdown' || type === 'ranking') {
            return text(value);
        }
        var canonical = decimal.canonical(value);
        if (canonical !== null && type !== 'date' && type !== 'text') {
            return num(canonical);
        }
        if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return { t: 'date', v: value };
        return text(value);
    }
    function fieldType(key) {
        if (typeof document === 'undefined') return '';
        var node = document.querySelector('[data-cf-field-id="' + String(key).replace(/"/g, '') + '"]');
        return node ? String(node.getAttribute('data-cf-field-type') || '') : '';
    }
    function context(values) {
        var fields = {};
        Object.keys(values || {}).forEach(function (key) {
            if (key.indexOf('panel.') === 0 || key === 'arm' || key.indexOf('var:') === 0) return;
            fields[key] = leaf(values[key], fieldType(key));
        });
        return { fields: fields, values: values || {} };
    }
    function lit(token) {
        if (token.lit === 'number') return num(token.v);
        if (token.lit === 'text') return text(token.v);
        if (token.lit === 'bool') return { t: 'bool', v: !!token.v };
        if (token.lit === 'date') return { t: 'date', v: token.v };
        return empty();
    }
    function ref(node, ctx) {
        if (node.ref === 'arm') return leaf(ctx.values.arm, '');
        if (node.ref === 'panel') return leaf(ctx.values['panel.' + node.name], '');
        if (node.ref === 'var') return leaf(ctx.values['var:' + node.name], '');
        return ctx.fields[node.name] || empty();
    }
    function same(left, right) {
        var a = left.t === 'bool' ? (left.v ? 'true' : 'false') : String(left.v);
        var b = right.t === 'bool' ? (right.v ? 'true' : 'false') : String(right.v);
        return a === b;
    }
    function compare(op, left, right) {
        if (!left || left.t === 'empty' || !right || right.t === 'empty') return op === 'ne';
        if (left.t === 'list') {
            var any = left.v.some(function (item) { return compare(op === 'ne' ? 'eq' : op, item, right); });
            return op === 'ne' ? !any : any;
        }
        if (left.t === 'number' && right.t === 'number') {
            var order = decimal.cmp(left.v, right.v);
            if (op === 'eq') return order === 0;
            if (op === 'ne') return order !== 0;
            if (op === 'gt') return order > 0;
            if (op === 'gte') return order >= 0;
            if (op === 'lt') return order < 0;
            return order <= 0;
        }
        if (op === 'gt' || op === 'gte' || op === 'lt' || op === 'lte') return false;
        var equal = same(left, right);
        return op === 'ne' ? !equal : equal;
    }
    function flatten(values) {
        var out = [];
        values.forEach(function (value) {
            if (value.t === 'list') value.v.forEach(function (item) { out.push(item); });
            else out.push(value);
        });
        return out;
    }
    function evaluate(node, ctx) {
        if (!node) return empty();
        if (node.op === 'lit') return lit(node);
        if (node.op === 'ref') return ref(node, ctx);
        var args = (node.args || []).map(function (arg) { return evaluate(arg, ctx); });
        if (node.op === 'add' || node.op === 'sub' || node.op === 'mul' || node.op === 'div') {
            if (args[0].t !== 'number' || args[1].t !== 'number') return empty();
            var result = decimal[node.op](args[0].v, args[1].v);
            return result === null ? empty() : num(result);
        }
        if (node.op === 'eq' || node.op === 'ne' || node.op === 'gt' || node.op === 'gte' || node.op === 'lt' || node.op === 'lte') {
            return { t: 'bool', v: compare(node.op, args[0], args[1]) };
        }
        if (node.op === 'and') return { t: 'bool', v: truth(args[0]) && truth(args[1]) };
        if (node.op === 'or') return { t: 'bool', v: truth(args[0]) || truth(args[1]) };
        if (node.op === 'not') return { t: 'bool', v: !truth(args[0]) };
        if (node.op === 'contains_text') {
            if (!args[1] || args[1].t === 'empty' || args[1].v === '' || args[0].t === 'empty') return { t: 'bool', v: false };
            return { t: 'bool', v: String(args[0].v).toLowerCase().indexOf(String(args[1].v).toLowerCase()) !== -1 };
        }
        if (node.op === 'is_answered') {
            var value = args[0];
            if (!value || value.t === 'empty') return { t: 'bool', v: false };
            if (value.t === 'text' && value.v === '0') return { t: 'bool', v: false };
            return { t: 'bool', v: true };
        }
        if (node.op === 'between') {
            return { t: 'bool', v: compare('gte', args[0], args[1]) && compare('lte', args[0], args[2]) };
        }
        if (node.op === 'sum' || node.op === 'count_answered') {
            var flat = flatten(args);
            var seen = flat.some(function (item) { return item.t !== 'empty'; });
            if (!seen) return empty();
            if (node.op === 'count_answered') {
                var count = flat.filter(function (item) { return item.t !== 'empty' && !(item.t === 'text' && item.v === '0'); }).length;
                return num(String(count));
            }
            var total = '0';
            var anyNumber = false;
            flat.forEach(function (item) {
                if (item.t === 'number') {
                    anyNumber = true;
                    total = decimal.add(total, item.v);
                }
            });
            return anyNumber && total !== null ? num(total) : empty();
        }
        if (node.op === 'round' && args[0].t === 'number') {
            var places = args[1] && args[1].t === 'number' ? parseInt(args[1].v, 10) : 0;
            var rounded = decimal.round(args[0].v, places);
            return rounded === null ? empty() : num(rounded);
        }
        if (node.op === 'if') return truth(args[0]) ? args[1] : args[2];
        return empty();
    }
    function literal(value) {
        var canonical = decimal.canonical(value);
        if (canonical !== null) return { op: 'lit', lit: 'number', v: canonical };
        return { op: 'lit', lit: 'text', v: value };
    }
    function compile(rule) {
        if (!rule) return null;
        if (rule.op) return rule;
        if (rule.when) return rule.when;
        if (Array.isArray(rule.all)) {
            var ands = rule.all.map(compile).filter(Boolean);
            return ands.length ? { op: 'and', args: ands } : null;
        }
        if (Array.isArray(rule.any)) {
            var ors = rule.any.map(compile).filter(Boolean);
            return ors.length ? { op: 'or', args: ors } : null;
        }
        var key = String(rule.fieldKey || '');
        if (!key && rule.source !== 'arm' && rule.source !== 'panel') return null;
        var node = rule.source === 'panel' || key.indexOf('panel.') === 0
            ? { op: 'ref', ref: 'panel', name: key.indexOf('panel.') === 0 ? key.slice(6) : key }
            : (rule.source === 'arm' || key === 'arm' ? { op: 'ref', ref: 'arm' } : { op: 'ref', ref: 'field', name: key });
        var expected = literal(String(rule.value == null ? '' : rule.value).replace(/^["']|["']$/g, ''));
        var op = String(rule.operator || 'equals');
        var map = { equals: 'eq', not_equals: 'ne', gt: 'gt', gte: 'gte', lt: 'lt', lte: 'lte' };
        var cmp = map[op] ? { op: map[op], args: [node, expected] } : null;
        if (op === 'contains') cmp = { op: 'contains_text', args: [node, expected] };
        if (op === 'checked') cmp = expected.v === '' ? { op: 'is_answered', args: [node] } : { op: 'eq', args: [node, expected] };
        if (op === 'between') {
            var parts = String(expected.v).split(',');
            cmp = { op: 'between', args: [node, literal(parts[0] || ''), literal(parts[1] || '')] };
        }
        if (rule.aggregate === 'any') return { op: 'any_eq', args: [node, expected] };
        return cmp;
    }
    function ruleTruth(rule, values) {
        var tree = compile(rule);
        if (!tree) return false;
        if (tree.op === 'any_eq') {
            var raw = values[tree.args[0].name];
            var cells = raw && typeof raw === 'object' && !Array.isArray(raw) ? Object.keys(raw).map(function (key) { return raw[key]; }) : [];
            return cells.some(function (cell) {
                var probe = {};
                probe[tree.args[0].name] = cell;
                return truth(evaluate({ op: 'eq', args: [tree.args[0], tree.args[1]] }, context(probe)));
            });
        }
        return truth(evaluate(tree, context(values)));
    }
    decimal.ruleTruth = ruleTruth;
    decimal.evaluateTree = evaluate;
    return decimal;
}));
