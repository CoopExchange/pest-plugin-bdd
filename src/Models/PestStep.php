<?php

namespace CoopExchange\PestPluginBdd\Models;

use Spatie\LaravelData\Data;

final class PestStep extends Data
{
    public function __construct(
        public string $name,
        public int $startLine,
        public int $endLine,
        public int $functionStartLine
    ) {
    }
}
