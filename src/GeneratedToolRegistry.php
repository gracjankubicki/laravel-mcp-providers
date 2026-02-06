<?php

declare(strict_types=1);

namespace Laravel\McpProviders;

use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Laravel\McpProviders\Generation\ToolClassNameResolver;
use RuntimeException;
use Throwable;

final class GeneratedToolRegistry
{
    public function __construct(
        private readonly Container $container,
        private readonly ConfigServerRepository $servers,
        private readonly ToolClassNameResolver $classNameResolver,
        private readonly Filesystem $files,
    ) {}

    /**
     * @param  list<string>|null  $servers
     * @param  array<int|string, mixed>|null  $toolClasses
     * @return iterable<object>
     */
    public function forServers(?array $servers = null, ?array $toolClasses = null): iterable
    {
        foreach ($this->classNamesForServers($servers, $toolClasses) as $className) {
            if (! class_exists($className)) {
                throw new RuntimeException(
                    'Generated tool class ['.$className.'] was not found. Run `php artisan ai-mcp:generate`.'
                );
            }

            yield $this->container->make($className);
        }
    }

    /**
     * @param  list<string>|null  $servers
     * @param  array<int|string, mixed>|null  $toolClasses
     * @return list<string>
     */
    private function classNamesForServers(?array $servers = null, ?array $toolClasses = null): array
    {
        $serverConfigs = $this->servers->selected($servers ?? []);

        ksort($serverConfigs);

        $baseNamespace = trim(
            (string) config('mcp-providers.generated.namespace', 'App\\Ai\\Tools\\Generated'),
            '\\'
        );

        $allowedClasses = $this->allowedClassesMap($toolClasses);
        $usedClassNames = [];
        $classNames = [];

        foreach ($serverConfigs as $serverSlug => $serverConfig) {
            $manifestPath = $serverConfig['manifest'] ?? null;

            if (! is_string($manifestPath) || ! $this->files->isFile($manifestPath)) {
                continue;
            }

            $manifest = $this->decodeManifest($manifestPath);
            $tools = $manifest['tools'] ?? [];

            if (! is_array($tools)) {
                continue;
            }

            usort($tools, function (mixed $a, mixed $b): int {
                $aName = is_array($a) && isset($a['name']) ? (string) $a['name'] : '';
                $bName = is_array($b) && isset($b['name']) ? (string) $b['name'] : '';

                return $aName <=> $bName;
            });

            foreach ($tools as $tool) {
                if (! is_array($tool) || ! isset($tool['name']) || ! is_string($tool['name'])) {
                    continue;
                }

                $className = $this->classNameResolver->resolve($serverSlug, $tool['name'], $usedClassNames);
                $fqcn = $baseNamespace.'\\'.Str::studly($serverSlug).'\\'.$className;

                if (! $this->isToolAllowed($fqcn, $allowedClasses)) {
                    continue;
                }

                $classNames[] = $fqcn;
            }
        }

        return $classNames;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeManifest(string $manifestPath): array
    {
        try {
            $contents = $this->files->get($manifestPath);
        } catch (Throwable) {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, true>|null  $allowedClasses
     */
    private function isToolAllowed(string $className, ?array $allowedClasses): bool
    {
        if ($allowedClasses === null) {
            return true;
        }

        return isset($allowedClasses[$className]);
    }

    /**
     * @param  array<int|string, mixed>|null  $toolClasses
     * @return array<string, true>|null
     */
    private function allowedClassesMap(?array $toolClasses): ?array
    {
        if ($toolClasses === null || $toolClasses === []) {
            return null;
        }

        $allowed = [];

        foreach ($toolClasses as $toolClass) {
            if (! is_string($toolClass) || $toolClass === '') {
                continue;
            }

            $allowed[$toolClass] = true;
        }

        return $allowed;
    }
}
