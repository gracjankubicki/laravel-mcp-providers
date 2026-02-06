<?php

declare(strict_types=1);

namespace Laravel\McpProviders;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Laravel\McpProviders\Generation\ToolClassNameResolver;
use RuntimeException;

final class GeneratedToolRegistry
{
    public function __construct(
        private readonly Container $container,
        private readonly ConfigServerRepository $servers,
        private readonly ToolClassNameResolver $classNameResolver,
    ) {}

    /**
     * @param  list<string>|null  $servers
     * @param  list<string>|array<string, list<string>>|null  $toolAllowlist
     * @return iterable<object>
     */
    public function forServers(?array $servers = null, ?array $toolAllowlist = null): iterable
    {
        foreach ($this->classNamesForServers($servers, $toolAllowlist) as $className) {
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
     * @param  list<string>|array<string, list<string>>|null  $toolAllowlist
     * @return list<string>
     */
    private function classNamesForServers(?array $servers = null, ?array $toolAllowlist = null): array
    {
        $serverConfigs = $this->servers->selected($servers ?? []);

        ksort($serverConfigs);

        $baseNamespace = trim(
            (string) config('ai-mcp.generated.namespace', 'App\\Ai\\Tools\\Generated'),
            '\\'
        );

        $usedClassNames = [];
        $classNames = [];

        foreach ($serverConfigs as $serverSlug => $serverConfig) {
            $manifestPath = $serverConfig['manifest'] ?? null;

            if (! is_string($manifestPath) || ! is_file($manifestPath)) {
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

                if (! $this->isToolAllowed($serverSlug, $tool['name'], $toolAllowlist)) {
                    continue;
                }

                $className = $this->classNameResolver->resolve($serverSlug, $tool['name'], $usedClassNames);
                $classNames[] = $baseNamespace.'\\'.Str::studly($serverSlug).'\\'.$className;
            }
        }

        return $classNames;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeManifest(string $manifestPath): array
    {
        $contents = @file_get_contents($manifestPath);

        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  list<string>|array<string, list<string>>|null  $toolAllowlist
     */
    private function isToolAllowed(string $serverSlug, string $toolName, ?array $toolAllowlist): bool
    {
        if ($toolAllowlist === null || $toolAllowlist === []) {
            return true;
        }

        if (array_is_list($toolAllowlist)) {
            return in_array($serverSlug.'.'.$toolName, $toolAllowlist, true);
        }

        $allowedForServer = $toolAllowlist[$serverSlug] ?? [];

        if (! is_array($allowedForServer)) {
            return false;
        }

        return in_array($toolName, $allowedForServer, true);
    }
}
