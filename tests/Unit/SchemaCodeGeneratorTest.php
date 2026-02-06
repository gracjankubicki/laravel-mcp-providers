<?php

declare(strict_types=1);

namespace Tests\Unit;

use Laravel\McpProviders\Generation\SchemaCodeGenerator;
use PHPUnit\Framework\TestCase;

final class SchemaCodeGeneratorTest extends TestCase
{
    public function test_it_generates_required_and_constrained_fields(): void
    {
        $generator = new SchemaCodeGenerator;

        $code = $generator->generate([
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'minLength' => 2,
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 50,
                ],
            ],
            'required' => ['query'],
        ]);

        $this->assertStringContainsString("'query' => \$schema->string()->min(2)->required()", $code);
        $this->assertStringContainsString("'limit' => \$schema->integer()->min(1)->max(50)", $code);
    }

    public function test_it_handles_array_object_number_boolean_nullable_enum_and_format_rules(): void
    {
        $generator = new SchemaCodeGenerator;

        $code = $generator->generate([
            'properties' => [
                'score' => ['type' => 'number', 'minimum' => 0.1, 'maximum' => 99.9],
                'flag' => ['type' => 'boolean'],
                'tags' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 3,
                    'items' => ['type' => ['null', 'string'], 'enum' => ['a', 'b']],
                ],
                'payload' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'url' => ['type' => 'string', 'format' => 'uri', 'pattern' => '^https://'],
                    ],
                    'required' => ['url'],
                ],
                'status' => ['type' => 'string', 'description' => 'status', 'nullable' => true],
            ],
            'required' => ['payload'],
        ]);

        $this->assertStringContainsString("'score' => \$schema->number()->min(0.1)->max(99.9)", $code);
        $this->assertStringContainsString("'flag' => \$schema->boolean()", $code);
        $this->assertStringContainsString('->array()->items($schema->string()->enum(array', $code);
        $this->assertStringContainsString('->min(1)->max(3)', $code);
        $this->assertStringContainsString("->object(['url' => \$schema->string()->pattern('^https://')->format('uri')->required()])->withoutAdditionalProperties()->required()", $code);
        $this->assertStringContainsString("'status' => \$schema->string()->description('status')->nullable()", $code);
    }

    public function test_it_returns_empty_array_when_properties_are_missing_or_invalid(): void
    {
        $generator = new SchemaCodeGenerator;

        $this->assertSame('[]', $generator->generate([]));
        $this->assertSame('[]', $generator->generate(['properties' => ['x' => 'invalid']]));
    }

    public function test_it_handles_object_fallbacks_and_type_fallbacks(): void
    {
        $generator = new SchemaCodeGenerator;

        $code = $generator->generate([
            'properties' => [
                'obj_empty' => ['type' => 'object', 'properties' => []],
                'obj_invalid' => ['type' => 'object', 'properties' => ['x' => 'invalid']],
                'max_only' => ['type' => 'string', 'maxLength' => 10],
                'type_non_array_or_string' => ['type' => 123],
                'type_only_null' => ['type' => ['null']],
            ],
        ]);

        $this->assertStringContainsString("'obj_empty' => \$schema->object()", $code);
        $this->assertStringContainsString("'obj_invalid' => \$schema->object()", $code);
        $this->assertStringContainsString("'max_only' => \$schema->string()->max(10)", $code);
        $this->assertStringContainsString("'type_non_array_or_string' => \$schema->string()", $code);
        $this->assertStringContainsString("'type_only_null' => \$schema->string()->nullable()", $code);
    }
}
