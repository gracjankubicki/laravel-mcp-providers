<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Contracts;

interface McpAuthStrategy
{
    /**
     * @param  array<string, mixed>  $authConfig
     * @return array<string, string>
     */
    public function headers(array $authConfig, ?string $serverSlug = null, ?string $toolName = null): array;
}
