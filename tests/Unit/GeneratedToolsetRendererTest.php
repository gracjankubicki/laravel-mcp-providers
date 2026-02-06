<?php

declare(strict_types=1);

namespace Tests\Unit;

use Laravel\McpProviders\Generation\GeneratedToolsetRenderer;
use PHPUnit\Framework\TestCase;

final class GeneratedToolsetRendererTest extends TestCase
{
    public function test_it_renders_empty_classes_array_body(): void
    {
        $renderer = new GeneratedToolsetRenderer;

        $source = $renderer->render(
            namespace: 'App\\Ai\\Tools\\Generated',
            className: 'McpToolset',
            toolClasses: [],
        );

        $this->assertStringContainsString('return [];', $source);
    }

    public function test_it_renders_fully_qualified_tool_class_entries(): void
    {
        $renderer = new GeneratedToolsetRenderer;

        $source = $renderer->render(
            namespace: 'App\\Ai\\Tools\\Generated',
            className: 'McpToolset',
            toolClasses: ['App\\Ai\\Tools\\Generated\\Gdocs\\GdocsSearchDocsTool'],
        );

        $this->assertStringContainsString(
            '\\App\\Ai\\Tools\\Generated\\Gdocs\\GdocsSearchDocsTool::class',
            $source
        );
    }
}
