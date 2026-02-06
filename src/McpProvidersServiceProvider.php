<?php

declare(strict_types=1);

namespace Laravel\McpProviders;

use Illuminate\Support\ServiceProvider;
use Laravel\McpProviders\Auth\McpAuthResolver;
use Laravel\McpProviders\Clients\JsonRpcMcpClient;
use Laravel\McpProviders\Commands\DiscoverToolsCommand;
use Laravel\McpProviders\Commands\GenerateToolsCommand;
use Laravel\McpProviders\Commands\HealthCheckCommand;
use Laravel\McpProviders\Commands\SyncToolsCommand;
use Laravel\McpProviders\Contracts\McpClient;
use Laravel\McpProviders\Contracts\McpInvocationRouter;
use Laravel\McpProviders\Generation\ToolClassNameResolver;
use Laravel\McpProviders\Routing\DefaultMcpInvocationRouter;

final class McpProvidersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mcp-providers.php', 'mcp-providers');

        $this->app->singleton(ConfigServerRepository::class);
        $this->app->singleton(McpAuthResolver::class);
        $this->app->singleton(ToolManifestNormalizer::class);
        $this->app->singleton(McpClient::class, JsonRpcMcpClient::class);
        $this->app->singleton(McpInvocationRouter::class, DefaultMcpInvocationRouter::class);
        $this->app->singleton(ToolClassNameResolver::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/mcp-providers.php' => config_path('mcp-providers.php'),
        ], 'mcp-providers-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DiscoverToolsCommand::class,
                GenerateToolsCommand::class,
                HealthCheckCommand::class,
                SyncToolsCommand::class,
            ]);
        }
    }
}
