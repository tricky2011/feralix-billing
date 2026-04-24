<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithApiResponses;

abstract class Controller
{
    use InteractsWithApiResponses;
}
