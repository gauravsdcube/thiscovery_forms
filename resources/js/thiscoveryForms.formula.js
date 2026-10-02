/**
 * Decimal strings at scale 12. Halves round away from zero.
 * This is the same routine as services/formula/Decimal.php.
 */
(function (root, factory) {
    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }
    root.thiscoveryFormula = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    var SCALE = 12;
    // Must match Decimal::MAX_WHOLE_DIGITS and Decimal::MAX_TEXT in PHP (V3-4).
    var MAX_WHOLE_DIGITS = 30;
    var MAX_TEXT = 64;

    function canonical(text) {
        var parsed = parse(text);
        if (!parsed) {
            return null;
        }
        return format(parsed[0], parsed[1], SCALE);
    }

    function add(left, right) {
        var a = parse(left);
        var b = parse(right);
        if (!a || !b) {
            return null;
        }
        var sum = addScaled(a, b);
        return format(sum[0], sum[1], SCALE);
    }

    function sub(left, right) {
        var negative = negate(right);
        if (negative === null) {
            return null;
        }
        return add(left, negative);
    }

    function mul(left, right) {
        var a = parse(left);
        var b = parse(right);
        if (!a || !b) {
            return null;
        }
        var product = mulInt(a[1], b[1]);
        var rounded = divIntRound(product, pow10(SCALE));
        return format(a[0] * b[0], rounded, SCALE);
    }

    function div(left, right) {
        var a = parse(left);
        var b = parse(right);
        if (!a || !b || b[1] === '0') {
            return null;
        }
        var numerator = mulInt(a[1], pow10(SCALE));
        var rounded = divIntRound(numerator, b[1]);
        return format(a[0] * b[0], rounded, SCALE);
    }

    function round(text, places) {
        if (places < 0 || places > SCALE) {
            return null;
        }
        var parsed = parse(text);
        if (!parsed) {
            return null;
        }
        var drop = SCALE - places;
        var rounded = drop === 0 ? parsed[1] : divIntRound(parsed[1], pow10(drop));
        var scaled = drop === 0 ? rounded : mulInt(rounded, pow10(drop));
        return format(parsed[0], scaled, SCALE);
    }

    function cmp(left, right) {
        var a = parse(left);
        var b = parse(right);
        if (!a || !b) {
            return null;
        }
        if (a[1] === '0' && b[1] === '0') {
            return 0;
        }
        if (a[0] !== b[0]) {
            return a[0] < b[0] ? -1 : 1;
        }
        var order = cmpInt(a[1], b[1]);
        return a[0] < 0 ? -order : order;
    }

    function negate(text) {
        var parsed = parse(text);
        if (!parsed) {
            return null;
        }
        if (parsed[1] === '0') {
            return '0';
        }
        return format(-parsed[0], parsed[1], SCALE);
    }

    function parse(text) {
        text = String(text).trim();
        if (text.length > MAX_TEXT || !/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/.test(text)) {
            return null;
        }
        var sign = 1;
        if (text.charAt(0) === '-') {
            sign = -1;
            text = text.slice(1);
        }
        var parts = text.split('.');
        var whole = parts[0];
        var frac = parts.length > 1 ? parts[1] : '';
        if (whole.replace(/^0+/, '').length > MAX_WHOLE_DIGITS) {
            return null;
        }
        var roundUp = false;
        if (frac.length > SCALE) {
            roundUp = frac.charAt(SCALE) >= '5';
            frac = frac.slice(0, SCALE);
        }
        while (frac.length < SCALE) {
            frac += '0';
        }
        var digits = (whole + frac).replace(/^0+/, '');
        if (digits === '') {
            digits = '0';
        }
        if (roundUp) {
            digits = addInt(digits, '1');
        }
        if (digits === '0') {
            return [1, '0'];
        }
        return [sign, digits];
    }

    function format(sign, digits, scale) {
        if (digits === '0') {
            return '0';
        }
        if (digits.replace(/^0+/, '').length > scale + MAX_WHOLE_DIGITS) {
            return null;
        }
        while (digits.length < scale + 1) {
            digits = '0' + digits;
        }
        var whole = digits.slice(0, -scale);
        var frac = digits.slice(-scale).replace(/0+$/, '');
        var body = frac === '' ? whole : whole + '.' + frac;
        return sign < 0 ? '-' + body : body;
    }

    function addScaled(a, b) {
        if (a[1] === '0') {
            return b;
        }
        if (b[1] === '0') {
            return a;
        }
        if (a[0] === b[0]) {
            return [a[0], addInt(a[1], b[1])];
        }
        var order = cmpInt(a[1], b[1]);
        if (order === 0) {
            return [1, '0'];
        }
        if (order > 0) {
            return [a[0], subInt(a[1], b[1])];
        }
        return [b[0], subInt(b[1], a[1])];
    }

    function addInt(a, b) {
        a = a.replace(/^0+/, '') || '0';
        b = b.replace(/^0+/, '') || '0';
        var carry = 0;
        var out = '';
        var i = a.length - 1;
        var j = b.length - 1;
        while (i >= 0 || j >= 0 || carry) {
            var sum = carry;
            if (i >= 0) {
                sum += a.charCodeAt(i) - 48;
                i--;
            }
            if (j >= 0) {
                sum += b.charCodeAt(j) - 48;
                j--;
            }
            out = String(sum % 10) + out;
            carry = Math.floor(sum / 10);
        }
        return out.replace(/^0+/, '') || '0';
    }

    function subInt(a, b) {
        var borrow = 0;
        var out = '';
        var i = a.length - 1;
        var j = b.length - 1;
        while (i >= 0) {
            var digit = (a.charCodeAt(i) - 48) - borrow;
            if (j >= 0) {
                digit -= b.charCodeAt(j) - 48;
                j--;
            }
            if (digit < 0) {
                digit += 10;
                borrow = 1;
            } else {
                borrow = 0;
            }
            out = String(digit) + out;
            i--;
        }
        return out.replace(/^0+/, '') || '0';
    }

    function mulInt(a, b) {
        if (a === '0' || b === '0') {
            return '0';
        }
        var result = '0';
        var shift = 0;
        for (var j = b.length - 1; j >= 0; j--, shift++) {
            var digit = b.charCodeAt(j) - 48;
            if (digit === 0) {
                continue;
            }
            var carry = 0;
            var part = '';
            for (var z = 0; z < shift; z++) {
                part += '0';
            }
            for (var i = a.length - 1; i >= 0; i--) {
                var prod = (a.charCodeAt(i) - 48) * digit + carry;
                part = String(prod % 10) + part;
                carry = Math.floor(prod / 10);
            }
            if (carry) {
                part = String(carry) + part;
            }
            result = addInt(result, part);
        }
        return result;
    }

    function divIntRound(numerator, denominator) {
        if (numerator === '0') {
            return '0';
        }
        var quotient = '0';
        var remainder = '0';
        for (var i = 0; i < numerator.length; i++) {
            remainder = addInt(mulInt(remainder, '10'), numerator.charAt(i));
            var digit = 0;
            while (cmpInt(remainder, denominator) >= 0) {
                remainder = subInt(remainder, denominator);
                digit++;
                if (digit === 10) {
                    break;
                }
            }
            quotient = addInt(mulInt(quotient, '10'), String(digit));
        }
        var twice = mulInt(remainder, '2');
        if (cmpInt(twice, denominator) >= 0) {
            quotient = addInt(quotient, '1');
        }
        return quotient;
    }

    function cmpInt(a, b) {
        a = a.replace(/^0+/, '') || '0';
        b = b.replace(/^0+/, '') || '0';
        if (a.length !== b.length) {
            return a.length < b.length ? -1 : 1;
        }
        if (a === b) {
            return 0;
        }
        return a < b ? -1 : 1;
    }

    function pow10(places) {
        return '1' + new Array(places + 1).join('0');
    }

    return {
        SCALE: SCALE,
        canonical: canonical,
        add: add,
        sub: sub,
        mul: mul,
        div: div,
        round: round,
        cmp: cmp,
        negate: negate
    };
}));
