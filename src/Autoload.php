<?php

declare(strict_types=1);

namespace Vmeretail\PestPluginBdd;

use function Pest\Laravel\{get};

function step_I_should_be_be_able_to_see($parameter)
{
    get('/')->assertSee($parameter);
}
