<?php

namespace Vmeretail\PestPluginBdd\Models;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class PestFeature extends Data
{
    public function __construct(
        public string $name,
        //public string $description,
        public int $startLine,
        public int $endLine,
        //#[DataCollectionOf(PestStep::class)]
        //public DataCollection $steps
    ) {
    }
}
