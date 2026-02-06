<?php

declare(strict_types=1);

namespace Laravel\McpProviders;

use Illuminate\Support\ServiceProvider;
use Laravel\McpProviders\Auth\McpAuthResolver;
use Laravel\McpProviders\Clients\JsonRpcMcpClient;
use Laravel\McpProviders\Console\DiscoverToolsCommand;
use Laravel\McpProviders\Console\GenerateToolsCommand;
use Laravel\McpProviders\Console\HealthCheckCommand;
use Laravel\McpProviders\Console\SyncToolsCommand;
use Laravel\McpProviders\Contracts\McpClient;
use Laravel\McpProviders\Contracts\McpInvocationRouter;
use Laravel\McpProviders\Generation\ToolClassNameResolver;
use Laravel\McpProviders\Routing\DefaultMcpInvocationRouter;

final class McpProvidersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-mcp.php', 'ai-mcp');

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
            __DIR__.'/../config/ai-mcp.php' => config_path('ai-mcp.php'),
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
