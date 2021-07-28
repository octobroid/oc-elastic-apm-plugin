<?php namespace Octobro\ElasticApm\Classes;

use Nipwaayoni\AgentBuilder;
use Nipwaayoni\Config;

class Manager
{
    protected $config, $agent;

    protected $endpoint, $method, $payload, $response;

    public function __construct()
    {
        $this->agent = new AgentBuilder();
    }

    public function setNewConfig(array $config)
    {
        $this->agent->withConfig(new Config($config));
        return $this;
    }

    public function setTagData(array $tags)
    {
        $this->agent->withTagData($tags);
        return $this;
    }

    public function setEnvData(array $env)
    {
        $this->agent->withEnvData($env);
        return $this;
    }

    public function setCookieData(array $cookies)
    {
        $this->agent->withCookieData($cookies);
        return $this;
    }

    public function setMethod(string $method)
    {
        $this->method = strtoupper($method);
        return $this;
    }

    public function setEndpoint(string $endpoint)
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function setPayload($payload)
    {
        $this->payload = $payload;
        return $this;
    }

    public function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }

    public function buildAgent()
    {
        return $this->agent->build();
    }

    public function sendAgentData($agent)
    {
        $agent->send();
    }
}