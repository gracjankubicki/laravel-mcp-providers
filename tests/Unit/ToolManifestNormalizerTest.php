<?php

declare(strict_types=1);

namespace Tests\Unit;

use Laravel\McpProviders\ToolManifestNormalizer;
use PHPUnit\Framework\TestCase;

final class ToolManifestNormalizerTest extends TestCase
{
    public function test_it_normalizes_manifest_structure_and_sorts_tools_and_schema_keys(): void
    {
        $normalizer = new ToolManifestNormalizer;

        $manifest = $normalizer->normalize(
            serverSlug: 'gdocs',
            endpointEnv: 'MCP_GDOCS_URL',
            tools: [
                [
                    'name' => 'z_tool',
                    'description' => 'Z',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'b' => ['type' => 'string'],
                            'a' => ['type' => 'string'],
                        ],
                    ],
                ],
                [
                    'name' => 'a_tool',
                    'input_schema' => ['type' => 'object'],
                ],
            ],
            generatedAt: '2026-02-06T12:00:00Z',
        );

        $this->assertSame(1, $manifest['version']);
        $this->assertSame('gdocs', $manifest['server']['slug']);
        $this->assertSame('MCP_GDOCS_URL', $manifest['server']['endpoint_env']);
        $this->assertSame('2026-02-06T12:00:00Z', $manifest['generated_at']);
        $this->assertSame('a_tool', $manifest['tools'][0]['name']);
        $this->assertSame('z_tool', $manifest['tools'][1]['name']);
        $this->assertSame(['properties', 'type'], array_keys($manifest['tools'][1]['input_schema']));
        $this->assertSame('a_tool', $manifest['tools'][0]['description']);
    }

    public function test_it_omits_endpoint_env_when_not_provided(): void
    {
        $normalizer = new ToolManifestNormalizer;

        $manifest = $normalizer->normalize(
            serverSlug: 'n8n',
            endpointEnv: null,
            tools: [],
            generatedAt: '2026-02-06T12:00:00Z',
        );

        $this->assertSame(['slug' => 'n8n'], $manifest['server']);
    }
}
