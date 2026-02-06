<?php

declare(strict_types=1);

namespace Laravel\McpProviders;

final class GeneratedToolset
{
    /**
     * @var list<string>|null
     */
    private ?array $servers = null;

    public function __construct(private readonly GeneratedToolRegistry $registry) {}

    /**
     * @param  list<string>|null  $servers
     */
    public function forServers(?array $servers = null): self
    {
        $clone = clone $this;
        $clone->servers = $servers;

        return $clone;
    }

    /**
     * @return array<int, object>
     */
    public function all(): array
    {
        return iterator_to_array($this->registry->forServers($this->servers), false);
    }

    /**
     * @param  list<class-string>  $toolClasses
     * @return array<int, object>
     */
    public function onlyClasses(array $toolClasses): array
    {
        return iterator_to_array($this->registry->forServers($this->servers, $toolClasses), false);
    }

    /**
     * @param  list<class-string>  $toolClasses
     * @return array<int, object>
     */
    public function exceptClasses(array $toolClasses): array
    {
        $excluded = array_fill_keys($toolClasses, true);

        return array_values(array_filter(
            $this->all(),
            static fn (object $tool): bool => ! isset($excluded[$tool::class]),
        ));
    }
}
