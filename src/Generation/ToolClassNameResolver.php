<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Generation;

use Illuminate\Support\Str;
use RuntimeException;

final class ToolClassNameResolver
{
    /**
     * @param  array<string, true>  $usedClassNames
     */
    public function resolve(
        string $serverSlug,
        string $toolName,
        array &$usedClassNames,
        bool $allowCollisionSuffix = true,
    ): string {
        $baseName = $this->normalize($serverSlug).$this->normalize($toolName).'Tool';

        if (! isset($usedClassNames[$baseName])) {
            $usedClassNames[$baseName] = true;

            return $baseName;
        }

        if (! $allowCollisionSuffix) {
            throw new RuntimeException(
                'Tool class name collision detected for ['.$serverSlug.'.'.$toolName.'] -> ['.$baseName.']'
            );
        }

        $hashedName = $baseName.substr(md5($serverSlug.'|'.$toolName), 0, 8);

        if (! isset($usedClassNames[$hashedName])) {
            $usedClassNames[$hashedName] = true;

            return $hashedName;
        }

        $counter = 2;

        do {
            $candidate = $hashedName.$counter;
            $counter++;
        } while (isset($usedClassNames[$candidate]));

        $usedClassNames[$candidate] = true;

        return $candidate;
    }

    private function normalize(string $value): string
    {
        $studly = Str::studly(preg_replace('/[^a-zA-Z0-9]+/', ' ', $value) ?? '');

        return $studly === '' ? 'Mcp' : $studly;
    }
}
