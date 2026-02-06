<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Routing;

use Laravel\McpProviders\ConfigServerRepository;
use Laravel\McpProviders\Contracts\McpClient;
use Laravel\McpProviders\Contracts\McpInvocationRouter;
use RuntimeException;

final class DefaultMcpInvocationRouter implements McpInvocationRouter
{
    public function __construct(
        private readonly McpClient $client,
        private readonly ConfigServerRepository $servers,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function invoke(string $serverSlug, string $toolName, array $arguments): mixed
    {
        $serverConfig = $this->servers->selected([$serverSlug])[$serverSlug];

        $endpoint = $serverConfig['endpoint'] ?? null;

        if (! is_string($endpoint) || $endpoint === '') {
            throw new RuntimeException('Missing endpoint for MCP server ['.$serverSlug.'].');
        }

        $retry = $this->servers->retry($serverConfig);

        return $this->client->callTool(
            endpoint: $endpoint,
            toolName: $toolName,
            arguments: $arguments,
            headers: $this->servers->headers($serverConfig),
            timeout: (int) ($serverConfig['timeout'] ?? 60),
            retryAttempts: $retry['attempts'],
            retryBackoffMs: $retry['backoff_ms'],
            retryMaxBackoffMs: $retry['max_backoff_ms'],
        );
    }
}
