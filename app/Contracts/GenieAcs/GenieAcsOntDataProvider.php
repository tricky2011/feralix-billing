<?php

namespace App\Contracts\GenieAcs;

use App\Data\GenieAcs\GenieAcsOntSnapshot;
use App\Models\Ont;

interface GenieAcsOntDataProvider
{
    public function name(): string;

    public function fetchOntSnapshot(Ont $ont): ?GenieAcsOntSnapshot;
}
