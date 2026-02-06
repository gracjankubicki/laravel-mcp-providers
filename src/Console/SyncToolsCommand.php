<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Console;

use Illuminate\Console\Command;

final class SyncToolsCommand extends Command
{
    protected $signature = 'ai-mcp:sync
        {--server=* : Server slug(s) to sync}
        {--dry-run : Do not write any files}
        {--prune : Prune manifests for configured but unselected servers}
        {--fail-fast : Stop discover on first error}
        {--clean : Remove generated files before generation}
        {--fail-on-collision : Fail generation when class naming collision occurs}';

    protected $description = 'Run discover and generate in sequence.';

    public function handle(): int
    {
        $discoverExit = $this->call('ai-mcp:discover', [
            '--server' => $this->option('server'),
            '--dry-run' => (bool) $this->option('dry-run'),
            '--prune' => (bool) $this->option('prune'),
            '--fail-fast' => (bool) $this->option('fail-fast'),
        ]);

        if ($discoverExit !== self::SUCCESS) {
            return self::FAILURE;
        }

        return $this->call('ai-mcp:generate', [
            '--server' => $this->option('server'),
            '--dry-run' => (bool) $this->option('dry-run'),
            '--clean' => (bool) $this->option('clean'),
            '--fail-on-collision' => (bool) $this->option('fail-on-collision'),
        ]);
    }
}
