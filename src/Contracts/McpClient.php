<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Contracts;

interface McpClient
{
    /**
     * @param  array<string, string>  $headers
     * @return list<array<string, mixed>>
     */
    public function listTools(
        string $endpoint,
        array $headers = [],
        int $timeout = 60,
        int $retryAttempts = 1,
        int $retryBackoffMs = 100,
        int $retryMaxBackoffMs = 1000,
    ): array;

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, string>  $headers
     */
    public function callTool(
        string $endpoint,
        string $toolName,
        array $arguments,
        array $headers = [],
        int $timeout = 60,
        int $retryAttempts = 1,
        int $retryBackoffMs = 100,
        int $retryMaxBackoffMs = 1000,
    ): mixed;
}
