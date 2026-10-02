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
    function choiceScores(values) {
        if (values && values.__scores && typeof values.__scores === 'object') return values.__scores;
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
    function fieldType(key) {
        if (typeof document === 'undefined') return '';
        var node = document.querySelector('[data-cf-field-id="' + String(key).replace(/"/g, '') + '"]');
        return node ? String(node.getAttribute('data-cf-field-type') || '') : '';
    }
    function context(values) {
        var fields = {};
        Object.keys(values || {}).forEach(function (key) {
            if (key.charAt(0) === '_' || key.indexOf('panel.') === 0 || key === 'arm' || key.indexOf('var:') === 0 || key.indexOf('url:') === 0 || key.indexOf('meta:') === 0) return;
            fields[key] = leaf(values[key], fieldType(key));
        });
        return { fields: fields, values: values || {}, today: (values && values.__today) || '2026-09-30', scores: choiceScores(values) };
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
        if (node.ref === 'url') return leaf(ctx.values['url:' + node.name], '');
        if (node.ref === 'meta') return leaf(ctx.values['meta:' + node.name], '');
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
        if (node.op === 'round' || node.op === 'floor' || node.op === 'ceil' || node.op === 'abs') {
            if (!args[0] || args[0].t !== 'number') return empty();
            if (node.op === 'abs') {
                var abs = args[0].v.charAt(0) === '-' ? decimal.negate(args[0].v) : args[0].v;
                return abs === null ? empty() : num(abs);
            }
            if (node.op === 'round') {
                var places = args[1] && args[1].t === 'number' ? parseInt(args[1].v, 10) : 0;
                var rounded = decimal.round(args[0].v, places);
                return rounded === null ? empty() : num(rounded);
            }
            var whole = decimal.round(args[0].v, 0);
            if (whole === null) return empty();
            var order = decimal.cmp(args[0].v, whole);
            if (node.op === 'floor' && order < 0) whole = decimal.sub(whole, '1');
            if (node.op === 'ceil' && order > 0) whole = decimal.add(whole, '1');
            return whole === null ? empty() : num(whole);
        }
        if (node.op === 'neg') {
            if (!args[0] || args[0].t !== 'number') return empty();
            var negated = decimal.negate(args[0].v);
            return negated === null ? empty() : num(negated);
        }
        if (node.op === 'pow') {
            if (!args[0] || args[0].t !== 'number' || !args[1] || args[1].t !== 'number' || args[1].v.indexOf('.') !== -1) return empty();
            var times = parseInt(args[1].v, 10);
            if (times < 0 || times > 20) return empty();
            var pow = '1';
            for (var p = 0; p < times; p++) {
                pow = decimal.mul(pow, args[0].v);
                if (pow === null) return empty();
            }
            return num(pow);
        }
        if (node.op === 'mean' || node.op === 'min' || node.op === 'max' || node.op === 'count') {
            var bag = flatten(args);
            if (node.op === 'count') {
                if (!bag.some(function (item) { return item.t !== 'empty'; })) return empty();
                return num(String(bag.filter(function (item) { return item.t !== 'empty'; }).length));
            }
            var nums = bag.filter(function (item) { return item.t === 'number'; }).map(function (item) { return item.v; });
            if (!nums.length) return empty();
            if (node.op === 'min' || node.op === 'max') {
                var best = nums[0];
                nums.forEach(function (n) {
                    var cmp = decimal.cmp(n, best);
                    if ((node.op === 'min' && cmp < 0) || (node.op === 'max' && cmp > 0)) best = n;
                });
                return num(best);
            }
            var meanTotal = '0';
            nums.forEach(function (n) { meanTotal = decimal.add(meanTotal, n); });
            var mean = decimal.div(meanTotal, String(nums.length));
            return mean === null ? empty() : num(mean);
        }
        if (node.op === 'any_eq' || node.op === 'all_eq') {
            var list = args[0] && args[0].t === 'list' ? args[0].v : (args[0] ? [args[0]] : []);
            var needle = args[1] || empty();
            var seen = false;
            for (var i = 0; i < list.length; i++) {
                if (!list[i] || list[i].t === 'empty') continue;
                seen = true;
                var hit = compare('eq', list[i], needle);
                if (node.op === 'any_eq' && hit) return { t: 'bool', v: true };
                if (node.op === 'all_eq' && !hit) return { t: 'bool', v: false };
            }
            return { t: 'bool', v: node.op === 'all_eq' && seen };
        }
        if (node.op === 'is_empty') return { t: 'bool', v: !args[0] || args[0].t === 'empty' };
        if (node.op === 'concat') {
            var text = '';
            flatten(args).forEach(function (item) {
                if (item && item.t !== 'empty') text += String(item.v);
            });
            return text(text.slice(0, 2000));
        }
        if (node.op === 'today') return { t: 'date', v: ctx.today || '1970-01-01' };
        if (node.op === 'date_diff') return dateDiff(args);
        if (node.op === 'add_days' || node.op === 'add_months') return shiftDate(node.op, args);
        if (node.op === 'score_of') {
            var refNode = (node.args || [])[0];
            var scoreName = refNode && refNode.name ? String(refNode.name) : '';
            var scored = args[0];
            if (!scored || scored.t === 'empty' || !scoreName) return empty();
            var code = String(scored.v);
            var table = ctx.scores && ctx.scores[scoreName] ? ctx.scores[scoreName] : {};
            if (table[code] !== undefined && table[code] !== '') {
                var fromScore = decimal.canonical(String(table[code]));
                return fromScore === null ? empty() : num(fromScore);
            }
            var fromCode = decimal.canonical(code);
            return fromCode === null ? empty() : num(fromCode);
        }
        if (node.op === 'if') return truth(args[0]) ? args[1] : (args[2] || empty());
        if (node.op === 'min_valid') {
            if (!args[0] || args[0].t !== 'number') return empty();
            var rest = flatten(args.slice(1)).filter(function (item) { return item.t === 'number'; });
            if (rest.length < parseInt(args[0].v, 10)) return empty();
            var validTotal = '0';
            rest.forEach(function (item) { validTotal = decimal.add(validTotal, item.v); });
            return num(validTotal);
        }
        return empty();
    }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function parseIso(value) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        if (!match) return null;
        return { y: parseInt(match[1], 10), m: parseInt(match[2], 10), d: parseInt(match[3], 10) };
    }
    function dateDiff(args) {
        var start = parseIso(args[0] && args[0].t === 'date' ? args[0].v : '');
        var end = parseIso(args[1] && args[1].t === 'date' ? args[1].v : '');
        var unit = String(args[2] && args[2].v || '').toLowerCase();
        if (!start || !end) return empty();
        if (unit === 'days') {
            var ms = Date.UTC(end.y, end.m - 1, end.d) - Date.UTC(start.y, start.m - 1, start.d);
            return num(String(Math.round(ms / 86400000)));
        }
        var total = (end.y - start.y) * 12 + (end.m - start.m);
        if (end.d < start.d) total--;
        if (unit === 'months') return num(String(total));
        if (unit === 'years') {
            var whole = Math.trunc(total / 12);
            if (total < 0 && total % 12 !== 0) whole--;
            return num(String(whole));
        }
        return empty();
    }
    function shiftDate(op, args) {
        var date = parseIso(args[0] && args[0].t === 'date' ? args[0].v : '');
        if (!date || !args[1] || args[1].t !== 'number') return empty();
        var steps = parseInt(args[1].v, 10);
        if (op === 'add_days') {
            var utc = new Date(Date.UTC(date.y, date.m - 1, date.d + steps));
            return { t: 'date', v: utc.getUTCFullYear() + '-' + pad(utc.getUTCMonth() + 1) + '-' + pad(utc.getUTCDate()) };
        }
        var monthIndex = date.m - 1 + steps;
        var year = date.y + Math.floor(monthIndex / 12);
        var month = ((monthIndex % 12) + 12) % 12;
        var dim = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
        return { t: 'date', v: year + '-' + pad(month + 1) + '-' + pad(Math.min(date.d, dim)) };
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
    decimal.evaluateTree = function (tree, values) {
        return evaluate(tree, context(values));
    };
    decimal.treeTruth = function (tree, values) {
        return truth(evaluate(tree, context(values)));
    };
    return decimal;
}));
