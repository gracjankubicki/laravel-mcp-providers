# Laravel MCP Providers

Integracja wielu serwerow MCP z Laravel AI SDK przez workflow oparty o pliki:
`discover -> manifesty -> generate -> runtime`.

## Najwazniejsze funkcje
- Wiele serwerow MCP bez bazy danych.
- Stabilne nazwy narzedzi w runtime: `server_slug.tool_name`.
- Generowanie klas narzedzi z manifestow JSON.
- Retry/backoff i timeouty per serwer.
- Health check dla serwerow MCP.
- Allowlista narzedzi per agent.
- Auth per serwer przez klasy strategii (`auth.strategy`).

## Wymagania
- PHP `^8.5`
- Laravel `^12.0`
- `laravel/ai` `^0.1.2`

## Instalacja
```bash
composer require laravel/mcp-providers
```

Publikacja konfiguracji:
```bash
php artisan vendor:publish --tag=mcp-providers-config
```

## Konfiguracja
Plik: `config/ai-mcp.php`

```php
<?php

return [
    'servers' => [
        'gdocs' => [
            'endpoint' => env('MCP_GDOCS_URL'),
            'auth' => [
                'strategy' => \Laravel\McpProviders\Auth\BearerAuthStrategy::class,
                'token' => env('MCP_GDOCS_TOKEN'),
            ],
            'timeout' => 10,
            'retry' => [
                'attempts' => 3,
                'backoff_ms' => 150,
                'max_backoff_ms' => 1000,
            ],
            'manifest' => resource_path('mcp/gdocs.tools.json'),
        ],
        'n8n' => [
            'endpoint' => env('MCP_N8N_URL'),
            'auth' => [
                'strategy' => \Laravel\McpProviders\Auth\HeaderAuthStrategy::class,
                'header' => 'X-API-Key',
                'value' => env('MCP_N8N_KEY'),
            ],
            'timeout' => 15,
            'manifest' => resource_path('mcp/n8n.tools.json'),
        ],
    ],

    'retry' => [
        'attempts' => 1,
        'backoff_ms' => 100,
        'max_backoff_ms' => 1000,
    ],

    'generated' => [
        'path' => app_path('Ai/Tools/Generated'),
        'namespace' => 'App\\Ai\\Tools\\Generated',
    ],
];
```

## Workflow
### 1. Discover
Pobiera `tools/list` i zapisuje deterministyczne manifesty.

```bash
php artisan ai-mcp:discover
php artisan ai-mcp:discover --server=gdocs --dry-run
php artisan ai-mcp:discover --prune --fail-fast
```

### 2. Generate
Generuje klasy narzedzi z manifestow.

```bash
php artisan ai-mcp:generate
php artisan ai-mcp:generate --server=gdocs --clean
php artisan ai-mcp:generate --fail-on-collision
```

### 3. Sync
Uruchamia discover + generate.

```bash
php artisan ai-mcp:sync
```

### 4. Health
Sprawdza lacznosc z serwerami MCP.

```bash
php artisan ai-mcp:health
php artisan ai-mcp:health --server=gdocs --fail-fast
```

## Uzycie w agencie
```php
public function tools(): iterable
{
    return app(\Laravel\McpProviders\GeneratedToolRegistry::class)
        ->forServers(['gdocs', 'n8n']);
}
```

Allowlista narzedzi:
```php
public function tools(): iterable
{
    return app(\Laravel\McpProviders\GeneratedToolRegistry::class)
        ->forServers(
            ['gdocs', 'n8n'],
            ['gdocs' => ['search_docs'], 'n8n' => ['run_workflow']]
        );
}
```

## Wlasna strategia auth (per server)
```php
namespace App\Mcp\Auth;

use Laravel\McpProviders\Contracts\McpAuthStrategy;

final class TenantBearerAuthStrategy implements McpAuthStrategy
{
    public function headers(array $authConfig, ?string $serverSlug = null, ?string $toolName = null): array
    {
        $token = $authConfig['token'] ?? null;

        return is_string($token) && $token !== ''
            ? ['Authorization' => 'Bearer '.$token]
            : [];
    }
}
```

W configu serwera:
```php
'auth' => [
    'strategy' => \App\Mcp\Auth\TenantBearerAuthStrategy::class,
    'token' => env('MCP_TENANT_TOKEN'),
],
```

## Komendy developerskie
```bash
composer lint
composer test
composer test:coverage
```

## Licencja
MIT
