<?php

declare(strict_types=1);

namespace Tests\Feature;

use Laravel\McpProviders\Commands\DiscoverToolsCommand;
use Laravel\McpProviders\Commands\GenerateToolsCommand;
use Laravel\McpProviders\Commands\HealthCheckCommand;
use Laravel\McpProviders\Commands\SyncToolsCommand;
use Laravel\McpProviders\Concerns\HasMcpTools;
use Laravel\McpProviders\GeneratedToolRegistry;
use Laravel\McpProviders\GeneratedToolset;
use ReflectionMethod;
use Tests\TestCase;

final class PublicApiContractTest extends TestCase
{
    public function test_generated_toolset_public_methods_are_available(): void
    {
        $reflection = new \ReflectionClass(GeneratedToolset::class);

        $this->assertTrue($reflection->hasMethod('forServers'));
        $this->assertTrue($reflection->hasMethod('all'));
        $this->assertTrue($reflection->hasMethod('onlyClasses'));
        $this->assertTrue($reflection->hasMethod('exceptClasses'));
    }

    public function test_generated_tool_registry_for_servers_contract_is_stable(): void
    {
        $method = new ReflectionMethod(GeneratedToolRegistry::class, 'forServers');

        $this->assertCount(2, $method->getParameters());
        $this->assertSame('servers', $method->getParameters()[0]->getName());
        $this->assertSame('toolClasses', $method->getParameters()[1]->getName());
    }

    public function test_has_mcp_tools_trait_extension_points_are_available(): void
    {
        $reflection = new \ReflectionClass(HasMcpToolsHost::class);

        $this->assertTrue($reflection->hasMethod('tools'));
        $this->assertTrue($reflection->hasMethod('mcpServers'));
        $this->assertTrue($reflection->hasMethod('mcpOnlyToolClasses'));
        $this->assertTrue($reflection->hasMethod('mcpExceptToolClasses'));
    }

    public function test_artisan_command_names_remain_stable(): void
    {
        $discover = $this->app->make(DiscoverToolsCommand::class);
        $generate = $this->app->make(GenerateToolsCommand::class);
        $sync = $this->app->make(SyncToolsCommand::class);
        $health = $this->app->make(HealthCheckCommand::class);

        $this->assertSame('ai-mcp:discover', $discover->getName());
        $this->assertSame('ai-mcp:generate', $generate->getName());
        $this->assertSame('ai-mcp:sync', $sync->getName());
        $this->assertSame('ai-mcp:health', $health->getName());
    }
}

final class HasMcpToolsHost
{
    use HasMcpTools;
}
