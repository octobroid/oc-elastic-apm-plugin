<?php

namespace Octobro\ElasticApm\Facades;

use Illuminate\Support\Facades\Facade;

class ApmAgent extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'apm-agent';
    }
}
