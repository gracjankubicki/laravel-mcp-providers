<?php

declare(strict_types=1);

namespace Tests\Unit;

use Laravel\McpProviders\Generation\ToolClassNameResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ToolClassNameResolverTest extends TestCase
{
    public function test_it_prefixes_class_name_with_server_slug(): void
    {
        $resolver = new ToolClassNameResolver;
        $used = [];

        $className = $resolver->resolve('gdocs', 'search_docs', $used);

        $this->assertSame('GdocsSearchDocsTool', $className);
    }

    public function test_it_adds_hash_when_normalized_names_collide(): void
    {
        $resolver = new ToolClassNameResolver;
        $used = [];

        $first = $resolver->resolve('crm-api', 'create_ticket', $used);
        $second = $resolver->resolve('crm_api', 'create-ticket', $used);

        $this->assertSame('CrmApiCreateTicketTool', $first);
        $this->assertMatchesRegularExpression('/^CrmApiCreateTicketTool[a-f0-9]{8}$/', $second);
        $this->assertNotSame($first, $second);
    }

    public function test_it_can_throw_when_collisions_are_not_allowed(): void
    {
        $resolver = new ToolClassNameResolver;
        $used = [];

        $resolver->resolve('crm-api', 'create_ticket', $used, allowCollisionSuffix: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tool class name collision detected');

        $resolver->resolve('crm_api', 'create-ticket', $used, allowCollisionSuffix: false);
    }

    public function test_it_appends_increment_when_hash_name_is_already_taken(): void
    {
        $resolver = new ToolClassNameResolver;
        $used = [];

        $resolver->resolve('crm-api', 'create_ticket', $used);
        $hashed = $resolver->resolve('crm_api', 'create-ticket', $used);
        $used[$hashed.'2'] = true;

        $next = $resolver->resolve('crm_api', 'create-ticket', $used);

        $this->assertSame($hashed.'3', $next);
    }

    public function test_it_uses_mcp_fallback_for_empty_normalized_fragments(): void
    {
        $resolver = new ToolClassNameResolver;
        $used = [];

        $className = $resolver->resolve('---', '***', $used);

        $this->assertSame('McpMcpTool', $className);
    }
}
