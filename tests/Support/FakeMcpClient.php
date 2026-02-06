<?php

declare(strict_types=1);

namespace Tests\Support;

use Laravel\McpProviders\Contracts\McpClient;
use RuntimeException;

final class FakeMcpClient implements McpClient
{
    /**
     * @var array<string, list<array<string, mixed>>>
     */
    public array $toolsByEndpoint = [];

    /**
     * @var array<string, mixed>
     */
    public array $callResultsByKey = [];

    /**
     * @var array<string, RuntimeException>
     */
    public array $errorsByEndpoint = [];

    /**
     * @var list<array{
     *   endpoint: string,
     *   headers: array<string, string>,
     *   timeout: int,
     *   retry_attempts: int,
     *   retry_backoff_ms: int,
     *   retry_max_backoff_ms: int
     * }>
     */
    public array $listCalls = [];

    /**
     * @var list<array{
     *   endpoint: string,
     *   tool_name: string,
     *   arguments: array<string, mixed>,
     *   headers: array<string, string>,
     *   timeout: int,
     *   retry_attempts: int,
     *   retry_backoff_ms: int,
     *   retry_max_backoff_ms: int
     * }>
     */
    public array $toolCalls = [];

    /**
     * @param  array<string, string>  $headers
     * @return list<array<string, mixed>>
     */
    public function listTools(
        string $endpoint,
        array $headers = [],
        int $timeout = 60,
        int $retryAttempts = 1,
        int $retryBackoffMs = 100,
        int $retryMaxBackoffMs = 1000,
    ): array {
        $this->listCalls[] = [
            'endpoint' => $endpoint,
            'headers' => $headers,
            'timeout' => $timeout,
            'retry_attempts' => $retryAttempts,
            'retry_backoff_ms' => $retryBackoffMs,
            'retry_max_backoff_ms' => $retryMaxBackoffMs,
        ];

        if (isset($this->errorsByEndpoint[$endpoint])) {
            throw $this->errorsByEndpoint[$endpoint];
        }

        return $this->toolsByEndpoint[$endpoint] ?? [];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, string>  $headers
     */
    public function callTool(
        string $endpoint,
        string $toolName,
        array $arguments,
        array $headers = [],
        int $timeout = 60,
        int $retryAttempts = 1,
        int $retryBackoffMs = 100,
        int $retryMaxBackoffMs = 1000,
    ): mixed {
        $this->toolCalls[] = [
            'endpoint' => $endpoint,
            'tool_name' => $toolName,
            'arguments' => $arguments,
            'headers' => $headers,
            'timeout' => $timeout,
            'retry_attempts' => $retryAttempts,
            'retry_backoff_ms' => $retryBackoffMs,
            'retry_max_backoff_ms' => $retryMaxBackoffMs,
        ];

        $key = $endpoint.'|'.$toolName;

        return $this->callResultsByKey[$key] ?? null;
    }
}
