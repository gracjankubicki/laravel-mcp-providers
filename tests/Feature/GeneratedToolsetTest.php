<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Laravel\McpProviders\GeneratedToolset;
use Tests\TestCase;

final class GeneratedToolsetTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/mcp-providers-toolset-'.Str::lower((string) Str::ulid());
        mkdir($this->workspace, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_it_returns_all_tools_for_selected_servers(): void
    {
        $this->configureManifests();
        $this->defineGeneratedClasses();

        $tools = $this->app->make(GeneratedToolset::class)
            ->forServers(['gdocs', 'n8n'])
            ->all();

        $this->assertSame([
            'Tests\\GeneratedToolset\\Gdocs\\GdocsListDocsTool',
            'Tests\\GeneratedToolset\\Gdocs\\GdocsSearchDocsTool',
            'Tests\\GeneratedToolset\\N8n\\N8nRunWorkflowTool',
        ], array_map(static fn (object $tool): string => $tool::class, $tools));
    }

    public function test_it_filters_tools_by_explicit_class_names(): void
    {
        $this->configureManifests();
        $this->defineGeneratedClasses();

        $tools = $this->app->make(GeneratedToolset::class)
            ->forServers(['gdocs', 'n8n'])
            ->onlyClasses([
                'Tests\\GeneratedToolset\\Gdocs\\GdocsSearchDocsTool',
                'Tests\\GeneratedToolset\\N8n\\N8nRunWorkflowTool',
            ]);

        $this->assertSame([
            'Tests\\GeneratedToolset\\Gdocs\\GdocsSearchDocsTool',
            'Tests\\GeneratedToolset\\N8n\\N8nRunWorkflowTool',
        ], array_map(static fn (object $tool): string => $tool::class, $tools));
    }

    public function test_it_excludes_tools_by_explicit_class_names(): void
    {
        $this->configureManifests();
        $this->defineGeneratedClasses();

        $tools = $this->app->make(GeneratedToolset::class)
            ->forServers(['gdocs', 'n8n'])
            ->exceptClasses([
                'Tests\\GeneratedToolset\\Gdocs\\GdocsListDocsTool',
            ]);

        $this->assertSame([
            'Tests\\GeneratedToolset\\Gdocs\\GdocsSearchDocsTool',
            'Tests\\GeneratedToolset\\N8n\\N8nRunWorkflowTool',
        ], array_map(static fn (object $tool): string => $tool::class, $tools));
    }

    private function configureManifests(): void
    {
        $gdocsManifest = $this->workspace.'/gdocs.tools.json';
        file_put_contents($gdocsManifest, json_encode([
            'version' => 1,
            'server' => ['slug' => 'gdocs'],
            'generated_at' => '2026-02-06T12:00:00Z',
            'tools' => [
                ['name' => 'search_docs'],
                ['name' => 'list_docs'],
            ],
        ], JSON_UNESCAPED_SLASHES));

        $n8nManifest = $this->workspace.'/n8n.tools.json';
        file_put_contents($n8nManifest, json_encode([
            'version' => 1,
            'server' => ['slug' => 'n8n'],
            'generated_at' => '2026-02-06T12:00:00Z',
            'tools' => [
                ['name' => 'run_workflow'],
            ],
        ], JSON_UNESCAPED_SLASHES));

        $this->app['config']->set('mcp-providers.servers', [
            'gdocs' => ['manifest' => $gdocsManifest, 'endpoint' => 'http://example.test'],
            'n8n' => ['manifest' => $n8nManifest, 'endpoint' => 'http://example.test'],
        ]);
        $this->app['config']->set('mcp-providers.generated.namespace', 'Tests\\GeneratedToolset');
    }

    private function defineGeneratedClasses(): void
    {
        if (! class_exists('Tests\\GeneratedToolset\\Gdocs\\GdocsListDocsTool')) {
            eval('namespace Tests\\GeneratedToolset\\Gdocs; final class GdocsListDocsTool {}');
        }
        if (! class_exists('Tests\\GeneratedToolset\\Gdocs\\GdocsSearchDocsTool')) {
            eval('namespace Tests\\GeneratedToolset\\Gdocs; final class GdocsSearchDocsTool {}');
        }
        if (! class_exists('Tests\\GeneratedToolset\\N8n\\N8nRunWorkflowTool')) {
            eval('namespace Tests\\GeneratedToolset\\N8n; final class N8nRunWorkflowTool {}');
        }
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
