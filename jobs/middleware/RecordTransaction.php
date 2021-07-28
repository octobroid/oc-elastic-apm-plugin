<?php

namespace Octobro\ElasticApm\Jobs\Middleware;

use Octobro\ElasticApm\Facades\ApmCollector;

class RecordTransaction
{
    /**
     * Wrap the job processing in an APM transaction.
     *
     * @param mixed    $job
     * @param callable $next
     *
     * @return mixed
     */
    public function handle($job, $next)
    {
        if (false === config('octobro-elastic-apm.active') || false === config('octobro-elastic-apm.cli.active')) {
            return $next($job);
        }

        ApmCollector::startMeasure('job_processing', 'job', 'processing', get_class($job) . ' processing');

        $next($job);

        ApmCollector::stopMeasure('job_processing');
    }
}
