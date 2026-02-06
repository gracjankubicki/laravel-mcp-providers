<?php

declare(strict_types=1);

namespace Tests\Feature;

use Laravel\McpProviders\Auth\BearerAuthStrategy;
use Laravel\McpProviders\Auth\HeaderAuthStrategy;
use Laravel\McpProviders\ConfigServerRepository;
use Laravel\McpProviders\Contracts\McpAuthStrategy;
use RuntimeException;
use Tests\TestCase;

final class ConfigServerRepositoryTest extends TestCase
{
    public function test_it_returns_empty_when_servers_config_is_not_array(): void
    {
        $this->app['config']->set('ai-mcp.servers', 'invalid');

        $repository = new ConfigServerRepository;

        $this->assertSame([], $repository->all());
    }

    public function test_it_filters_invalid_server_entries_and_selects_requested_servers(): void
    {
        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => ['endpoint' => 'http://example.test', 'manifest' => '/tmp/gdocs.json'],
            'invalid' => 'value',
            'n8n' => ['endpoint' => 'http://example.test', 'manifest' => '/tmp/n8n.json'],
        ]);

        $repository = new ConfigServerRepository;

        $all = $repository->all();
        $selected = $repository->selected(['n8n']);

        $this->assertArrayHasKey('gdocs', $all);
        $this->assertArrayNotHasKey('invalid', $all);
        $this->assertSame(['n8n' => $all['n8n']], $selected);
    }

    public function test_it_throws_for_unknown_server_slug(): void
    {
        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => ['endpoint' => 'http://example.test', 'manifest' => '/tmp/gdocs.json'],
        ]);

        $repository = new ConfigServerRepository;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown MCP servers: missing');

        $repository->selected(['missing']);
    }

    public function test_it_builds_auth_headers_from_server_config(): void
    {
        $repository = new ConfigServerRepository;

        $bearer = $repository->headers([
            'auth' => ['strategy' => BearerAuthStrategy::class, 'token' => 'secret'],
        ]);
        $header = $repository->headers([
            'auth' => ['strategy' => HeaderAuthStrategy::class, 'header' => 'X-Api-Key', 'value' => 'value'],
        ]);
        $customStrategy = $repository->headers([
            'auth' => [
                'strategy' => FakeAuthStrategy::class,
                'value' => 'custom',
            ],
        ]);
        $none = $repository->headers(['auth' => 'invalid']);

        $this->assertSame(['Authorization' => 'Bearer secret'], $bearer);
        $this->assertSame(['X-Api-Key' => 'value'], $header);
        $this->assertSame(['X-Fake-Auth' => 'custom'], $customStrategy);
        $this->assertSame([], $none);
    }

    public function test_it_resolves_retry_configuration_with_defaults_and_overrides(): void
    {
        $this->app['config']->set('ai-mcp.retry', [
            'attempts' => 2,
            'backoff_ms' => 150,
            'max_backoff_ms' => 900,
        ]);

        $repository = new ConfigServerRepository;

        $defaultRetry = $repository->retry([]);
        $overrideRetry = $repository->retry([
            'retry' => [
                'attempts' => 4,
                'backoff_ms' => 200,
                'max_backoff_ms' => 2000,
            ],
        ]);
        $invalidRetry = $repository->retry([
            'retry' => [
                'attempts' => 0,
                'backoff_ms' => -1,
                'max_backoff_ms' => -1,
            ],
        ]);

        $this->assertSame(['attempts' => 2, 'backoff_ms' => 150, 'max_backoff_ms' => 900], $defaultRetry);
        $this->assertSame(['attempts' => 4, 'backoff_ms' => 200, 'max_backoff_ms' => 2000], $overrideRetry);
        $this->assertSame(['attempts' => 2, 'backoff_ms' => 150, 'max_backoff_ms' => 900], $invalidRetry);
    }

    public function test_it_throws_for_missing_auth_strategy(): void
    {
        $repository = new ConfigServerRepository;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing MCP auth strategy');
        $repository->headers(['auth' => []]);
    }

    public function test_it_throws_for_invalid_strategy_class(): void
    {
        $repository = new ConfigServerRepository;

        try {
            $repository->headers(['auth' => ['strategy' => 'Tests\\Feature\\MissingStrategyClass']]);
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('does not exist', $e->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must implement McpAuthStrategy');
        $repository->headers(['auth' => ['strategy' => InvalidAuthStrategy::class]]);
    }

    public function test_it_supports_custom_strategy_class(): void
    {
        $repository = new ConfigServerRepository;
        $headers = $repository->headers([
            'auth' => ['strategy' => FakeAuthStrategy::class, 'value' => 'mapped'],
        ]);

        $this->assertSame(['X-Fake-Auth' => 'mapped'], $headers);
    }

    public function test_it_returns_empty_headers_for_invalid_strategy_payload(): void
    {
        $repository = new ConfigServerRepository;

        $emptyBearer = $repository->headers([
            'auth' => ['strategy' => BearerAuthStrategy::class, 'token' => ''],
        ]);
        $invalidHeader = $repository->headers([
            'auth' => ['strategy' => HeaderAuthStrategy::class, 'header' => '', 'value' => 'x'],
        ]);

        $this->assertSame([], $emptyBearer);
        $this->assertSame([], $invalidHeader);
    }
}

final class FakeAuthStrategy implements McpAuthStrategy
{
    /**
     * @param  array<string, mixed>  $authConfig
     * @return array<string, string>
     */
    public function headers(array $authConfig, ?string $serverSlug = null, ?string $toolName = null): array
    {
        $value = $authConfig['value'] ?? 'ok';

        return ['X-Fake-Auth' => (string) $value];
    }
}

final class InvalidAuthStrategy {}
