<?php

namespace Octobro\ElasticApm\Collectors;

use Octobro\ElasticApm\Contracts\DataCollector;
use Octobro\ElasticApm\Events\StartMeasuring;
use Octobro\ElasticApm\Events\StopMeasuring;

/**
 * Generic collector for spans measured manually throughout the app.
 */
class SpanCollector extends EventDataCollector implements DataCollector
{
    public function getName(): string
    {
        return 'span-collector';
    }

    public function registerEventListeners(): void
    {
        $this->app->events->listen(StartMeasuring::class, function (StartMeasuring $event) {
            $this->startMeasure(
                $event->name,
                $event->type,
                $event->action,
                $event->label,
                $event->start_time
            );
        });

        $this->app->events->listen(StopMeasuring::class, function (StopMeasuring $event) {
            $this->stopMeasure($event->name, $event->params);
        });
    }
}
