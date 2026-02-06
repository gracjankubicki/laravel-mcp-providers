<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Generation;

final class GeneratedToolsetRenderer
{
    /**
     * @param  list<class-string>  $toolClasses
     */
    public function render(
        string $namespace,
        string $className,
        array $toolClasses,
    ): string {
        $classesExport = implode(
            ",\n",
            array_map(
                static fn (string $className): string => '            \\'.ltrim($className, '\\').'::class',
                $toolClasses
            )
        );

        $classesBody = $classesExport === ''
            ? 'return [];'
            : "return [\n".$classesExport.",\n        ];";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\Contracts\Container\Container;

final class {$className}
{
    public function __construct(private readonly Container \$container) {}

    /**
     * @return list<class-string>
     */
    public static function classes(): array
    {
        {$classesBody}
    }

    /**
     * @return list<object>
     */
    public function all(): array
    {
        return array_map(fn (string \$className): object => \$this->container->make(\$className), self::classes());
    }

    /**
     * @param  list<class-string>  \$toolClasses
     * @return list<object>
     */
    public function onlyClasses(array \$toolClasses): array
    {
        \$allowed = array_fill_keys(\$toolClasses, true);

        return array_values(array_filter(
            \$this->all(),
            static fn (object \$tool): bool => isset(\$allowed[\$tool::class]),
        ));
    }

    /**
     * @param  list<class-string>  \$toolClasses
     * @return list<object>
     */
    public function exceptClasses(array \$toolClasses): array
    {
        \$excluded = array_fill_keys(\$toolClasses, true);

        return array_values(array_filter(
            \$this->all(),
            static fn (object \$tool): bool => ! isset(\$excluded[\$tool::class]),
        ));
    }
}

PHP;
    }
}
