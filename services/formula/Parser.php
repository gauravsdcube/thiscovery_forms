<?php

namespace humhub\modules\thiscoveryForms\services\formula;

/**
 * Author text to an ADR-010 syntax tree. The fill page does not parse text.
 */
final class Parser
{
    private const KEYWORDS = ['and', 'or', 'not', 'in', 'not_in', 'true', 'false', 'empty', 'date', 'datetime'];

    /** Functions a formula may call. Anything else is an error, not a silent blank (V3-54). */
    public const FUNCTIONS = [
        'and', 'or', 'not', 'sum', 'mean', 'min', 'max', 'count', 'count_eq', 'count_answered', 'count_selected',
        'round', 'floor', 'ceil', 'abs', 'sqrt', 'pow', 'if', 'coalesce', 'ifempty', 'min_valid', 'concat', 'lower',
        'upper', 'length', 'contains_text', 'today', 'date_diff', 'add_days', 'add_months', 'year', 'month',
        'any_eq', 'all_eq', 'between', 'is_empty', 'is_answered', 'selected', 'selected_all', 'selected_only',
        'code_of', 'score_of',
    ];

    /** @var list<array{t:string,v:string,line:int,col:int}> */
    private array $tokens = [];
    private int $index = 0;

    /** @return array<string,mixed> */
    public function parse(string $text): array
    {
        Limits::assertText($text);
        $this->tokens = $this->lex($text);
        $this->index = 0;
        if ($this->tokens === [] || $this->peek()['t'] === 'eof') {
            throw new FormulaException('Enter a formula.', 1, 1);
        }
        $tree = $this->parseOr();
        if ($this->peek()['t'] !== 'eof') {
            $token = $this->peek();
            throw new FormulaException('Unexpected “' . $token['v'] . '”.', $token['line'], $token['col']);
        }
        Limits::assertTree($tree);
        return $tree;
    }

    /** @return array<string,mixed> */
    private function parseOr(): array
    {
        $left = $this->parseAnd();
        while ($this->eatKeyword('or')) {
            $left = ['op' => 'or', 'args' => [$left, $this->parseAnd()]];
        }
        return $left;
    }

    /** @return array<string,mixed> */
    private function parseAnd(): array
    {
        $left = $this->parseNot();
        while ($this->eatKeyword('and')) {
            $left = ['op' => 'and', 'args' => [$left, $this->parseNot()]];
        }
        return $left;
    }

    /** @return array<string,mixed> */
    private function parseNot(): array
    {
        if ($this->eatKeyword('not')) {
            return ['op' => 'not', 'args' => [$this->parseNot()]];
        }
        return $this->parseCmp();
    }

    /** @return array<string,mixed> */
    private function parseCmp(): array
    {
        $left = $this->parseAdd();
        if ($this->eatKeyword('in')) {
            return ['op' => 'in', 'args' => [$left, $this->parseList()]];
        }
        if ($this->eatKeyword('not_in')) {
            return ['op' => 'not_in', 'args' => [$left, $this->parseList()]];
        }
        $ops = ['=' => 'eq', '!=' => 'ne', '>' => 'gt', '>=' => 'gte', '<' => 'lt', '<=' => 'lte'];
        $token = $this->peek();
        if (isset($ops[$token['t']])) {
            $this->index++;
            $right = $this->parseAdd();
            if (isset($ops[$this->peek()['t']]) || $this->keywordIs('in') || $this->keywordIs('not_in')) {
                throw new FormulaException('Comparisons cannot be chained.', $token['line'], $token['col']);
            }
            return ['op' => $ops[$token['t']], 'args' => [$left, $right]];
        }
        return $left;
    }

    /** @return array<string,mixed> */
    private function parseAdd(): array
    {
        $left = $this->parseMul();
        while (in_array($this->peek()['t'], ['+', '-'], true)) {
            $op = $this->tokens[$this->index++]['t'] === '+' ? 'add' : 'sub';
            $left = ['op' => $op, 'args' => [$left, $this->parseMul()]];
        }
        return $left;
    }

