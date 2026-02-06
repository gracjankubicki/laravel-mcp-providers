<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Console;

use Illuminate\Console\Command;
use Laravel\McpProviders\ConfigServerRepository;
use Laravel\McpProviders\Contracts\McpClient;
use RuntimeException;
use Throwable;

final class HealthCheckCommand extends Command
{
    protected $signature = 'ai-mcp:health
        {--server=* : Server slug(s) to check}
        {--fail-fast : Stop on first server error}';

    protected $description = 'Run MCP connectivity health checks against configured servers.';

    public function __construct(
        private readonly ConfigServerRepository $servers,
        private readonly McpClient $client,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $selected = $this->selectedServers();

            if ($selected === []) {
                $this->warn('No MCP servers selected for health check.');

                return self::SUCCESS;
            }

            $failed = false;
            $failFast = (bool) $this->option('fail-fast');
            ksort($selected);

            foreach ($selected as $slug => $server) {
                try {
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

                    $this->line(
                        sprintf(
                            'Healthy: %s (%d tool%s)',
                            $slug,
                            count($tools),
                            count($tools) === 1 ? '' : 's',
                        )
                    );
                } catch (Throwable $e) {
                    $failed = true;
                    $this->error('Unhealthy: '.$slug.' - '.$e->getMessage());

                    if ($failFast) {
                        return self::FAILURE;
                    }
                }
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
    private function endpoint(string $slug, array $server): string
    {
        $endpoint = $server['endpoint'] ?? null;

        if (! is_string($endpoint) || $endpoint === '') {
            throw new RuntimeException('Missing endpoint for MCP server ['.$slug.'].');
        }

        return $endpoint;
    }
}
