<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Contracts;

interface McpInvocationRouter
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function invoke(string $serverSlug, string $toolName, array $arguments): mixed;
}