    /** @return array<string,mixed> */
    private function parseMul(): array
    {
        $left = $this->parsePow();
        while (in_array($this->peek()['t'], ['*', '/', '%'], true)) {
            $token = $this->tokens[$this->index++]['t'];
            $op = $token === '*' ? 'mul' : ($token === '/' ? 'div' : 'mod');
            $left = ['op' => $op, 'args' => [$left, $this->parsePow()]];
        }
        return $left;
    }

    /** @return array<string,mixed> */
    private function parsePow(): array
    {
        $left = $this->parseUnary();
        if ($this->peek()['t'] === '^') {
            $this->index++;
            return ['op' => 'pow', 'args' => [$left, $this->parsePow()]];
        }
        return $left;
    }

    /** @return array<string,mixed> */
    private function parseUnary(): array
    {
        if ($this->peek()['t'] === '+') {
            $this->index++;
            return $this->parseUnary();
        }
        if ($this->peek()['t'] === '-') {
            $this->index++;
            return ['op' => 'neg', 'args' => [$this->parseUnary()]];
        }
        return $this->parsePrimary();
    }

    /** @return array<string,mixed> */
    private function parsePrimary(): array
    {
        $token = $this->peek();
        if ($token['t'] === '(') {
            $this->index++;
            $inner = $this->parseOr();
            if ($this->peek()['t'] !== ')') {
                throw new FormulaException('Expected ).', $token['line'], $token['col']);
            }
            $this->index++;
            return $inner;
        }
        if ($token['t'] === 'number') {
            $this->index++;
            $canonical = Decimal::canonical($token['v']);
            if ($canonical === null) {
                throw new FormulaException('“' . $token['v'] . '” is not a number.', $token['line'], $token['col']);
            }
            return ['op' => 'lit', 'lit' => 'number', 'v' => $canonical];
        }
        if ($token['t'] === 'string') {
            $this->index++;
            return ['op' => 'lit', 'lit' => 'text', 'v' => $token['v']];
        }
        if ($token['t'] === 'ref') {
            $this->index++;
            return $this->reference($token['v'], $token['line'], $token['col']);
        }
        if ($this->eatKeyword('true')) {
            return ['op' => 'lit', 'lit' => 'bool', 'v' => true];
        }
        if ($this->eatKeyword('false')) {
            return ['op' => 'lit', 'lit' => 'bool', 'v' => false];
        }
        if ($this->eatKeyword('empty')) {
            return ['op' => 'lit', 'lit' => 'empty'];
        }
        if ($this->eatKeyword('date')) {
            return $this->dateCall('date', $token);
        }
        if ($this->eatKeyword('datetime')) {
            return $this->dateCall('datetime', $token);
        }
        if ($token['t'] === 'ident' && $token['v'] === 'fn' && (($this->tokens[$this->index + 1]['t'] ?? '') === ':')) {
            $name = $this->tokens[$this->index + 2] ?? null;
            if (!$name || $name['t'] !== 'ident') {
                throw new FormulaException('A named formula needs a name.', $token['line'], $token['col']);
            }
            $this->index += 3;
            return ['op' => 'fn', 'name' => $name['v']];
        }
        if ($token['t'] === 'ident') {
            $this->index++;
            if ($this->peek()['t'] !== '(') {
                throw new FormulaException('Expected ( after ' . $token['v'] . '.', $token['line'], $token['col']);
            }
            if (!in_array(strtolower($token['v']), self::FUNCTIONS, true)) {
                throw new FormulaException('Unknown function ' . $token['v'] . '.', $token['line'], $token['col']);
            }
            $this->index++;
            $args = [];
            if ($this->peek()['t'] !== ')') {
                $args[] = $this->parseOr();
                while ($this->peek()['t'] === ',') {
                    $this->index++;
                    $args[] = $this->parseOr();
                }
            }
            if ($this->peek()['t'] !== ')') {
                throw new FormulaException('Expected ).', $token['line'], $token['col']);
            }
            $this->index++;
            return ['op' => $token['v'], 'args' => $args];
        }
        throw new FormulaException('Expected a value.', $token['line'], $token['col']);
    }

