<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Auth;

use Laravel\McpProviders\Contracts\McpAuthStrategy;

final class BearerAuthStrategy implements McpAuthStrategy
{
    /**
     * @param  array<string, mixed>  $authConfig
     * @return array<string, string>
     */
    public function headers(array $authConfig, ?string $serverSlug = null, ?string $toolName = null): array
    {
        $token = $authConfig['token'] ?? null;

        if (! is_string($token) || $token === '') {
            return [];
        }

        return [
            'Authorization' => 'Bearer '.$token,
        ];
    }
}
