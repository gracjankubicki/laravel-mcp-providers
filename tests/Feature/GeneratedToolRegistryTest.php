<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Laravel\McpProviders\ConfigServerRepository;
use Laravel\McpProviders\GeneratedToolRegistry;
use Laravel\McpProviders\Generation\ToolClassNameResolver;
use RuntimeException;
use Tests\TestCase;

final class GeneratedToolRegistryTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/mcp-providers-registry-'.Str::lower((string) Str::ulid());
        mkdir($this->workspace, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_it_resolves_generated_tools_for_selected_servers(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';
        file_put_contents($manifest, json_encode([
            'version' => 1,
            'server' => ['slug' => 'gdocs'],
            'generated_at' => '2026-02-06T12:00:00Z',
            'tools' => [
                ['name' => 'search_docs'],
            ],
        ], JSON_UNESCAPED_SLASHES));

        $this->app['config']->set('mcp-providers.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://example.test'],
        ]);
        $this->app['config']->set('mcp-providers.generated.namespace', 'Tests\\Generated');

        if (! class_exists('Tests\\Generated\\Gdocs\\GdocsSearchDocsTool')) {
            eval('namespace Tests\\Generated\\Gdocs; final class GdocsSearchDocsTool {}');
        }

        $registry = new GeneratedToolRegistry(
            $this->app,
            new ConfigServerRepository,
            new ToolClassNameResolver,
            new Filesystem,
        );

        $tools = iterator_to_array($registry->forServers(['gdocs']));

        $this->assertCount(1, $tools);
        $this->assertSame('Tests\\Generated\\Gdocs\\GdocsSearchDocsTool', $tools[0]::class);
    }

    public function test_it_throws_when_generated_class_does_not_exist(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';
        file_put_contents($manifest, json_encode([
            'version' => 1,
            'server' => ['slug' => 'gdocs'],
            'generated_at' => '2026-02-06T12:00:00Z',
            'tools' => [
                ['name' => 'search_docs'],
            ],
        ], JSON_UNESCAPED_SLASHES));

        $this->app['config']->set('mcp-providers.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://example.test'],
        ]);
        $this->app['config']->set('mcp-providers.generated.namespace', 'Tests\\NotExisting');

        $registry = new GeneratedToolRegistry(
            $this->app,
            new ConfigServerRepository,
            new ToolClassNameResolver,
            new Filesystem,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Run `php artisan ai-mcp:generate`');

        iterator_to_array($registry->forServers(['gdocs']));
    }

    public function test_it_ignores_servers_with_invalid_manifest_payload(): void
    {
        $manifest = $this->workspace.'/invalid.tools.json';
        file_put_contents($manifest, '{invalid-json');

        $this->app['config']->set('mcp-providers.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://example.test'],
        ]);

        $registry = new GeneratedToolRegistry(
            $this->app,
            new ConfigServerRepository,
            new ToolClassNameResolver,
            new Filesystem,
        );

        $tools = iterator_to_array($registry->forServers());

        $this->assertSame([], $tools);
    }

    public function test_it_ignores_servers_without_valid_manifest_path_or_tools_shape(): void
    {
        $invalidToolsManifest = $this->workspace.'/invalid-tools.tools.json';
        file_put_contents($invalidToolsManifest, json_encode(['tools' => 'invalid']));

        $this->app['config']->set('mcp-providers.servers', [
            'missing_manifest' => ['endpoint' => 'http://example.test'],
            'non_string_manifest' => ['endpoint' => 'http://example.test', 'manifest' => ['invalid']],
            'invalid_tools' => ['endpoint' => 'http://example.test', 'manifest' => $invalidToolsManifest],
        ]);

        $registry = new GeneratedToolRegistry(
            $this->app,
            new ConfigServerRepository,
            new ToolClassNameResolver,
            new Filesystem,
        );

        $tools = iterator_to_array($registry->forServers());
        $this->assertSame([], $tools);
    }

    public function test_it_filters_tools_by_class_names_only(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';
        file_put_contents($manifest, json_encode([
            'version' => 1,
            'server' => ['slug' => 'gdocs'],
            'generated_at' => '2026-02-06T12:00:00Z',
            'tools' => [
                ['name' => 'search_docs'],
                ['name' => 'list_docs'],
            ],
        ], JSON_UNESCAPED_SLASHES));

        $this->app['config']->set('mcp-providers.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://example.test'],
        ]);
        $this->app['config']->set('mcp-providers.generated.namespace', 'Tests\\GeneratedAllow');

        if (! class_exists('Tests\\GeneratedAllow\\Gdocs\\GdocsSearchDocsTool')) {
            eval('namespace Tests\\GeneratedAllow\\Gdocs; final class GdocsSearchDocsTool {}');
        }
        if (! class_exists('Tests\\GeneratedAllow\\Gdocs\\GdocsListDocsTool')) {
            eval('namespace Tests\\GeneratedAllow\\Gdocs; final class GdocsListDocsTool {}');
        }

        $registry = new GeneratedToolRegistry(
            $this->app,
            new ConfigServerRepository,
            new ToolClassNameResolver,
            new Filesystem,
        );

        $searchOnly = iterator_to_array(
            $registry->forServers(['gdocs'], ['Tests\\GeneratedAllow\\Gdocs\\GdocsSearchDocsTool'])
        );
        $listOnly = iterator_to_array(
            $registry->forServers(['gdocs'], ['Tests\\GeneratedAllow\\Gdocs\\GdocsListDocsTool'])
        );

        $this->assertCount(1, $searchOnly);
        $this->assertSame('Tests\\GeneratedAllow\\Gdocs\\GdocsSearchDocsTool', $searchOnly[0]::class);
        $this->assertCount(1, $listOnly);
        $this->assertSame('Tests\\GeneratedAllow\\Gdocs\\GdocsListDocsTool', $listOnly[0]::class);
    }

    public function test_it_does_not_support_name_based_allowlist_formats(): void
    {
        $manifest = $this->workspace.'/gdocs-allowlist-invalid.json';
        file_put_contents($manifest, json_encode([
            'version' => 1,
            'server' => ['slug' => 'gdocs'],
            'generated_at' => '2026-02-06T12:00:00Z',
            'tools' => [
                ['name' => 'search_docs'],
            ],
        ], JSON_UNESCAPED_SLASHES));

        $this->app['config']->set('mcp-providers.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://example.test'],
        ]);
        $this->app['config']->set('mcp-providers.generated.namespace', 'Tests\\GeneratedAllowInvalid');

        if (! class_exists('Tests\\GeneratedAllowInvalid\\Gdocs\\GdocsSearchDocsTool')) {
            eval('namespace Tests\\GeneratedAllowInvalid\\Gdocs; final class GdocsSearchDocsTool {}');
        }

        $registry = new GeneratedToolRegistry(
            $this->app,
            new ConfigServerRepository,
            new ToolClassNameResolver,
            new Filesystem,
        );

        $listStyleByName = iterator_to_array($registry->forServers(['gdocs'], ['gdocs.search_docs']));
        $mapStyleByName = iterator_to_array($registry->forServers(['gdocs'], ['gdocs' => ['search_docs']]));

        $this->assertSame([], $listStyleByName);
        $this->assertSame([], $mapStyleByName);
    }

    public function test_it_skips_invalid_tool_entries_inside_manifest_tools_list(): void
    {
        $manifest = $this->workspace.'/gdocs-invalid-tools.json';
        file_put_contents($manifest, json_encode([
            'version' => 1,
            'server' => ['slug' => 'gdocs'],
            'generated_at' => '2026-02-06T12:00:00Z',
            'tools' => [
                ['invalid' => true],
                'not-array',
                ['name' => 'search_docs'],
            ],
        ], JSON_UNESCAPED_SLASHES));

        $this->app['config']->set('mcp-providers.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://example.test'],
        ]);
        $this->app['config']->set('mcp-providers.generated.namespace', 'Tests\\GeneratedOnlyValid');

        if (! class_exists('Tests\\GeneratedOnlyValid\\Gdocs\\GdocsSearchDocsTool')) {
            eval('namespace Tests\\GeneratedOnlyValid\\Gdocs; final class GdocsSearchDocsTool {}');
        }

        $registry = new GeneratedToolRegistry(
            $this->app,
            new ConfigServerRepository,
            new ToolClassNameResolver,
            new Filesystem,
        );

        $tools = iterator_to_array($registry->forServers());
        $this->assertCount(1, $tools);
    }

    public function test_it_ignores_unreadable_manifest_file(): void
    {
        $manifest = $this->workspace.'/unreadable.tools.json';
        file_put_contents($manifest, '{}');
        chmod($manifest, 0000);

        $this->app['config']->set('mcp-providers.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://example.test'],
        ]);

        $registry = new GeneratedToolRegistry(
            $this->app,
            new ConfigServerRepository,
            new ToolClassNameResolver,
            new Filesystem,
        );

        try {
            $tools = iterator_to_array($registry->forServers());
            $this->assertSame([], $tools);
        } finally {
            chmod($manifest, 0644);
        }
    }

    public function test_it_throws_for_unknown_selected_server_slug(): void
    {
        $this->app['config']->set('mcp-providers.servers', []);

        $registry = new GeneratedToolRegistry(
            $this->app,
            new ConfigServerRepository,
            new ToolClassNameResolver,
            new Filesystem,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown MCP servers: missing');

        iterator_to_array($registry->forServers(['missing']));
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
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
