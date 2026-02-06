<?php

declare(strict_types=1);

namespace Laravel\McpProviders;

use Laravel\McpProviders\Auth\McpAuthResolver;
use RuntimeException;

final class ConfigServerRepository
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $servers = config('mcp-providers.servers', []);

        if (! is_array($servers)) {
            return [];
        }

        return array_filter($servers, is_array(...));
    }

    /**
     * @param  list<string>  $slugs
     * @return array<string, array<string, mixed>>
     */
    public function selected(array $slugs = []): array
    {
        $servers = $this->all();

        if ($slugs === []) {
            return $servers;
        }

        $missing = array_values(array_diff($slugs, array_keys($servers)));

        if ($missing !== []) {
            throw new RuntimeException('Unknown MCP servers: '.implode(', ', $missing));
        }

        return array_intersect_key($servers, array_flip($slugs));
    }

    /**
     * @param  array<string, mixed>  $server
     * @return array<string, string>
     */
    public function headers(array $server): array
    {
        return app(McpAuthResolver::class)->headers($server);
    }

    /**
     * @param  array<string, mixed>  $server
     * @return array{attempts: int, backoff_ms: int, max_backoff_ms: int}
     */
    public function retry(array $server): array
    {
        $defaults = config('mcp-providers.retry', []);
        $serverRetry = $server['retry'] ?? [];

        $attempts = $this->positiveInt($serverRetry['attempts'] ?? null)
            ?? $this->positiveInt(is_array($defaults) ? ($defaults['attempts'] ?? null) : null)
            ?? 1;
        $backoffMs = $this->nonNegativeInt($serverRetry['backoff_ms'] ?? null)
            ?? $this->nonNegativeInt(is_array($defaults) ? ($defaults['backoff_ms'] ?? null) : null)
            ?? 100;
        $maxBackoffMs = $this->nonNegativeInt($serverRetry['max_backoff_ms'] ?? null)
            ?? $this->nonNegativeInt(is_array($defaults) ? ($defaults['max_backoff_ms'] ?? null) : null)
            ?? 1000;

        return [
            'attempts' => $attempts,
            'backoff_ms' => $backoffMs,
            'max_backoff_ms' => $maxBackoffMs,
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_int($value) && $value > 0 ? $value : null;
    }

    private function nonNegativeInt(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }
}
