<?php

declare(strict_types=1);

namespace Laravel\McpProviders;

final class ToolManifestNormalizer
{
    /**
     * @param  list<array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function normalize(
        string $serverSlug,
        ?string $endpointEnv,
        array $tools,
        string $generatedAt,
    ): array {
        $normalizedTools = array_map(function (array $tool): array {
            $name = isset($tool['name']) && is_string($tool['name']) ? $tool['name'] : '';

            return [
                'name' => $name,
                'title' => isset($tool['title']) && is_string($tool['title']) ? $tool['title'] : null,
                'description' => isset($tool['description']) && is_string($tool['description'])
                    ? $tool['description']
                    : $name,
                'input_schema' => $this->normalizeSchema(
                    isset($tool['input_schema']) && is_array($tool['input_schema']) ? $tool['input_schema'] : []
                ),
            ];
        }, $tools);

        usort($normalizedTools, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return [
            'version' => 1,
            'server' => array_filter([
                'slug' => $serverSlug,
                'endpoint_env' => $endpointEnv,
            ], static fn (mixed $value): bool => ! is_null($value)),
            'generated_at' => $generatedAt,
            'tools' => $normalizedTools,
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function normalizeSchema(array $schema): array
    {
        return $this->sortRecursive($schema);
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $value
     * @return array<string, mixed>|list<mixed>
     */
    private function sortRecursive(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursive($item);
            }
        }

        return $value;
    }
}
