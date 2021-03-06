<?php

namespace Octobro\ElasticApm\Services;

use Octobro\ElasticApm\Agent;
use Illuminate\Contracts\Foundation\Application;
use Nipwaayoni\Events\Transaction;
use Psr\Http\Message\RequestInterface;

class ApmAgentService
{
    /**
     * @var Application
     */
    protected $app;
    /**
     * @var Agent
     */
    private $agent;

    public function __construct(Application $app, Agent $agent)
    {
        $this->app = $app;
        $this->agent = $agent;
    }

    public function getCurrentTransaction(): Transaction
    {
        return $this->agent->currentTransaction();
    }

    public function addTraceParentHeader(RequestInterface $request): RequestInterface
    {
        $transaction = $this->agent->currentTransaction();

        return $transaction->addTraceHeaderToRequest($request);
    }
}
