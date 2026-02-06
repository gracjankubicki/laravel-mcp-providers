<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Auth;

use Laravel\McpProviders\Contracts\McpAuthStrategy;

final class HeaderAuthStrategy implements McpAuthStrategy
{
    /**
     * @param  array<string, mixed>  $authConfig
     * @return array<string, string>
     */
    public function headers(array $authConfig, ?string $serverSlug = null, ?string $toolName = null): array
    {
        $header = $authConfig['header'] ?? null;
        $value = $authConfig['value'] ?? null;

        if (! is_string($header) || $header === '' || ! is_string($value)) {
            return [];
        }

        return [
            $header => $value,
        ];
    }
}
