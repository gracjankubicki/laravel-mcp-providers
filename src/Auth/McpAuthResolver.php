<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Auth;

use Laravel\McpProviders\Contracts\McpAuthStrategy;
use RuntimeException;

final class McpAuthResolver
{
    /**
     * @param  array<string, mixed>  $serverConfig
     * @return array<string, string>
     */
    public function headers(array $serverConfig, ?string $serverSlug = null, ?string $toolName = null): array
    {
        $auth = $serverConfig['auth'] ?? null;

        if (! is_array($auth)) {
            return [];
        }

        $strategyClass = $this->strategyClass($auth);

        $strategy = $this->makeStrategy($strategyClass);

        return $strategy->headers($auth, $serverSlug, $toolName);
    }

    /**
     * @param  array<string, mixed>  $auth
     */
    private function strategyClass(array $auth): string
    {
        $strategy = $auth['strategy'] ?? null;

        if (! is_string($strategy) || $strategy === '') {
            throw new RuntimeException('Missing MCP auth strategy in `auth.strategy`.');
        }

        return $strategy;
    }

    /**
     * @param  class-string  $strategyClass
     */
    private function makeStrategy(string $strategyClass): McpAuthStrategy
    {
        if (! class_exists($strategyClass)) {
            throw new RuntimeException('MCP auth strategy class does not exist: '.$strategyClass);
        }

        $strategy = app()->make($strategyClass);

        if (! $strategy instanceof McpAuthStrategy) {
            throw new RuntimeException('MCP auth strategy must implement McpAuthStrategy: '.$strategyClass);
        }

        return $strategy;
    }
}
