<?php

namespace CoopExchange\PestPluginBdd\Models;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class PestScenario extends Data
{
    public function __construct(
        public ? string $name,
        public int $startLine,
        public int $endLine,
        #[DataCollectionOf(PestStep::class)]
        public DataCollection $steps
    ) {
    }
}
