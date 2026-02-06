<?php

declare(strict_types=1);

namespace Tests\Feature;

use Laravel\McpProviders\Auth\BearerAuthStrategy;
use Laravel\McpProviders\ConfigServerRepository;
use Laravel\McpProviders\Routing\DefaultMcpInvocationRouter;
use RuntimeException;
use Tests\Support\FakeMcpClient;
use Tests\TestCase;

final class DefaultMcpInvocationRouterTest extends TestCase
{
    public function test_it_invokes_tool_with_configured_headers_and_timeout(): void
    {
        $this->app['config']->set('mcp-providers.servers', [
            'gdocs' => [
                'endpoint' => 'http://example.test/mcp',
                'timeout' => 15,
                'auth' => ['strategy' => BearerAuthStrategy::class, 'token' => 'secret'],
                'retry' => ['attempts' => 3, 'backoff_ms' => 250, 'max_backoff_ms' => 1200],
            ],
        ]);

        $client = new FakeMcpClient;
        $client->callResultsByKey['http://example.test/mcp|search_docs'] = 'ok';

        $router = new DefaultMcpInvocationRouter($client, new ConfigServerRepository);
        $result = $router->invoke('gdocs', 'search_docs', ['query' => 'laravel']);

        $this->assertSame('ok', $result);
        $this->assertSame('Bearer secret', $client->toolCalls[0]['headers']['Authorization']);
        $this->assertSame(15, $client->toolCalls[0]['timeout']);
        $this->assertSame(3, $client->toolCalls[0]['retry_attempts']);
        $this->assertSame(250, $client->toolCalls[0]['retry_backoff_ms']);
        $this->assertSame(1200, $client->toolCalls[0]['retry_max_backoff_ms']);
    }

    public function test_it_throws_for_unknown_server(): void
    {
        $this->app['config']->set('mcp-providers.servers', []);

        $router = new DefaultMcpInvocationRouter(new FakeMcpClient, new ConfigServerRepository);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown MCP servers: missing');

        $router->invoke('missing', 'search_docs', []);
    }

    public function test_it_throws_when_endpoint_is_missing(): void
    {
        $this->app['config']->set('mcp-providers.servers', [
            'gdocs' => ['manifest' => '/tmp/x.json'],
        ]);

        $router = new DefaultMcpInvocationRouter(new FakeMcpClient, new ConfigServerRepository);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing endpoint for MCP server [gdocs].');

        $router->invoke('gdocs', 'search_docs', []);
    }
}
