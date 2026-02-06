<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\McpProviders\Contracts\McpInvocationRouter;
use Laravel\McpProviders\Tools\AbstractMcpTool;
use PHPUnit\Framework\TestCase;

final class AbstractMcpToolTest extends TestCase
{
    public function test_it_returns_runtime_name(): void
    {
        $tool = new FakeMcpTool(new FakeRouter(['ok' => true]));

        $this->assertSame('gdocs.search_docs', $tool->name());
    }

    public function test_it_json_encodes_non_scalar_results(): void
    {
        $tool = new FakeMcpTool(new FakeRouter(['status' => 'ok']));

        $this->assertSame('{"status":"ok"}', $tool->handle(new \Laravel\Ai\Tools\Request([])));
    }

    public function test_it_returns_string_results_directly(): void
    {
        $tool = new FakeMcpTool(new FakeRouter('plain'));

        $this->assertSame('plain', $tool->handle(new \Laravel\Ai\Tools\Request([])));
    }
}

final class FakeRouter implements McpInvocationRouter
{
    public function __construct(private readonly mixed $result) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function invoke(string $serverSlug, string $toolName, array $arguments): mixed
    {
        return $this->result;
    }
}

final class FakeMcpTool extends AbstractMcpTool
{
    public function serverSlug(): string
    {
        return 'gdocs';
    }

    public function rawToolName(): string
    {
        return 'search_docs';
    }

    public function description(): string
    {
        return 'Search docs';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
