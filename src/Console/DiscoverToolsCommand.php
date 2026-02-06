<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Laravel\McpProviders\ConfigServerRepository;
use Laravel\McpProviders\Contracts\McpClient;
use Laravel\McpProviders\ToolManifestNormalizer;
use RuntimeException;
use Throwable;

final class DiscoverToolsCommand extends Command
{
    protected $signature = 'ai-mcp:discover
        {--server=* : Server slug(s) to discover}
        {--dry-run : Show what would be written}
        {--prune : Remove manifest files for configured but unselected servers}
        {--fail-fast : Stop on first server error}';

    protected $description = 'Discover tools from MCP servers and write normalized manifests.';

    public function __construct(
        private readonly ConfigServerRepository $servers,
        private readonly McpClient $client,
        private readonly ToolManifestNormalizer $normalizer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $selected = $this->selectedServers();
            $allServers = $this->servers->all();
            $dryRun = (bool) $this->option('dry-run');
            $failFast = (bool) $this->option('fail-fast');
            $generatedAt = Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z');
            $failed = false;

            if ($selected === []) {
                $this->warn('No MCP servers selected for discover.');

                return self::SUCCESS;
            }

            ksort($selected);

            foreach ($selected as $slug => $server) {
                try {
                    $manifestPath = $this->manifestPath($slug, $server);
                    $endpoint = $this->endpoint($slug, $server);
                    $retry = $this->servers->retry($server);
                    $tools = $this->client->listTools(
                        endpoint: $endpoint,
                        headers: $this->servers->headers($server),
                        timeout: (int) ($server['timeout'] ?? 60),
                        retryAttempts: $retry['attempts'],
                        retryBackoffMs: $retry['backoff_ms'],
                        retryMaxBackoffMs: $retry['max_backoff_ms'],
                    );

                    $manifest = $this->normalizer->normalize(
                        serverSlug: $slug,
                        endpointEnv: isset($server['endpoint_env']) && is_string($server['endpoint_env'])
                            ? $server['endpoint_env']
                            : null,
                        tools: $tools,
                        generatedAt: $generatedAt,
                    );

                    if ($dryRun) {
                        $this->line('[dry-run] '.$slug.' => '.$manifestPath.' (tools: '.count($tools).')');

                        continue;
                    }

                    $directory = dirname($manifestPath);

                    if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
                        throw new RuntimeException('Unable to create directory: '.$directory);
                    }

                    $json = json_encode(
                        $manifest,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                    );
                    file_put_contents($manifestPath, $json.PHP_EOL);

                    $this->line('Discovered: '.$slug.' -> '.$manifestPath);
                } catch (Throwable $e) {
                    $failed = true;
                    $this->error('Discover failed for ['.$slug.']: '.$e->getMessage());

                    if ($failFast) {
                        return self::FAILURE;
                    }
                }
            }

            if ((bool) $this->option('prune')) {
                $this->pruneManifests($allServers, $selected, $dryRun);
            }

            return $failed ? self::FAILURE : self::SUCCESS;
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
     * @param  array<string, mixed>  $server
     */
    private function manifestPath(string $slug, array $server): string
    {
        $path = $server['manifest'] ?? null;

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Missing manifest path for MCP server ['.$slug.'].');
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $server
     */
    private function endpoint(string $slug, array $server): string
    {
        $endpoint = $server['endpoint'] ?? null;

        if (! is_string($endpoint) || $endpoint === '') {
            throw new RuntimeException('Missing endpoint for MCP server ['.$slug.'].');
        }

        return $endpoint;
    }

    /**
     * @param  array<string, array<string, mixed>>  $allServers
     * @param  array<string, array<string, mixed>>  $selectedServers
     */
    private function pruneManifests(array $allServers, array $selectedServers, bool $dryRun): void
    {
        $selectedSlugs = array_keys($selectedServers);

        foreach ($allServers as $slug => $server) {
            if (in_array($slug, $selectedSlugs, true)) {
                continue;
            }

            $manifestPath = $server['manifest'] ?? null;

            if (! is_string($manifestPath) || ! is_file($manifestPath)) {
                continue;
            }

            if ($dryRun) {
                $this->line('[dry-run] prune '.$manifestPath);

                continue;
            }

            unlink($manifestPath);
            $this->line('Pruned: '.$manifestPath);
        }
    }
}
