<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Laravel\McpProviders\ConfigServerRepository;
use Laravel\McpProviders\Generation\GeneratedToolRenderer;
use Laravel\McpProviders\Generation\GeneratedToolsetRenderer;
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
        private readonly GeneratedToolsetRenderer $toolsetRenderer,
        private readonly ToolClassNameResolver $resolver,
        private readonly Filesystem $files,
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
            $baseNamespace = trim((string) config('mcp-providers.generated.namespace', 'App\\Ai\\Tools\\Generated'), '\\');
            $basePath = (string) config('mcp-providers.generated.path', app_path('Ai/Tools/Generated'));
            $allowCollisionSuffix = ! (bool) $this->option('fail-on-collision');
            $dryRun = (bool) $this->option('dry-run');

            if ((bool) $this->option('clean')) {
                $this->cleanSelectedServerDirectories($basePath, array_keys($servers));
            }

            $toolDefinitions = $this->loadToolDefinitions($servers);
            $generatedCount = 0;
            $toolClassesByServer = [];
            $allToolClasses = [];

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
                $class = $namespace.'\\'.$className;

                $source = $this->renderer->render(
                    namespace: $namespace,
                    className: $className,
                    serverSlug: $serverSlug,
                    toolName: $toolName,
                    description: $description,
                    inputSchema: $inputSchema,
                );

                $toolClassesByServer[$serverSlug][] = $class;
                $allToolClasses[] = $class;
                $this->writeGeneratedFile($path, $source, $class, $dryRun);
                $generatedCount++;
            }

            $generatedCount += $this->generateToolsetClasses(
                $baseNamespace,
                $basePath,
                $toolClassesByServer,
                $allToolClasses,
                $dryRun,
                $allowCollisionSuffix,
            );

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

    /**
     * @param  list<string>  $serverSlugs
     */
    private function cleanSelectedServerDirectories(string $basePath, array $serverSlugs): void
    {
        foreach ($serverSlugs as $serverSlug) {
            $directory = rtrim($basePath, '/').'/'.Str::studly($serverSlug);

            if (! $this->files->isDirectory($directory)) {
                continue;
            }

            try {
                if ($this->files->deleteDirectory($directory)) {
                    $this->line('Cleaned: '.$directory);
                }
            } catch (Throwable $e) {
                $this->warn('Skipping clean for ['.$directory.']: '.$e->getMessage());
            }
        }

        $aggregateToolset = rtrim($basePath, '/').'/McpToolset.php';

        if (! $this->files->isFile($aggregateToolset)) {
            return;
        }

        try {
            if ($this->files->delete($aggregateToolset)) {
                $this->line('Cleaned: '.$aggregateToolset);
            }
        } catch (Throwable $e) {
            $this->warn('Skipping clean for ['.$aggregateToolset.']: '.$e->getMessage());
        }
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if ($this->files->isDirectory($directory)) {
            return;
        }

        if (! $this->files->makeDirectory($directory, 0755, true)) {
            throw new RuntimeException('Unable to create directory: '.$directory);
        }
    }

    private function writeGeneratedFile(
        string $path,
        string $source,
        string $className,
        bool $dryRun,
    ): void {
        if ($dryRun) {
            $this->line('[dry-run] '.$path.' => '.$className);

            return;
        }

        $this->ensureDirectoryExists(dirname($path));

        if ($this->files->put($path, $source) === false) {
            throw new RuntimeException('Unable to write generated tool file: '.$path);
        }

        $this->line('Generated: '.$path);
    }

    /**
     * @param  array<string, list<class-string>>  $toolClassesByServer
     * @param  list<class-string>  $allToolClasses
     */
    private function generateToolsetClasses(
        string $baseNamespace,
        string $basePath,
        array $toolClassesByServer,
        array $allToolClasses,
        bool $dryRun,
        bool $allowCollisionSuffix,
    ): int {
        if ($allToolClasses === []) {
            return 0;
        }

        $count = 0;
        $usedToolsetClassNames = [];
        ksort($toolClassesByServer);

        foreach ($toolClassesByServer as $serverSlug => $toolClasses) {
            if ($toolClasses === []) {
                continue;
            }

            sort($toolClasses);

            $serverNamespace = Str::studly($serverSlug);
            $namespace = $baseNamespace.'\\'.$serverNamespace;
            $className = $this->resolveToolsetClassName(
                $serverSlug,
                $usedToolsetClassNames,
                $allowCollisionSuffix,
            );
            $path = rtrim($basePath, '/').'/'.$serverNamespace.'/'.$className.'.php';
            $source = $this->toolsetRenderer->render(
                namespace: $namespace,
                className: $className,
                toolClasses: $toolClasses,
            );

            $this->writeGeneratedFile($path, $source, $namespace.'\\'.$className, $dryRun);
            $count++;
        }

        sort($allToolClasses);

        $aggregatePath = rtrim($basePath, '/').'/McpToolset.php';
        $aggregateClass = $baseNamespace.'\\McpToolset';
        $aggregateSource = $this->toolsetRenderer->render(
            namespace: $baseNamespace,
            className: 'McpToolset',
            toolClasses: $allToolClasses,
        );

        $this->writeGeneratedFile($aggregatePath, $aggregateSource, $aggregateClass, $dryRun);

        return $count + 1;
    }

    /**
     * @param  array<string, true>  $usedClassNames
     */
    private function resolveToolsetClassName(
        string $serverSlug,
        array &$usedClassNames,
        bool $allowCollisionSuffix,
    ): string {
        $baseName = $this->normalizeForClass($serverSlug).'Toolset';

        if (! isset($usedClassNames[$baseName])) {
            $usedClassNames[$baseName] = true;

            return $baseName;
        }

        if (! $allowCollisionSuffix) {
            throw new RuntimeException('Toolset class name collision detected for ['.$serverSlug.'] -> ['.$baseName.']');
        }

        $hashedName = $baseName.substr(md5($serverSlug), 0, 8);

        if (! isset($usedClassNames[$hashedName])) {
            $usedClassNames[$hashedName] = true;

            return $hashedName;
        }

        $counter = 2;

        do {
            $candidate = $hashedName.$counter;
            $counter++;
        } while (isset($usedClassNames[$candidate]));

        $usedClassNames[$candidate] = true;

        return $candidate;
    }

    private function normalizeForClass(string $value): string
    {
        $studly = Str::studly(preg_replace('/[^a-zA-Z0-9]+/', ' ', $value) ?? '');

        return $studly === '' ? 'Mcp' : $studly;
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

            if (! $this->files->isFile($manifestPath)) {
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
        try {
            $contents = $this->files->get($manifestPath);
        } catch (FileNotFoundException) {
            throw new RuntimeException('Unable to read manifest: '.$manifestPath);
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid JSON manifest: '.$manifestPath);
        }

        return $decoded;
    }
}
