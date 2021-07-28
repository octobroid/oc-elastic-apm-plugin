<?php

namespace Octobro\ElasticApm;

class EventClock
{
    public function microtime(): float
    {
        return microtime(true);
    }
}
