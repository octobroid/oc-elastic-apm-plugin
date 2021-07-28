<?php namespace Octobro\ElasticApm;

use Backend;
use System\Classes\PluginBase;
use Octobro\ElasticApm\Classes\Manager;

use Octobro\ElasticApm\Collectors\CommandCollector;
use Octobro\ElasticApm\Collectors\DBQueryCollector;
use Octobro\ElasticApm\Collectors\EventCounter;
use Octobro\ElasticApm\Collectors\FrameworkCollector;
use Octobro\ElasticApm\Collectors\HttpRequestCollector;
use Octobro\ElasticApm\Collectors\JobCollector;
use Octobro\ElasticApm\Collectors\RequestStartTime;
use Octobro\ElasticApm\Collectors\ScheduledTaskCollector;
use Octobro\ElasticApm\Collectors\SpanCollector;
use Octobro\ElasticApm\Contracts\VersionResolver;
use Octobro\ElasticApm\Middleware\RecordTransaction;
use Octobro\ElasticApm\Services\ApmAgentService;
use Octobro\ElasticApm\Services\ApmCollectorService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Nipwaayoni\Config;

/**
 * ElasticApm Plugin Information File
 */
class Plugin extends PluginBase
{
    public const COLLECTOR_TAG = 'event-collector';

    private $source_config_path = __DIR__ . '/config/octobro-elastic-apm.php';

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'ElasticApm',
            'description' => 'Log your data in Elastic APM using this plugin',
            'author'      => 'Octobro',
            'icon'        => 'icon-document'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom($this->source_config_path, 'octobro-elastic-apm');

        // Always available, even when inactive
        $this->registerFacades();

        // Create a single representation of the request start time which can be injected
        // to other classes.
        $this->app->singleton(RequestStartTime::class, function () {
            return new RequestStartTime($this->app['request']->server('REQUEST_TIME_FLOAT') ?? microtime(true));
        });

        $this->registerAgent();

        if (!$this->isAgentDisabled()) {
            $this->registerCollectors();
        }
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {
        $this->publishConfig();

        if ($this->isAgentDisabled()) {
            return;
        }

        $this->registerMiddleware();

        // If not collecting http events, the http middleware will not be executed and an
        // Agent will not exist prior to events occurring. Create one here to ensure the
        // collectors all register their listeners before any work is done. Unlike the
        // FrameWorkCollector, the JobCollector needs an Agent object so it cannot be
        // created independently and discovered by the ServiceProvider later.
        if (!$this->collectHttpEvents()) {
            $this->app->make(Agent::class);
        }
    }

    /**
     * Register Facades into the Service Container.
     */
    protected function registerFacades(): void
    {
        $this->app->bind('apm-collector', function ($app) {
            return $app->make(ApmCollectorService::class);
        });

        $this->app->bind('apm-agent', function ($app) {
            return $app->make(ApmAgentService::class);
        });
    }

    /**
     * Register the APM Agent into the Service Container.
     */
    protected function registerAgent(): void
    {
        $this->app->singleton(EventCounter::class, function () {
            $limit = config('octobro-elastic-apm.spans.maxTraceItems', EventCounter::EVENT_LIMIT);

            return new EventCounter($limit);
        });

        $this->app->singleton(Agent::class, function () {
            /** @var AgentBuilder $builder */
            $builder = $this->app->make(AgentBuilder::class);

            return $builder
                ->withConfig(new Config($this->getAgentConfig()))
                ->withEnvData(config('octobro-elastic-apm.env.env'))
                ->withEventCollectors(collect($this->app->tagged(self::COLLECTOR_TAG)))
                ->build();
        });

        // Register a callback on terminating to send the events
        $this->app->terminating(function () {
            /** @var Agent $agent */
            $agent = $this->app->make(Agent::class);

            $agent->send();
        });
    }

     /**
     * Add the middleware to the very top of the list,
     * aiming to have better time measurements.
     */
    protected function registerMiddleware(): void
    {
        $kernel = $this->app->make(Kernel::class);
        $kernel->prependMiddleware(RecordTransaction::class);
    }

    /**
     * Register data collectors and start listening for events. Most collectors are
     * registered by tagging the abstracts in the service container. The concreate
     * implementations are not created during registration.
     *
     * All collectors which must be created prior to the boot phase should ensure
     * they have no dependencies on other services which may not be registered yet.
     *
     * All tagged collectors will be gathered and given to the Agent when it is created.
     */
    protected function registerCollectors(): void
    {
        if ($this->collectFrameworkEvents()) {
            // Force the FrameworkCollector instance to be created and used. While this appears odd,
            // the collector instance registers itself to listen for booting events, so that instance
            // must be made available for collection later.
            $this->app->instance(FrameworkCollector::class, $this->app->make(FrameworkCollector::class));

            $this->app->tag(FrameworkCollector::class, self::COLLECTOR_TAG);
        }

        if (false !== config('octobro-elastic-apm.spans.querylog.enabled')) {
            // DB Queries collector
            $this->app->tag(DBQueryCollector::class, self::COLLECTOR_TAG);
        }

        // Http request collector
        if ($this->collectHttpEvents()) {
            $this->app->tag(HttpRequestCollector::class, self::COLLECTOR_TAG);
        } else {
            $this->app->tag(CommandCollector::class, self::COLLECTOR_TAG);
            $this->app->tag(ScheduledTaskCollector::class, self::COLLECTOR_TAG);
        }

        // Job collector
        $this->app->tag(JobCollector::class, self::COLLECTOR_TAG);

        // Collector for manual measurements throughout the app
        $this->app->tag(SpanCollector::class, self::COLLECTOR_TAG);
    }

    private function collectFrameworkEvents(): bool
    {
        // For cli executions, like queue workers, the application only
        // starts once. It doesn't really make sense to measure freamework events.
        return !$this->app->runningInConsole();
    }

    private function collectHttpEvents(): bool
    {
        return !$this->app->runningInConsole();
    }

    /**
     * Publish the config file.
     *
     * @param string $configPath
     */
    protected function publishConfig(): void
    {
        $this->publishes([$this->source_config_path => $this->getConfigPath()], 'config');
    }

    /**
     * Get the config path.
     */
    protected function getConfigPath(): string
    {
        return config_path('octobro-elastic-apm.php');
    }

    protected function getAgentConfig(): array
    {
        return array_merge(
                [
                    'defaultServiceName' => config('octobro-elastic-apm.app.appName'),
                    'frameworkName' => 'Laravel',
                    'frameworkVersion' => app()->version(),
                    'active' => config('octobro-elastic-apm.active'),
                    'environment' => config('octobro-elastic-apm.env.environment'),
                    'logLevel' => config('octobro-elastic-apm.log-level', 'error'),
                ],
                $this->getAppConfig(),
                config('octobro-elastic-apm.server'),
                config('octobro-elastic-apm.agent')
            );
    }

    protected function getAppConfig(): array
    {
        $config = config('octobro-elastic-apm.app');
        if ($this->app->bound(VersionResolver::class)) {
            $config['appVersion'] = $this->app->make(VersionResolver::class)->getVersion();
        }

        return $config;
    }

    private function isAgentDisabled(): bool
    {
        return false === config('octobro-elastic-apm.active')
            || ($this->app->runningInConsole() && false === config('octobro-elastic-apm.cli.active'));
    }

}
