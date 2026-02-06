<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Laravel\McpProviders\Auth\HeaderAuthStrategy;
use Laravel\McpProviders\Contracts\McpClient;
use RuntimeException;
use Tests\Support\FakeMcpClient;
use Tests\TestCase;

final class DiscoverToolsCommandTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/mcp-providers-discover-'.Str::lower((string) Str::ulid());
        mkdir($this->workspace, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_it_discovers_tools_and_writes_normalized_manifest(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';

        $client = new FakeMcpClient;
        $client->toolsByEndpoint['http://example.test/mcp'] = [
            [
                'name' => 'z_tool',
                'description' => 'Z',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'b' => ['type' => 'string'],
                        'a' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name' => 'a_tool',
                'description' => 'A',
                'input_schema' => ['type' => 'object', 'properties' => []],
            ],
        ];

        $this->app->instance(McpClient::class, $client);
        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => [
                'endpoint' => 'http://example.test/mcp',
                'endpoint_env' => 'MCP_GDOCS_URL',
                'timeout' => 10,
                'retry' => ['attempts' => 3, 'backoff_ms' => 200, 'max_backoff_ms' => 1200],
                'auth' => [
                    'strategy' => HeaderAuthStrategy::class,
                    'header' => 'X-API-Key',
                    'value' => 'secret',
                ],
                'manifest' => $manifest,
            ],
        ]);

        $this->artisan('ai-mcp:discover')->assertExitCode(0);

        $this->assertFileExists($manifest);
        $decoded = json_decode((string) file_get_contents($manifest), true);

        $this->assertIsArray($decoded);
        $this->assertSame('gdocs', $decoded['server']['slug']);
        $this->assertSame('MCP_GDOCS_URL', $decoded['server']['endpoint_env']);
        $this->assertSame('a_tool', $decoded['tools'][0]['name']);
        $this->assertSame(['properties', 'type'], array_keys($decoded['tools'][1]['input_schema']));
        $this->assertSame('secret', $client->listCalls[0]['headers']['X-API-Key']);
        $this->assertSame(3, $client->listCalls[0]['retry_attempts']);
        $this->assertSame(200, $client->listCalls[0]['retry_backoff_ms']);
        $this->assertSame(1200, $client->listCalls[0]['retry_max_backoff_ms']);
    }

    public function test_dry_run_does_not_write_manifest_file(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';

        $client = new FakeMcpClient;
        $client->toolsByEndpoint['http://example.test/mcp'] = [];
        $this->app->instance(McpClient::class, $client);

        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => [
                'endpoint' => 'http://example.test/mcp',
                'manifest' => $manifest,
            ],
        ]);

        $this->artisan('ai-mcp:discover --dry-run')->assertExitCode(0);

        $this->assertFileDoesNotExist($manifest);
    }

    public function test_prune_removes_unselected_server_manifest_files(): void
    {
        $keepManifest = $this->workspace.'/keep.tools.json';
        $pruneManifest = $this->workspace.'/prune.tools.json';
        file_put_contents($pruneManifest, '{}');

        $client = new FakeMcpClient;
        $client->toolsByEndpoint['http://example.test/mcp'] = [];
        $this->app->instance(McpClient::class, $client);

        $this->app['config']->set('ai-mcp.servers', [
            'keep' => [
                'endpoint' => 'http://example.test/mcp',
                'manifest' => $keepManifest,
            ],
            'prune' => [
                'endpoint' => 'http://example.test/mcp',
                'manifest' => $pruneManifest,
            ],
        ]);

        $this->artisan('ai-mcp:discover --server=keep --prune')->assertExitCode(0);

        $this->assertFileExists($keepManifest);
        $this->assertFileDoesNotExist($pruneManifest);
    }

    public function test_prune_dry_run_keeps_unselected_manifest_files(): void
    {
        $keepManifest = $this->workspace.'/keep.tools.json';
        $pruneManifest = $this->workspace.'/prune.tools.json';
        file_put_contents($pruneManifest, '{}');

        $client = new FakeMcpClient;
        $client->toolsByEndpoint['http://example.test/mcp'] = [];
        $this->app->instance(McpClient::class, $client);

        $this->app['config']->set('ai-mcp.servers', [
            'keep' => [
                'endpoint' => 'http://example.test/mcp',
                'manifest' => $keepManifest,
            ],
            'prune' => [
                'endpoint' => 'http://example.test/mcp',
                'manifest' => $pruneManifest,
            ],
        ]);

        $this->artisan('ai-mcp:discover --server=keep --prune --dry-run')->assertExitCode(0);

        $this->assertFileDoesNotExist($keepManifest);
        $this->assertFileExists($pruneManifest);
    }

    public function test_it_handles_errors_and_fail_fast_behavior(): void
    {
        $firstManifest = $this->workspace.'/first.tools.json';
        $secondManifest = $this->workspace.'/second.tools.json';

        $client = new FakeMcpClient;
        $client->errorsByEndpoint['http://example.test/first'] = new RuntimeException('first boom');
        $client->toolsByEndpoint['http://example.test/second'] = [];
        $this->app->instance(McpClient::class, $client);

        $this->app['config']->set('ai-mcp.servers', [
            'first' => ['endpoint' => 'http://example.test/first', 'manifest' => $firstManifest],
            'second' => ['endpoint' => 'http://example.test/second', 'manifest' => $secondManifest],
        ]);

        $this->artisan('ai-mcp:discover --fail-fast')->assertExitCode(1);
        $this->assertCount(1, $client->listCalls);

        $client->listCalls = [];
        $this->artisan('ai-mcp:discover')->assertExitCode(1);
        $this->assertCount(2, $client->listCalls);
    }

    public function test_it_fails_when_manifest_or_endpoint_is_missing(): void
    {
        $client = new FakeMcpClient;
        $this->app->instance(McpClient::class, $client);

        $this->app['config']->set('ai-mcp.servers', [
            'missing_manifest' => ['endpoint' => 'http://example.test/a'],
            'missing_endpoint' => ['manifest' => $this->workspace.'/x.tools.json'],
        ]);

        $this->artisan('ai-mcp:discover --fail-fast')->assertExitCode(1);
    }

    public function test_it_fails_when_manifest_path_is_missing(): void
    {
        $client = new FakeMcpClient;
        $this->app->instance(McpClient::class, $client);

        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => ['endpoint' => 'http://example.test/mcp'],
        ]);

        $this->artisan('ai-mcp:discover --fail-fast')->assertExitCode(1);
    }

    public function test_it_fails_when_manifest_parent_path_cannot_be_created(): void
    {
        $blockedParent = $this->workspace.'/blocked';
        file_put_contents($blockedParent, 'x');

        $client = new FakeMcpClient;
        $client->toolsByEndpoint['http://example.test/mcp'] = [];
        $this->app->instance(McpClient::class, $client);

        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => [
                'endpoint' => 'http://example.test/mcp',
                'manifest' => $blockedParent.'/child/gdocs.tools.json',
            ],
        ]);

        $this->artisan('ai-mcp:discover --fail-fast')->assertExitCode(1);
    }

    public function test_it_returns_success_when_no_servers_selected(): void
    {
        $this->app['config']->set('ai-mcp.servers', []);
        $this->artisan('ai-mcp:discover')->assertExitCode(0);
    }

    public function test_it_fails_for_unknown_selected_server_slug(): void
    {
        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => ['endpoint' => 'http://example.test/mcp', 'manifest' => $this->workspace.'/gdocs.tools.json'],
        ]);

        $this->artisan('ai-mcp:discover --server=missing')->assertExitCode(1);
    }

    public function test_prune_ignores_non_string_or_missing_manifest_paths(): void
    {
        $keepManifest = $this->workspace.'/keep.tools.json';

        $client = new FakeMcpClient;
        $client->toolsByEndpoint['http://example.test/mcp'] = [];
        $this->app->instance(McpClient::class, $client);

        $this->app['config']->set('ai-mcp.servers', [
            'keep' => [
                'endpoint' => 'http://example.test/mcp',
                'manifest' => $keepManifest,
            ],
            'skip_non_string' => [
                'endpoint' => 'http://example.test/mcp',
                'manifest' => ['invalid'],
            ],
            'skip_missing_file' => [
                'endpoint' => 'http://example.test/mcp',
                'manifest' => $this->workspace.'/missing.tools.json',
            ],
        ]);

        $this->artisan('ai-mcp:discover --server=keep --prune')->assertExitCode(0);

        $this->assertFileExists($keepManifest);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.'/'.$item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
