<?php

declare(strict_types=1);

namespace Tests\Feature;

use Laravel\McpProviders\Concerns\HasMcpTools;
use Tests\TestCase;

final class HasMcpToolsTraitTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().'/mcp-providers-has-tools-'.uniqid('', true);
        mkdir($this->workspace, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_trait_returns_all_tools_for_selected_servers(): void
    {
        $this->configureManifests();
        $this->defineGeneratedClasses();

        $agent = new TraitAllToolsAgent;
        $tools = iterator_to_array($agent->tools(), false);

        $this->assertSame([
            'Tests\\GeneratedTrait\\Gdocs\\GdocsListDocsTool',
            'Tests\\GeneratedTrait\\Gdocs\\GdocsSearchDocsTool',
            'Tests\\GeneratedTrait\\N8n\\N8nRunWorkflowTool',
        ], array_map(static fn (object $tool): string => $tool::class, $tools));
    }

    public function test_trait_can_include_only_selected_classes(): void
    {
        $this->configureManifests();
        $this->defineGeneratedClasses();

        $agent = new TraitOnlyToolsAgent;
        $tools = iterator_to_array($agent->tools(), false);

        $this->assertSame([
            'Tests\\GeneratedTrait\\Gdocs\\GdocsSearchDocsTool',
            'Tests\\GeneratedTrait\\N8n\\N8nRunWorkflowTool',
        ], array_map(static fn (object $tool): string => $tool::class, $tools));
    }

    public function test_trait_can_exclude_selected_classes(): void
    {
        $this->configureManifests();
        $this->defineGeneratedClasses();

        $agent = new TraitExceptToolsAgent;
        $tools = iterator_to_array($agent->tools(), false);

        $this->assertSame([
            'Tests\\GeneratedTrait\\Gdocs\\GdocsSearchDocsTool',
            'Tests\\GeneratedTrait\\N8n\\N8nRunWorkflowTool',
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
        $this->app['config']->set('mcp-providers.generated.namespace', 'Tests\\GeneratedTrait');
    }

    private function defineGeneratedClasses(): void
    {
        if (! class_exists('Tests\\GeneratedTrait\\Gdocs\\GdocsListDocsTool')) {
            eval('namespace Tests\\GeneratedTrait\\Gdocs; final class GdocsListDocsTool {}');
        }
        if (! class_exists('Tests\\GeneratedTrait\\Gdocs\\GdocsSearchDocsTool')) {
            eval('namespace Tests\\GeneratedTrait\\Gdocs; final class GdocsSearchDocsTool {}');
        }
        if (! class_exists('Tests\\GeneratedTrait\\N8n\\N8nRunWorkflowTool')) {
            eval('namespace Tests\\GeneratedTrait\\N8n; final class N8nRunWorkflowTool {}');
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

final class TraitAllToolsAgent
{
    use HasMcpTools;

    /**
     * @return list<string>
     */
    protected function mcpServers(): array
    {
        return ['gdocs', 'n8n'];
    }
}

final class TraitOnlyToolsAgent
{
    use HasMcpTools;

    /**
     * @return list<string>
     */
    protected function mcpServers(): array
    {
        return ['gdocs', 'n8n'];
    }

    /**
     * @return list<class-string>
     */
    protected function mcpOnlyToolClasses(): array
    {
        return [
            'Tests\\GeneratedTrait\\Gdocs\\GdocsSearchDocsTool',
            'Tests\\GeneratedTrait\\N8n\\N8nRunWorkflowTool',
        ];
    }
}

final class TraitExceptToolsAgent
{
    use HasMcpTools;

    /**
     * @return list<string>
     */
    protected function mcpServers(): array
    {
        return ['gdocs', 'n8n'];
    }

    /**
     * @return list<class-string>
     */
    protected function mcpExceptToolClasses(): array
    {
        return [
            'Tests\\GeneratedTrait\\Gdocs\\GdocsListDocsTool',
        ];
    }
}
