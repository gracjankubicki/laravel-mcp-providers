<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Laravel\McpProviders\Contracts\McpClient;
use RuntimeException;
use Tests\Support\FakeMcpClient;
use Tests\TestCase;

final class HealthCheckCommandTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/mcp-providers-health-'.Str::lower((string) Str::ulid());
        mkdir($this->workspace, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->workspace)) {
            rmdir($this->workspace);
        }

        parent::tearDown();
    }

    public function test_it_reports_healthy_servers(): void
    {
        $client = new FakeMcpClient;
        $client->toolsByEndpoint['http://example.test/mcp'] = [['name' => 'search_docs']];
        $this->app->instance(McpClient::class, $client);

        $this->app['config']->set('mcp-providers.servers', [
            'gdocs' => [
                'endpoint' => 'http://example.test/mcp',
                'retry' => ['attempts' => 3, 'backoff_ms' => 120, 'max_backoff_ms' => 800],
            ],
        ]);

        $this->artisan('ai-mcp:health')->assertExitCode(0);
        $this->assertSame(3, $client->listCalls[0]['retry_attempts']);
        $this->assertSame(120, $client->listCalls[0]['retry_backoff_ms']);
        $this->assertSame(800, $client->listCalls[0]['retry_max_backoff_ms']);
    }

    public function test_it_returns_failure_when_server_is_unhealthy(): void
    {
        $client = new FakeMcpClient;
        $client->errorsByEndpoint['http://example.test/mcp'] = new RuntimeException('boom');
        $this->app->instance(McpClient::class, $client);

        $this->app['config']->set('mcp-providers.servers', [
            'gdocs' => ['endpoint' => 'http://example.test/mcp'],
        ]);

        $this->artisan('ai-mcp:health')->assertExitCode(1);
    }

    public function test_fail_fast_stops_at_first_error(): void
    {
        $client = new FakeMcpClient;
        $client->errorsByEndpoint['http://example.test/first'] = new RuntimeException('first boom');
        $client->toolsByEndpoint['http://example.test/second'] = [];
        $this->app->instance(McpClient::class, $client);

        $this->app['config']->set('mcp-providers.servers', [
            'first' => ['endpoint' => 'http://example.test/first'],
            'second' => ['endpoint' => 'http://example.test/second'],
        ]);

        $this->artisan('ai-mcp:health --fail-fast')->assertExitCode(1);
        $this->assertCount(1, $client->listCalls);
    }

    public function test_it_fails_for_missing_endpoint_or_unknown_server(): void
    {
        $this->app['config']->set('mcp-providers.servers', [
            'gdocs' => ['manifest' => $this->workspace.'/gdocs.tools.json'],
        ]);

        $this->artisan('ai-mcp:health --server=gdocs')->assertExitCode(1);
        $this->artisan('ai-mcp:health --server=missing')->assertExitCode(1);
    }

    public function test_it_returns_success_when_no_servers_selected(): void
    {
        $this->app['config']->set('mcp-providers.servers', []);

        $this->artisan('ai-mcp:health')->assertExitCode(0);
    }
}
