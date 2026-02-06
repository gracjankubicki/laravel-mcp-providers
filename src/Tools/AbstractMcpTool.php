<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Tools;

use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\McpProviders\Contracts\McpInvocationRouter;
use Stringable;

abstract class AbstractMcpTool implements Tool
{
    public function __construct(private readonly McpInvocationRouter $router) {}

    /**
     * Runtime MCP name. Used by laravel/ai when dynamic tool names are supported.
     */
    public function name(): string
    {
        return $this->serverSlug().'.'.$this->rawToolName();
    }

    abstract public function serverSlug(): string;

    abstract public function rawToolName(): string;

    public function handle(Request $request): Stringable|string
    {
        $result = $this->router->invoke(
            $this->serverSlug(),
            $this->rawToolName(),
            $request->toArray(),
        );

        if ($result instanceof Stringable || is_string($result)) {
            return $result;
        }

        return json_encode(
            $result,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        ) ?: '';
    }
}
