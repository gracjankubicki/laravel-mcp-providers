<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Generation;

final class SchemaCodeGenerator
{
    /**
     * @param  array<string, mixed>  $inputSchema
     */
    public function generate(array $inputSchema): string
    {
        $properties = $inputSchema['properties'] ?? [];

        if (! is_array($properties) || $properties === []) {
            return '[]';
        }

        $required = array_flip(
            is_array($inputSchema['required'] ?? null) ? $inputSchema['required'] : []
        );

        $lines = [];

        foreach ($properties as $name => $schema) {
            if (! is_array($schema)) {
                continue;
            }

            $expression = $this->typeExpression($schema);

            if (isset($required[$name])) {
                $expression .= '->required()';
            }

            $lines[] = '            '.var_export((string) $name, true).' => '.$expression.',';
        }

        if ($lines === []) {
            return '[]';
        }

        return "[\n".implode("\n", $lines)."\n        ]";
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function typeExpression(array $schema): string
    {
        $type = $this->primaryType($schema);
        $expression = match ($type) {
            'integer' => '$schema->integer()',
            'number' => '$schema->number()',
            'boolean' => '$schema->boolean()',
            'array' => $this->arrayExpression($schema),
            'object' => $this->objectExpression($schema),
            default => '$schema->string()',
        };

        return $this->applyCommonModifiers($expression, $schema, $type);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function arrayExpression(array $schema): string
    {
        $expression = '$schema->array()';

        if (isset($schema['items']) && is_array($schema['items'])) {
            $expression .= '->items('.$this->typeExpression($schema['items']).')';
        }

        return $expression;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function objectExpression(array $schema): string
    {
        $properties = $schema['properties'] ?? [];

        if (! is_array($properties) || $properties === []) {
            $expression = '$schema->object()';
        } else {
            $required = array_flip(
                is_array($schema['required'] ?? null) ? $schema['required'] : []
            );

            $propertyExpressions = [];

            foreach ($properties as $name => $propertySchema) {
                if (! is_array($propertySchema)) {
                    continue;
                }

                $childExpression = $this->typeExpression($propertySchema);

                if (isset($required[$name])) {
                    $childExpression .= '->required()';
                }

                $propertyExpressions[] = var_export((string) $name, true).' => '.$childExpression;
            }

            if ($propertyExpressions === []) {
                $expression = '$schema->object()';
            } else {
                $expression = '$schema->object(['.implode(', ', $propertyExpressions).'])';
            }
        }

        if (($schema['additionalProperties'] ?? true) === false) {
            $expression .= '->withoutAdditionalProperties()';
        }

        return $expression;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function applyCommonModifiers(string $expression, array $schema, string $type): string
    {
        if (isset($schema['description']) && is_string($schema['description']) && $schema['description'] !== '') {
            $expression .= '->description('.var_export($schema['description'], true).')';
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && $schema['enum'] !== []) {
            $expression .= '->enum('.var_export(array_values($schema['enum']), true).')';
        }

        if ($type === 'string') {
            if (isset($schema['minLength']) && is_int($schema['minLength'])) {
                $expression .= '->min('.$schema['minLength'].')';
            }

            if (isset($schema['maxLength']) && is_int($schema['maxLength'])) {
                $expression .= '->max('.$schema['maxLength'].')';
            }

            if (isset($schema['pattern']) && is_string($schema['pattern'])) {
                $expression .= '->pattern('.var_export($schema['pattern'], true).')';
            }

            if (isset($schema['format']) && is_string($schema['format'])) {
                $expression .= '->format('.var_export($schema['format'], true).')';
            }
        }

        if ($type === 'integer' || $type === 'number') {
            if (isset($schema['minimum']) && (is_int($schema['minimum']) || is_float($schema['minimum']))) {
                $expression .= '->min('.$schema['minimum'].')';
            }

            if (isset($schema['maximum']) && (is_int($schema['maximum']) || is_float($schema['maximum']))) {
                $expression .= '->max('.$schema['maximum'].')';
            }
        }

        if ($type === 'array') {
            if (isset($schema['minItems']) && is_int($schema['minItems'])) {
                $expression .= '->min('.$schema['minItems'].')';
            }

            if (isset($schema['maxItems']) && is_int($schema['maxItems'])) {
                $expression .= '->max('.$schema['maxItems'].')';
            }
        }

        if ($this->isNullable($schema)) {
            $expression .= '->nullable()';
        }

        return $expression;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function primaryType(array $schema): string
    {
        $type = $schema['type'] ?? 'string';

        if (is_string($type)) {
            return $type;
        }

        if (! is_array($type)) {
            return 'string';
        }

        foreach ($type as $candidate) {
            if ($candidate !== 'null' && is_string($candidate)) {
                return $candidate;
            }
        }

        return 'string';
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function isNullable(array $schema): bool
    {
        $type = $schema['type'] ?? null;

        if (is_array($type) && in_array('null', $type, true)) {
            return true;
        }

        return ($schema['nullable'] ?? false) === true;
    }
}