    /** @param array{t:string,v:string,line:int,col:int} $token
     * @return array<string,mixed>
     */
    private function dateCall(string $kind, array $token): array
    {
        if ($this->peek()['t'] !== '(') {
            throw new FormulaException('Expected ( after ' . $kind . '.', $token['line'], $token['col']);
        }
        $this->index++;
        $arg = $this->peek();
        if ($arg['t'] !== 'string') {
            throw new FormulaException($kind . ' expects a quoted date.', $arg['line'], $arg['col']);
        }
        $this->index++;
        if ($this->peek()['t'] !== ')') {
            throw new FormulaException('Expected ).', $token['line'], $token['col']);
        }
        $this->index++;
        // A real calendar date: 2024-2-3 used to compare as text, and 2024-02-30 was accepted (V3-54).
        $pattern = $kind === 'date' ? '/^(\d{4})-(\d{2})-(\d{2})$/' : '/^(\d{4})-(\d{2})-(\d{2})[T ]([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/';
        if (!preg_match($pattern, (string)$arg['v'], $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            throw new FormulaException($kind . ' expects a real date written YYYY-MM-DD' . ($kind === 'datetime' ? ' HH:MM' : '') . '.', $arg['line'], $arg['col']);
        }
        return ['op' => 'lit', 'lit' => $kind, 'v' => $arg['v']];
    }

    /** @return array<string,mixed> */
    private function parseList(): array
    {
        if ($this->peek()['t'] !== '[') {
            $token = $this->peek();
            throw new FormulaException('Expected a list.', $token['line'], $token['col']);
        }
        $this->index++;
        $args = [];
        if ($this->peek()['t'] !== ']') {
            $args[] = $this->parseOr();
            while ($this->peek()['t'] === ',') {
                $this->index++;
                $args[] = $this->parseOr();
            }
        }
        if ($this->peek()['t'] !== ']') {
            $token = $this->peek();
            throw new FormulaException('Expected ].', $token['line'], $token['col']);
        }
        $this->index++;
        return ['op' => 'list', 'args' => $args];
    }

    /** @return array<string,mixed> */
    private function reference(string $body, int $line, int $col): array
    {
        if ($body === 'arm') {
            return ['op' => 'ref', 'ref' => 'arm'];
        }
        if (preg_match('/^(var|panel|meta|url):([A-Za-z_][A-Za-z0-9_]*)$/', $body, $match)) {
            return ['op' => 'ref', 'ref' => $match[1] === 'var' ? 'var' : $match[1], 'name' => $match[2]];
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\[\*\]$/', $body, $match)) {
            return ['op' => 'ref', 'ref' => 'field', 'name' => $match[1], 'all' => true];
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\["([^"]+)"\]$/', $body, $match)) {
            return ['op' => 'ref', 'ref' => 'field', 'name' => $match[1], 'instance' => $match[2]];
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\.([A-Za-z_][A-Za-z0-9_]*)$/', $body, $match)) {
            return ['op' => 'ref', 'ref' => 'field', 'name' => $match[1], 'row' => $match[2]];
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $body)) {
            return ['op' => 'ref', 'ref' => 'field', 'name' => $body];
        }
        throw new FormulaException('“[' . $body . ']” is not a question or variable.', $line, $col);
    }

    private function eatKeyword(string $word): bool
    {
        $token = $this->peek();
        if ($token['t'] === 'ident' && $token['v'] === $word) {
            $this->index++;
            return true;
        }
        return false;
    }

    private function keywordIs(string $word): bool
    {
        $token = $this->peek();
        return $token['t'] === 'ident' && $token['v'] === $word;
    }

    /** @return array{t:string,v:string,line:int,col:int} */
    private function peek(): array
    {
        return $this->tokens[$this->index] ?? ['t' => 'eof', 'v' => '', 'line' => 1, 'col' => 1];
    }

    /** @return list<array{t:string,v:string,line:int,col:int}> */
    private function lex(string $text): array
    {
        $tokens = [];
        $length = strlen($text);
        $i = 0;
        $line = 1;
        $col = 1;
        while ($i < $length) {
            $ch = $text[$i];
            if ($ch === "\n") {
                $line++;
                $col = 1;
                $i++;
                continue;
            }
            if (ctype_space($ch)) {
                $col++;
                $i++;
                continue;
            }
            $last = $tokens ? $tokens[count($tokens) - 1] : null;
            if ($ch === '[' && $last && $last['t'] === 'ident' && in_array(strtolower($last['v']), ['in', 'not_in'], true)) {
                // After in / not_in a bracket opens a list ([a] in [1, 2]), not a reference (V3-38).
                $tokens[] = ['t' => '[', 'v' => '[', 'line' => $line, 'col' => $col];
                $i++;
                $col++;
                continue;
            }
            if ($ch === '[') {
                $startCol = $col;
                $i++;
                $col++;
                $body = '';
                $depth = 1;
                while ($i < $length && $depth > 0) {
                    if ($text[$i] === '[') {
                        $depth++;
                    } elseif ($text[$i] === ']') {
                        $depth--;
                        if ($depth === 0) {
                            $i++;
                            $col++;
                            break;
                        }
                    }
                    $body .= $text[$i];
                    $col++;
                    $i++;
                }
                if ($depth !== 0) {
                    throw new FormulaException('Expected ].', $line, $startCol);
                }
                $tokens[] = ['t' => 'ref', 'v' => $body, 'line' => $line, 'col' => $startCol];
                continue;
            }
            if ($ch === '"') {
                $startCol = $col;
                $i++;
                $col++;
                $value = '';
                while ($i < $length && $text[$i] !== '"') {
                    if ($text[$i] === '\\' && $i + 1 < $length) {
                        $value .= $text[$i + 1];
                        $i += 2;
                        $col += 2;
                        continue;
                    }
                    $value .= $text[$i];
                    $i++;
                    $col++;
                }
                if ($i >= $length || $text[$i] !== '"') {
                    throw new FormulaException('A quote is not closed.', $line, $startCol);
                }
                $i++;
                $col++;
                $tokens[] = ['t' => 'string', 'v' => $value, 'line' => $line, 'col' => $startCol];
                continue;
            }
            if (ctype_digit($ch)) {
                $startCol = $col;
                $raw = '';
                while ($i < $length && (ctype_digit($text[$i]) || $text[$i] === '.')) {
                    $raw .= $text[$i];
                    $i++;
                    $col++;
                }
                $tokens[] = ['t' => 'number', 'v' => $raw, 'line' => $line, 'col' => $startCol];
                continue;
            }
            if (ctype_alpha($ch) || $ch === '_') {
                $startCol = $col;
                $raw = '';
                while ($i < $length && (ctype_alnum($text[$i]) || $text[$i] === '_')) {
                    $raw .= $text[$i];
                    $i++;
                    $col++;
                }
                $tokens[] = ['t' => 'ident', 'v' => $raw, 'line' => $line, 'col' => $startCol];
                continue;
            }
            $two = substr($text, $i, 2);
            if (in_array($two, ['!=', '>=', '<='], true)) {
                $tokens[] = ['t' => $two, 'v' => $two, 'line' => $line, 'col' => $col];
                $i += 2;
                $col += 2;
                continue;
            }
            $tokens[] = ['t' => $ch, 'v' => $ch, 'line' => $line, 'col' => $col];
            $i++;
            $col++;
        }
        $tokens[] = ['t' => 'eof', 'v' => '', 'line' => $line, 'col' => $col];
        return $tokens;
    }
}
