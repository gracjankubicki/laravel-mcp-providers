# MCP Providers for Laravel AI

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gracjankubicki/laravel-mcp-providers.svg?label=packagist)](https://packagist.org/packages/gracjankubicki/laravel-mcp-providers)
[![Total Downloads](https://img.shields.io/packagist/dt/gracjankubicki/laravel-mcp-providers.svg)](https://packagist.org/packages/gracjankubicki/laravel-mcp-providers)
[![PHP Version](https://img.shields.io/packagist/php-v/gracjankubicki/laravel-mcp-providers.svg)](https://packagist.org/packages/gracjankubicki/laravel-mcp-providers)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20.svg)](https://laravel.com)
[![License](https://img.shields.io/github/license/gracjankubicki/laravel-mcp-providers.svg)](LICENSE)
[![Tests](https://img.shields.io/github/actions/workflow/status/gracjankubicki/laravel-mcp-providers/tests.yml?label=tests)](https://github.com/gracjankubicki/laravel-mcp-providers/actions/workflows/tests.yml)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)](composer.json)

`gracjankubicki/laravel-mcp-providers` integrates external MCP servers with [laravel/ai](https://github.com/laravel/ai) using a class-first tools workflow.

## Why this package

- Connect multiple MCP servers from Laravel config.
- Discover tool metadata from MCP (`tools/list`) into local manifests.
- Generate typed Laravel AI tools from manifests.
- Select tools by `::class` (safe, explicit, refactor-friendly).
- Add per-server auth, timeout, retry, and health checks.

## Requirements

- PHP `^8.5`
- Laravel `^12.0`
- `laravel/ai` `^0.1.2`

## Installation

```bash
composer require gracjankubicki/laravel-mcp-providers
```

Publish config:

```bash
php artisan vendor:publish --tag=mcp-providers-config
```

## Quick Start

1. Configure MCP servers in `config/mcp-providers.php`.
2. Discover manifests:

```bash
php artisan ai-mcp:discover
```

3. Generate tools:

```bash
php artisan ai-mcp:generate
```

4. Use generated tool classes in your agent:

```php
public function tools(): iterable
{
    return [
        app(\App\Ai\Tools\Generated\Gdocs\GdocsSearchDocsTool::class),
        app(\App\Ai\Tools\Generated\N8n\N8nRunWorkflowTool::class),
    ];
}
```

## Configuration

Config file: `config/mcp-providers.php`

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

### Server fields

- `endpoint` (string, required): MCP server URL.
- `manifest` (string, required for discover/generate/registry/toolset flows): local JSON manifest path.
- `timeout` (int, optional): request timeout in seconds.
- `auth` (array, optional): auth strategy + params.
- `retry` (array, optional): `attempts`, `backoff_ms`, `max_backoff_ms`.

## CLI Workflow

### Discover

Fetches `tools/list` and writes normalized manifests.

```bash
php artisan ai-mcp:discover
php artisan ai-mcp:discover --server=gdocs --dry-run
php artisan ai-mcp:discover --prune --fail-fast
```

### Generate

Generates Laravel AI tool classes from manifests.

```bash
php artisan ai-mcp:generate
php artisan ai-mcp:generate --server=gdocs --clean
php artisan ai-mcp:generate --fail-on-collision
```

Generated output:

- Tool classes, for example: `App\Ai\Tools\Generated\Gdocs\GdocsSearchDocsTool`
- Per-server toolset classes, for example: `App\Ai\Tools\Generated\Gdocs\GdocsToolset`
- Aggregate toolset class: `App\Ai\Tools\Generated\McpToolset`

### Sync

Runs discover and generate in sequence.

```bash
php artisan ai-mcp:sync
```

### Health

Checks MCP connectivity.

```bash
php artisan ai-mcp:health
php artisan ai-mcp:health --server=gdocs --fail-fast
```

## Runtime APIs

### `GeneratedToolset`

```php
public function tools(): iterable
{
    return app(\Laravel\McpProviders\GeneratedToolset::class)
        ->forServers(['gdocs', 'n8n'])
        ->onlyClasses([
            \App\Ai\Tools\Generated\Gdocs\GdocsSearchDocsTool::class,
            \App\Ai\Tools\Generated\N8n\N8nRunWorkflowTool::class,
        ]);
}
```

Methods:

- `all()`
- `onlyClasses(array $toolClasses)`
- `exceptClasses(array $toolClasses)`

### Generated toolset classes

```php
public function tools(): iterable
{
    return app(\App\Ai\Tools\Generated\McpToolset::class)->all();
}
```

### `GeneratedToolRegistry` (fully dynamic)

```php
$tools = iterator_to_array(
    app(\Laravel\McpProviders\GeneratedToolRegistry::class)
        ->forServers(['gdocs', 'n8n'], [
            \App\Ai\Tools\Generated\Gdocs\GdocsSearchDocsTool::class,
            \App\Ai\Tools\Generated\N8n\N8nRunWorkflowTool::class,
        ]),
    false,
);
```

### `HasMcpTools` trait

```php
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\McpProviders\Concerns\HasMcpTools;

final class SupportAgent implements Agent, HasTools
{
    use Promptable;
    use HasMcpTools;

    public function instructions(): string
    {
        return 'Help users with docs and workflow tasks.';
    }

    protected function mcpServers(): array
    {
        return ['gdocs', 'n8n'];
    }

    protected function mcpOnlyToolClasses(): array
    {
        return [
            \App\Ai\Tools\Generated\Gdocs\GdocsSearchDocsTool::class,
            \App\Ai\Tools\Generated\N8n\N8nRunWorkflowTool::class,
        ];
    }
}
```

## Auth Strategies

Built-in strategies:

- `\Laravel\McpProviders\Auth\BearerAuthStrategy`
- `\Laravel\McpProviders\Auth\HeaderAuthStrategy`

Custom strategy: implement `\Laravel\McpProviders\Contracts\McpAuthStrategy` and set `servers.<slug>.auth.strategy`.

## Notes

- Tool selection is class-only (`::class`). Name-based allowlists are not supported.
- If you return explicit tool instances manually, runtime manifest lookup is not required.

## Development

```bash
composer format
composer lint
composer analyse
composer test
composer test:coverage
```

## Release

Release process is documented in [`RELEASING.md`](RELEASING.md).

## License

MIT
