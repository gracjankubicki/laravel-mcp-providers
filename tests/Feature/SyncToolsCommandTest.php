<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Laravel\McpProviders\Contracts\McpClient;
use RuntimeException;
use Tests\Support\FakeMcpClient;
use Tests\TestCase;

final class SyncToolsCommandTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/mcp-providers-sync-'.Str::lower((string) Str::ulid());
        mkdir($this->workspace, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_it_runs_discover_then_generate(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';
        $generatedPath = $this->workspace.'/generated';

        $client = new FakeMcpClient;
        $client->toolsByEndpoint['http://example.test/mcp'] = [
            [
                'name' => 'search_docs',
                'description' => 'Search docs',
                'input_schema' => ['type' => 'object', 'properties' => []],
            ],
        ];
        $this->app->instance(McpClient::class, $client);

        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => [
                'endpoint' => 'http://example.test/mcp',
                'manifest' => $manifest,
            ],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $generatedPath);
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        $this->artisan('ai-mcp:sync')->assertExitCode(0);

        $this->assertFileExists($manifest);
        $this->assertFileExists($generatedPath.'/Gdocs/GdocsSearchDocsTool.php');
    }

    public function test_it_does_not_run_generate_when_discover_fails(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';
        $generatedPath = $this->workspace.'/generated';

        $client = new FakeMcpClient;
        $client->errorsByEndpoint['http://example.test/mcp'] = new RuntimeException('discover failed');
        $this->app->instance(McpClient::class, $client);

        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => [
                'endpoint' => 'http://example.test/mcp',
                'manifest' => $manifest,
            ],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $generatedPath);
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        $this->artisan('ai-mcp:sync --fail-fast')->assertExitCode(1);

        $this->assertDirectoryDoesNotExist($generatedPath);
    }

    public function test_dry_run_sync_does_not_write_files(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';
        $generatedPath = $this->workspace.'/generated';

        $client = new FakeMcpClient;
        $client->toolsByEndpoint['http://example.test/mcp'] = [];
        $this->app->instance(McpClient::class, $client);

        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => [
                'endpoint' => 'http://example.test/mcp',
                'manifest' => $manifest,
            ],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $generatedPath);
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        $this->artisan('ai-mcp:sync --dry-run')->assertExitCode(0);

        $this->assertFileDoesNotExist($manifest);
        $this->assertDirectoryDoesNotExist($generatedPath);
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
