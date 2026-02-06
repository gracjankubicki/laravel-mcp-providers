<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Clients;

use Illuminate\Support\Str;
use Laravel\McpProviders\Contracts\McpClient;
use Laravel\McpProviders\Exceptions\McpException;
use Throwable;

final class JsonRpcMcpClient implements McpClient
{
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
        $result = $this->requestWithRetry(
            endpoint: $endpoint,
            method: 'tools/list',
            params: [],
            headers: $headers,
            timeout: $timeout,
            retryAttempts: $retryAttempts,
            retryBackoffMs: $retryBackoffMs,
            retryMaxBackoffMs: $retryMaxBackoffMs,
        );

        $tools = $result['tools'] ?? [];

        if (! is_array($tools)) {
            throw McpException::invalidResponse('Invalid MCP response for tools/list.');
        }

        return array_values(array_filter($tools, is_array(...)));
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
        $result = $this->requestWithRetry(
            endpoint: $endpoint,
            method: 'tools/call',
            params: [
                'name' => $toolName,
                'arguments' => $arguments,
            ],
            headers: $headers,
            timeout: $timeout,
            retryAttempts: $retryAttempts,
            retryBackoffMs: $retryBackoffMs,
            retryMaxBackoffMs: $retryMaxBackoffMs,
        );

        return $result['content'] ?? $result;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function requestWithRetry(
        string $endpoint,
        string $method,
        array $params,
        array $headers,
        int $timeout,
        int $retryAttempts,
        int $retryBackoffMs,
        int $retryMaxBackoffMs,
    ): array {
        $attempts = max(1, $retryAttempts);
        $backoffMs = max(0, $retryBackoffMs);
        $maxBackoffMs = max(0, $retryMaxBackoffMs);
        $attempt = 0;
        $delayMs = $backoffMs;

        while (true) {
            $attempt++;

            try {
                return $this->request($endpoint, $method, $params, $headers, $timeout);
            } catch (Throwable $e) {
                $isRetryable = $e instanceof McpException && $e->isTransient();
                if (! $isRetryable || $attempt >= $attempts) {
                    throw $e;
                }
            }

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }

            if ($maxBackoffMs > 0) {
                $delayMs = min($delayMs * 2, $maxBackoffMs);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function request(
        string $endpoint,
        string $method,
        array $params,
        array $headers,
        int $timeout,
    ): array {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => (string) Str::uuid(),
            'method' => $method,
            'params' => $params,
        ];

        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $requestHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        foreach ($headers as $name => $value) {
            $requestHeaders[] = $name.': '.$value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $requestHeaders),
                'content' => $encodedPayload,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = $this->readEndpoint($endpoint, $context);

        if ($responseBody === false) {
            throw McpException::transport($endpoint);
        }

        $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw McpException::invalidResponse('Invalid JSON-RPC response from MCP endpoint: '.$endpoint);
        }

        if (isset($decoded['error'])) {
            $message = is_array($decoded['error']) && isset($decoded['error']['message'])
                ? (string) $decoded['error']['message']
                : 'Unknown MCP error';

            throw McpException::rpc($method, $message);
        }

        if (! array_key_exists('result', $decoded) || ! is_array($decoded['result'])) {
            throw McpException::invalidResponse(
                'Missing or invalid result in JSON-RPC response for ['.$method.'].'
            );
        }

        return $decoded['result'];
    }

    private function readEndpoint(string $endpoint, mixed $context): string|false
    {
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            return file_get_contents($endpoint, false, $context);
        } finally {
            restore_error_handler();
        }
    }
}
