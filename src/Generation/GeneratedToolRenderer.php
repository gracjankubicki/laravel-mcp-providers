<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Generation;

final class GeneratedToolRenderer
{
    public function __construct(private readonly SchemaCodeGenerator $schemaCodeGenerator) {}

    /**
     * @param  array<string, mixed>  $inputSchema
     */
    public function render(
        string $namespace,
        string $className,
        string $serverSlug,
        string $toolName,
        string $description,
        array $inputSchema,
    ): string {
        $schemaCode = $this->schemaCodeGenerator->generate($inputSchema);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\McpProviders\Tools\AbstractMcpTool;

final class {$className} extends AbstractMcpTool
{
    public function serverSlug(): string
    {
        return {$this->export($serverSlug)};
    }

    public function rawToolName(): string
    {
        return {$this->export($toolName)};
    }

    public function description(): string
    {
        return {$this->export($description)};
    }

    public function schema(JsonSchema \$schema): array
    {
        return {$schemaCode};
    }
}

PHP;
    }

    private function export(string $value): string
    {
        return var_export($value, true);
    }
}
