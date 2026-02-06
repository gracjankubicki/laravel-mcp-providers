<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

final class GenerateToolsCommandTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/mcp-providers-tests-'.Str::lower((string) Str::ulid());

        mkdir($this->workspace, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_it_generates_tools_for_multiple_servers(): void
    {
        $gdocsManifest = $this->workspace.'/gdocs.tools.json';
        $n8nManifest = $this->workspace.'/n8n.tools.json';
        $generatedPath = $this->workspace.'/generated';

        $this->writeManifest($gdocsManifest, [[
            'name' => 'search_docs',
            'description' => 'Search docs',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'minLength' => 2],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 25],
                ],
                'required' => ['query'],
            ],
        ]]);

        $this->writeManifest($n8nManifest, [[
            'name' => 'search_docs',
            'description' => 'Search workflows',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string'],
                ],
            ],
        ]]);

        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => ['manifest' => $gdocsManifest, 'endpoint' => 'http://127.0.0.1:9999'],
            'n8n' => ['manifest' => $n8nManifest, 'endpoint' => 'http://127.0.0.1:9999'],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $generatedPath);
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        $this->artisan('ai-mcp:generate')
            ->assertExitCode(0);

        $gdocsFile = $generatedPath.'/Gdocs/GdocsSearchDocsTool.php';
        $n8nFile = $generatedPath.'/N8n/N8nSearchDocsTool.php';

        $this->assertFileExists($gdocsFile);
        $this->assertFileExists($n8nFile);

        $gdocsSource = file_get_contents($gdocsFile);
        $this->assertIsString($gdocsSource);
        $this->assertStringContainsString('namespace App\\Ai\\Tools\\Generated\\Gdocs;', $gdocsSource);
        $this->assertStringContainsString("return 'gdocs';", $gdocsSource);
        $this->assertStringContainsString("return 'search_docs';", $gdocsSource);
        $this->assertStringContainsString("'query' => \$schema->string()->min(2)->required()", $gdocsSource);
    }

    public function test_dry_run_does_not_write_generated_files(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';
        $generatedPath = $this->workspace.'/generated';

        $this->writeManifest($manifest, [[
            'name' => 'search_docs',
            'description' => 'Search docs',
            'input_schema' => ['type' => 'object', 'properties' => []],
        ]]);

        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://127.0.0.1:9999'],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $generatedPath);
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        $this->artisan('ai-mcp:generate --dry-run')
            ->assertExitCode(0);

        $this->assertDirectoryDoesNotExist($generatedPath);
    }

    public function test_clean_option_removes_stale_files_before_regeneration(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';
        $generatedPath = $this->workspace.'/generated';

        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://127.0.0.1:9999'],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $generatedPath);
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        $this->writeManifest($manifest, [[
            'name' => 'search_docs',
            'description' => 'Search docs',
            'input_schema' => ['type' => 'object', 'properties' => []],
        ]]);

        $this->artisan('ai-mcp:generate')->assertExitCode(0);
        $oldFile = $generatedPath.'/Gdocs/GdocsSearchDocsTool.php';
        $this->assertFileExists($oldFile);

        $this->writeManifest($manifest, [[
            'name' => 'list_docs',
            'description' => 'List docs',
            'input_schema' => ['type' => 'object', 'properties' => []],
        ]]);

        $this->artisan('ai-mcp:generate --clean')->assertExitCode(0);

        $newFile = $generatedPath.'/Gdocs/GdocsListDocsTool.php';
        $this->assertFileExists($newFile);
        $this->assertFileDoesNotExist($oldFile);
    }

    public function test_it_uses_hashed_suffix_when_class_names_collide(): void
    {
        $firstManifest = $this->workspace.'/crm-api.tools.json';
        $secondManifest = $this->workspace.'/crm_api.tools.json';
        $generatedPath = $this->workspace.'/generated';

        $toolDefinition = [[
            'name' => 'create_ticket',
            'description' => 'Create ticket',
            'input_schema' => ['type' => 'object', 'properties' => []],
        ]];

        $this->writeManifest($firstManifest, $toolDefinition);
        $this->writeManifest($secondManifest, $toolDefinition);

        $this->app['config']->set('ai-mcp.servers', [
            'crm-api' => ['manifest' => $firstManifest, 'endpoint' => 'http://127.0.0.1:9999'],
            'crm_api' => ['manifest' => $secondManifest, 'endpoint' => 'http://127.0.0.1:9999'],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $generatedPath);
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        $this->artisan('ai-mcp:generate')
            ->assertExitCode(0);

        $files = glob($generatedPath.'/CrmApi/*.php');
        sort($files);

        $this->assertIsArray($files);
        $this->assertCount(2, $files);
        $this->assertSame($generatedPath.'/CrmApi/CrmApiCreateTicketTool.php', $files[0]);
        $this->assertMatchesRegularExpression(
            '#/CrmApi/CrmApiCreateTicketTool[a-f0-9]{8}\.php$#',
            $files[1]
        );
    }

    public function test_it_can_fail_on_collision_when_option_enabled(): void
    {
        $firstManifest = $this->workspace.'/crm-api.tools.json';
        $secondManifest = $this->workspace.'/crm_api.tools.json';
        $generatedPath = $this->workspace.'/generated';

        $toolDefinition = [[
            'name' => 'create_ticket',
            'description' => 'Create ticket',
            'input_schema' => ['type' => 'object', 'properties' => []],
        ]];

        $this->writeManifest($firstManifest, $toolDefinition);
        $this->writeManifest($secondManifest, $toolDefinition);

        $this->app['config']->set('ai-mcp.servers', [
            'crm-api' => ['manifest' => $firstManifest, 'endpoint' => 'http://127.0.0.1:9999'],
            'crm_api' => ['manifest' => $secondManifest, 'endpoint' => 'http://127.0.0.1:9999'],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $generatedPath);
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        $this->artisan('ai-mcp:generate --fail-on-collision')
            ->assertExitCode(1);
    }

    public function test_it_returns_success_when_no_servers_selected(): void
    {
        $this->app['config']->set('ai-mcp.servers', []);

        $this->artisan('ai-mcp:generate')->assertExitCode(0);
    }

    public function test_it_skips_servers_with_missing_or_invalid_manifest_data(): void
    {
        $invalidToolsManifest = $this->workspace.'/invalid-tools.json';
        file_put_contents($invalidToolsManifest, json_encode(['tools' => 'invalid']));

        $this->app['config']->set('ai-mcp.servers', [
            'missing_manifest_path' => ['endpoint' => 'http://127.0.0.1:9999'],
            'manifest_not_found' => ['manifest' => $this->workspace.'/404.json', 'endpoint' => 'http://127.0.0.1:9999'],
            'invalid_tools' => ['manifest' => $invalidToolsManifest, 'endpoint' => 'http://127.0.0.1:9999'],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $this->workspace.'/generated');
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        $this->artisan('ai-mcp:generate')->assertExitCode(0);
        $this->assertDirectoryDoesNotExist($this->workspace.'/generated');
    }

    public function test_clean_option_skips_missing_directories(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';
        $generatedPath = $this->workspace.'/generated';

        $this->writeManifest($manifest, []);
        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://127.0.0.1:9999'],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $generatedPath);
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        $this->artisan('ai-mcp:generate --clean')->assertExitCode(0);
        $this->assertDirectoryDoesNotExist($generatedPath);
    }

    public function test_clean_option_removes_nested_directories(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';
        $generatedPath = $this->workspace.'/generated';
        $nestedDirectory = $generatedPath.'/Gdocs/Nested';
        mkdir($nestedDirectory, 0755, true);
        file_put_contents($nestedDirectory.'/old.php', '<?php');

        $this->writeManifest($manifest, [[
            'name' => 'search_docs',
            'description' => 'Search docs',
            'input_schema' => ['type' => 'object', 'properties' => []],
        ]]);
        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://127.0.0.1:9999'],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $generatedPath);
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        $this->artisan('ai-mcp:generate --clean')->assertExitCode(0);

        $this->assertFileDoesNotExist($nestedDirectory.'/old.php');
        $this->assertFileExists($generatedPath.'/Gdocs/GdocsSearchDocsTool.php');
    }

    public function test_it_skips_invalid_tool_entries_and_sorts_tools_within_same_server(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';
        $generatedPath = $this->workspace.'/generated';

        $this->writeManifest($manifest, [
            ['name' => 'z_tool', 'description' => 'z', 'input_schema' => ['type' => 'object', 'properties' => []]],
            ['name' => 'a_tool', 'description' => 'a', 'input_schema' => ['type' => 'object', 'properties' => []]],
            ['invalid' => true],
        ]);
        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://127.0.0.1:9999'],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $generatedPath);
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        $this->artisan('ai-mcp:generate')->assertExitCode(0);

        $files = glob($generatedPath.'/Gdocs/*.php');
        sort($files);

        $this->assertSame([
            $generatedPath.'/Gdocs/GdocsAToolTool.php',
            $generatedPath.'/Gdocs/GdocsZToolTool.php',
        ], $files);
    }

    public function test_it_fails_when_generated_directory_cannot_be_created(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';
        $basePath = $this->workspace.'/blocked';
        file_put_contents($basePath, 'x');

        $this->writeManifest($manifest, [[
            'name' => 'search_docs',
            'description' => 'Search docs',
            'input_schema' => ['type' => 'object', 'properties' => []],
        ]]);
        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://127.0.0.1:9999'],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $basePath);
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        $this->artisan('ai-mcp:generate')->assertExitCode(1);
    }

    public function test_it_fails_for_unreadable_manifest_file(): void
    {
        $manifest = $this->workspace.'/unreadable.tools.json';
        file_put_contents($manifest, '{}');
        chmod($manifest, 0000);

        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://127.0.0.1:9999'],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $this->workspace.'/generated');
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        try {
            $this->artisan('ai-mcp:generate')->assertExitCode(1);
        } finally {
            chmod($manifest, 0644);
        }
    }

    public function test_it_fails_for_manifest_with_non_array_json(): void
    {
        $manifest = $this->workspace.'/scalar.tools.json';
        file_put_contents($manifest, '1');

        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://127.0.0.1:9999'],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $this->workspace.'/generated');
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        $this->artisan('ai-mcp:generate')->assertExitCode(1);
    }

    public function test_clean_handles_unreadable_directory_listing(): void
    {
        $manifest = $this->workspace.'/gdocs.tools.json';
        $generatedPath = $this->workspace.'/generated';
        $directory = $generatedPath.'/Gdocs';
        mkdir($directory, 0755, true);
        chmod($directory, 0000);

        $this->writeManifest($manifest, []);
        $this->app['config']->set('ai-mcp.servers', [
            'gdocs' => ['manifest' => $manifest, 'endpoint' => 'http://127.0.0.1:9999'],
        ]);
        $this->app['config']->set('ai-mcp.generated.path', $generatedPath);
        $this->app['config']->set('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated');

        try {
            $this->artisan('ai-mcp:generate --clean')->assertExitCode(0);
        } finally {
            chmod($directory, 0755);
            $this->deleteDirectory($generatedPath);
        }
    }

    /**
     * @param  list<array{name: string, description?: string, input_schema?: array<string, mixed>}>  $tools
     */
    private function writeManifest(string $path, array $tools): void
    {
        $payload = [
            'version' => 1,
            'server' => ['slug' => 'test'],
            'generated_at' => '2026-02-06T12:00:00Z',
            'tools' => $tools,
        ];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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
