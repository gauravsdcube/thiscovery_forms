<?php

namespace humhub\modules\thiscoveryForms\services\formula;

final class Value
{
    public function __construct(public string $type, public mixed $data = null)
    {
    }

    public static function empty(): self
    {
        return new self('empty');
    }

    public static function number(string $canonical): self
    {
        return new self('number', $canonical);
    }

    public static function text(string $text): self
    {
        return new self('text', $text);
    }

    public static function bool(bool $value): self
    {
        return new self('bool', $value);
    }

    public static function date(string $iso): self
    {
        return new self('date', $iso);
    }

    /** @param list<Value> $items */
    public static function list(array $items): self
    {
        return new self('list', array_values($items));
    }

    public function isEmpty(): bool
    {
        return $this->type === 'empty';
    }

    public function truth(): bool
    {
        return match ($this->type) {
            'empty' => false,
            'bool' => $this->data === true,
            'number' => Decimal::cmp((string)$this->data, '0') !== 0,
            'text' => $this->data !== '' && $this->data !== '0',
            'list' => $this->data !== [],
            default => true,
        };
    }
}
