<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Laravel\McpProviders\ConfigServerRepository;
use Laravel\McpProviders\Generation\GeneratedToolRenderer;
use Laravel\McpProviders\Generation\ToolClassNameResolver;
use RuntimeException;
use Throwable;

final class GenerateToolsCommand extends Command
{
    protected $signature = 'ai-mcp:generate
        {--server=* : Server slug(s) to generate}
        {--dry-run : Show what would be generated}
        {--clean : Remove generated files for selected servers before generation}
        {--fail-on-collision : Fail when generated class name collides}';

    protected $description = 'Generate Laravel AI tools from MCP manifest files.';

    public function __construct(
        private readonly ConfigServerRepository $servers,
        private readonly GeneratedToolRenderer $renderer,
        private readonly ToolClassNameResolver $resolver,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $servers = $this->selectedServers();

            if ($servers === []) {
                $this->warn('No MCP servers selected for generation.');

                return self::SUCCESS;
            }

            $usedClassNames = [];
            $baseNamespace = trim((string) config('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated'), '\\');
            $basePath = (string) config('ai-mcp.generated.path', app_path('Ai/Tools/Generated'));
            $allowCollisionSuffix = ! (bool) $this->option('fail-on-collision');

            if ((bool) $this->option('clean')) {
                $this->cleanSelectedServerDirectories($basePath, array_keys($servers));
            }

            $toolDefinitions = $this->loadToolDefinitions($servers);
            $generatedCount = 0;

            foreach ($toolDefinitions as $definition) {
                $serverSlug = $definition['server_slug'];
                $toolName = $definition['tool_name'];
                $description = $definition['description'];
                $inputSchema = $definition['input_schema'];

                $serverNamespace = Str::studly($serverSlug);
                $namespace = $baseNamespace.'\\'.$serverNamespace;
                $className = $this->resolver->resolve(
                    serverSlug: $serverSlug,
                    toolName: $toolName,
                    usedClassNames: $usedClassNames,
                    allowCollisionSuffix: $allowCollisionSuffix,
                );
                $directory = rtrim($basePath, '/').'/'.$serverNamespace;
                $path = $directory.'/'.$className.'.php';

                $source = $this->renderer->render(
                    namespace: $namespace,
                    className: $className,
                    serverSlug: $serverSlug,
                    toolName: $toolName,
                    description: $description,
                    inputSchema: $inputSchema,
                );

                if ((bool) $this->option('dry-run')) {
                    $this->line('[dry-run] '.$path.' => '.$namespace.'\\'.$className);
                    $generatedCount++;

                    continue;
                }

                if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
                    throw new RuntimeException('Unable to create directory: '.$directory);
                }

                file_put_contents($path, $source);
                $this->line('Generated: '.$path);
                $generatedCount++;
            }

            $this->info('Generated tools: '.$generatedCount);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function selectedServers(): array
    {
        $selected = $this->option('server');
        $selected = is_array($selected) ? array_values(array_filter($selected, is_string(...))) : [];

        return $this->servers->selected($selected);
    }

    private function cleanSelectedServerDirectories(string $basePath, array $serverSlugs): void
    {
        foreach ($serverSlugs as $serverSlug) {
            $directory = rtrim($basePath, '/').'/'.Str::studly($serverSlug);

            if (! is_dir($directory)) {
                continue;
            }

            $this->deleteDirectory($directory);
            $this->line('Cleaned: '.$directory);
        }
    }

    private function deleteDirectory(string $directory): void
    {
        $items = @scandir($directory);

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
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    /**
     * @param  array<string, array<string, mixed>>  $servers
     * @return list<array{
     *   server_slug: string,
     *   tool_name: string,
     *   description: string,
     *   input_schema: array<string, mixed>
     * }>
     */
    private function loadToolDefinitions(array $servers): array
    {
        $definitions = [];
        ksort($servers);

        foreach ($servers as $serverSlug => $serverConfig) {
            $manifestPath = $serverConfig['manifest'] ?? null;

            if (! is_string($manifestPath) || $manifestPath === '') {
                $this->warn('Skipping ['.$serverSlug.'] - missing `manifest` path.');

                continue;
            }

            if (! is_file($manifestPath)) {
                $this->warn('Skipping ['.$serverSlug.'] - manifest not found: '.$manifestPath);

                continue;
            }

            $manifest = $this->decodeManifest($manifestPath);
            $tools = $manifest['tools'] ?? [];

            if (! is_array($tools)) {
                $this->warn('Skipping ['.$serverSlug.'] - invalid manifest `tools` shape.');

                continue;
            }

            foreach ($tools as $tool) {
                if (! is_array($tool) || ! isset($tool['name']) || ! is_string($tool['name'])) {
                    continue;
                }

                $definitions[] = [
                    'server_slug' => $serverSlug,
                    'tool_name' => $tool['name'],
                    'description' => isset($tool['description']) && is_string($tool['description'])
                        ? $tool['description']
                        : $tool['name'],
                    'input_schema' => isset($tool['input_schema']) && is_array($tool['input_schema'])
                        ? $tool['input_schema']
                        : [],
                ];
            }
        }

        usort($definitions, static function (array $a, array $b): int {
            $byServer = $a['server_slug'] <=> $b['server_slug'];

            if ($byServer !== 0) {
                return $byServer;
            }

            return $a['tool_name'] <=> $b['tool_name'];
        });

        return $definitions;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeManifest(string $manifestPath): array
    {
        $contents = @file_get_contents($manifestPath);

        if ($contents === false) {
            throw new RuntimeException('Unable to read manifest: '.$manifestPath);
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid JSON manifest: '.$manifestPath);
        }

        return $decoded;
    }
}
