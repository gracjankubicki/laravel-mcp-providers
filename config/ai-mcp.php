<?php

declare(strict_types=1);

return [
    'servers' => [
        // 'gdocs' => [
        //     'endpoint' => env('MCP_GDOCS_URL'),
        //     'auth' => [
        //         'strategy' => \Laravel\McpProviders\Auth\BearerAuthStrategy::class,
        //         'token' => env('MCP_GDOCS_TOKEN'),
        //     ],
        //     'timeout' => 10,
        //     'retry' => [
        //         'attempts' => 3,
        //         'backoff_ms' => 150,
        //         'max_backoff_ms' => 1000,
        //     ],
        //     'manifest' => resource_path('mcp/gdocs.tools.json'),
        // ],
    ],

    'retry' => [
        'attempts' => 1,
        'backoff_ms' => 100,
        'max_backoff_ms' => 1000,
    ],

    'generated' => [
        'path' => app_path('Ai/Tools/Generated'),
        'namespace' => 'App\\Ai\\Tools\\Generated',
    ],
];
