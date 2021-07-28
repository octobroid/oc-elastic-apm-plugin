<?php

namespace Octobro\ElasticApm\Contracts;

interface VersionResolver
{
    public function getVersion(): string;
}
