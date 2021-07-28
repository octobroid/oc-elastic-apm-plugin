<?php

namespace Octobro\ElasticApm;

use Octobro\ElasticApm\Collectors\EventDataCollector;
use Illuminate\Support\Collection;
use Nipwaayoni\AgentBuilder as NipwaayoniAgentBuilder;
use Nipwaayoni\ApmAgent;
use Nipwaayoni\Config;
use Nipwaayoni\Contexts\ContextCollection;
use Nipwaayoni\Events\EventFactoryInterface;
use Nipwaayoni\Middleware\Connector;
use Nipwaayoni\Stores\TransactionsStore;

class AgentBuilder extends NipwaayoniAgentBuilder
{
    public function withEventCollectors(Collection $collectors): self
    {
        $this->collectors = $collectors;

        return $this;
    }

    protected function newAgent(
        Config $config,
        ContextCollection $sharedContext,
        Connector $connector,
        EventFactoryInterface $eventFactory,
        TransactionsStore $transactionsStore): ApmAgent
    {

        if (null === $this->collectors) {
            $this->collectors = new Collection();
        }

        $agent = new Agent($config, $sharedContext, $connector, $eventFactory, $transactionsStore);

        $this->collectors->each(function (EventDataCollector $collector) use ($agent) {
            $agent->addCollector($collector);
        });

        return $agent;
    }
}
