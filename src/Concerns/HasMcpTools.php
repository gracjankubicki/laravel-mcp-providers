<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Concerns;

use Laravel\McpProviders\GeneratedToolset;

trait HasMcpTools
{
    /**
     * @return list<string>|null
     */
    protected function mcpServers(): ?array
    {
        return null;
    }

    /**
     * @return list<class-string>
     */
    protected function mcpOnlyToolClasses(): array
    {
        return [];
    }

    /**
     * @return list<class-string>
     */
    protected function mcpExceptToolClasses(): array
    {
        return [];
    }

    /**
     * @return iterable<object>
     */
    public function tools(): iterable
    {
        $toolset = app(GeneratedToolset::class)->forServers($this->mcpServers());

        $onlyClasses = $this->mcpOnlyToolClasses();

        if ($onlyClasses !== []) {
            return $toolset->onlyClasses($onlyClasses);
        }

        $exceptClasses = $this->mcpExceptToolClasses();

        if ($exceptClasses !== []) {
            return $toolset->exceptClasses($exceptClasses);
        }

        return $toolset->all();
    }
}
