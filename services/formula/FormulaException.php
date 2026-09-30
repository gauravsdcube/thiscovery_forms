<?php

namespace humhub\modules\thiscoveryForms\services\formula;

final class FormulaException extends \RuntimeException
{
    public function __construct(string $message, public int $line = 1, public int $column = 1)
    {
        parent::__construct($message . ' (line ' . $line . ', column ' . $column . ')');
    }
}
