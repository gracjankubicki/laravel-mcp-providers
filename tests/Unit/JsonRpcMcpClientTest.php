<?php

declare(strict_types=1);

namespace Tests\Unit;

use Laravel\McpProviders\Clients\JsonRpcMcpClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JsonRpcMcpClientTest extends TestCase
{
    public function test_list_tools_returns_tools_from_result_payload(): void
    {
        $client = new JsonRpcMcpClient;
        $endpoint = $this->endpointForJson([
            'jsonrpc' => '2.0',
            'result' => [
                'tools' => [
                    ['name' => 'a'],
                    ['name' => 'b'],
                ],
            ],
        ]);

        $tools = $client->listTools($endpoint);

        $this->assertCount(2, $tools);
        $this->assertSame('a', $tools[0]['name']);
    }

    public function test_list_tools_accepts_custom_headers(): void
    {
        $client = new JsonRpcMcpClient;
        $endpoint = $this->endpointForJson([
            'jsonrpc' => '2.0',
            'result' => ['tools' => []],
        ]);

        $tools = $client->listTools($endpoint, headers: ['X-Api-Key' => 'secret']);

        $this->assertSame([], $tools);
    }

    public function test_call_tool_returns_content_when_available(): void
    {
        $client = new JsonRpcMcpClient;
        $endpoint = $this->endpointForJson([
            'jsonrpc' => '2.0',
            'result' => [
                'content' => 'ok',
            ],
        ]);

        $result = $client->callTool($endpoint, 'search_docs', ['query' => 'abc']);

        $this->assertSame('ok', $result);
    }

    public function test_it_throws_when_endpoint_cannot_be_reached(): void
    {
        $client = new JsonRpcMcpClient;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to call MCP endpoint');

        $client->listTools('http://127.0.0.1:1', timeout: 1);
    }

    public function test_it_throws_when_response_has_error_object(): void
    {
        $client = new JsonRpcMcpClient;
        $endpoint = $this->endpointForJson([
            'jsonrpc' => '2.0',
            'error' => ['message' => 'boom'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MCP error for [tools/list]: boom');

        $client->listTools($endpoint);
    }

    public function test_it_uses_unknown_message_for_invalid_error_shape(): void
    {
        $client = new JsonRpcMcpClient;
        $endpoint = $this->endpointForJson([
            'jsonrpc' => '2.0',
            'error' => 'invalid-shape',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MCP error for [tools/list]: Unknown MCP error');

        $client->listTools($endpoint);
    }

    public function test_it_throws_when_result_is_missing_or_invalid(): void
    {
        $client = new JsonRpcMcpClient;

        $missingResult = $this->endpointForJson(['jsonrpc' => '2.0']);
        $invalidResult = $this->endpointForJson(['jsonrpc' => '2.0', 'result' => 'invalid']);

        try {
            $client->listTools($missingResult);
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Missing or invalid result', $e->getMessage());
            $this->assertStringContainsString('tools/list', $e->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing or invalid result');

        $client->listTools($invalidResult);
    }

    public function test_it_throws_when_tools_payload_is_invalid(): void
    {
        $client = new JsonRpcMcpClient;
        $endpoint = $this->endpointForJson([
            'jsonrpc' => '2.0',
            'result' => [
                'tools' => 'invalid',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid MCP response for tools/list.');

        $client->listTools($endpoint);
    }

    public function test_it_throws_for_non_object_json_rpc_payload(): void
    {
        $client = new JsonRpcMcpClient;
        $endpoint = $this->endpointForRaw('"hello"');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expected object, got string');

        $client->listTools($endpoint);
    }

    public function test_it_throws_with_body_preview_for_invalid_json(): void
    {
        $client = new JsonRpcMcpClient;
        $endpoint = $this->endpointForRaw('<html>Not Found</html>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('<html>Not Found</html>');

        $client->listTools($endpoint);
    }

    public function test_it_truncates_long_response_body_in_error_message(): void
    {
        $client = new JsonRpcMcpClient;
        $longBody = str_repeat('x', 300);
        $endpoint = $this->endpointForRaw($longBody);

        try {
            $client->listTools($endpoint);
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('... (truncated)', $e->getMessage());
        }
    }

    public function test_missing_result_error_includes_endpoint_and_body(): void
    {
        $client = new JsonRpcMcpClient;
        $endpoint = $this->endpointForJson(['jsonrpc' => '2.0']);

        try {
            $client->listTools($endpoint);
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('data://', $e->getMessage());
            $this->assertStringContainsString('"jsonrpc"', $e->getMessage());
        }
    }

    public function test_it_retries_transport_errors_and_succeeds(): void
    {
        RetryMockStream::setScenario('recover', [
            false,
            json_encode([
                'jsonrpc' => '2.0',
                'result' => ['tools' => [['name' => 'search_docs']]],
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $client = new JsonRpcMcpClient;
        $tools = $client->listTools(
            'retrymock://recover',
            timeout: 1,
            retryAttempts: 2,
            retryBackoffMs: 0,
            retryMaxBackoffMs: 0,
        );

        $this->assertCount(1, $tools);
        $this->assertSame(2, RetryMockStream::calls('recover'));
    }

    public function test_it_applies_backoff_when_retrying_transport_errors(): void
    {
        RetryMockStream::setScenario('recover-with-backoff', [
            false,
            json_encode([
                'jsonrpc' => '2.0',
                'result' => ['tools' => [['name' => 'search_docs']]],
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $client = new JsonRpcMcpClient;
        $tools = $client->listTools(
            'retrymock://recover-with-backoff',
            timeout: 1,
            retryAttempts: 2,
            retryBackoffMs: 1,
            retryMaxBackoffMs: 10,
        );

        $this->assertCount(1, $tools);
        $this->assertSame(2, RetryMockStream::calls('recover-with-backoff'));
    }

    public function test_it_stops_after_retry_limit_on_transport_errors(): void
    {
        RetryMockStream::setScenario('fail', [false, false]);

        $client = new JsonRpcMcpClient;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to call MCP endpoint');

        try {
            $client->listTools(
                'retrymock://fail',
                timeout: 1,
                retryAttempts: 2,
                retryBackoffMs: 0,
                retryMaxBackoffMs: 0,
            );
        } finally {
            $this->assertSame(2, RetryMockStream::calls('fail'));
        }
    }

    public function test_parse_http_status_code_extracts_code_from_headers(): void
    {
        $client = new JsonRpcMcpClient;
        $method = new \ReflectionMethod($client, 'parseHttpStatusCode');

        $this->assertSame(200, $method->invoke($client, ['HTTP/1.1 200 OK']));
        $this->assertSame(404, $method->invoke($client, ['HTTP/1.1 404 Not Found']));
        $this->assertNull($method->invoke($client, ['InvalidHeader']));
        $this->assertNull($method->invoke($client, null));
        $this->assertNull($method->invoke($client, []));
    }

    private function endpointForJson(array $payload): string
    {
        return 'data://text/plain,'.rawurlencode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    private function endpointForRaw(string $payload): string
    {
        return 'data://text/plain,'.rawurlencode($payload);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('retrymock', stream_get_wrappers(), true)) {
            stream_wrapper_register('retrymock', RetryMockStream::class);
        }
    }
}

final class RetryMockStream
{
    public mixed $context;

    /**
     * @var array<string, list<string|false>>
     */
    private static array $scenarios = [];

    /**
     * @var array<string, int>
     */
    private static array $callCount = [];

    private string $payload = '';

    private int $position = 0;

    /**
     * @param  list<string|false>  $responses
     */
    public static function setScenario(string $name, array $responses): void
    {
        self::$scenarios[$name] = $responses;
        self::$callCount[$name] = 0;
    }

    public static function calls(string $name): int
    {
        return self::$callCount[$name] ?? 0;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $name = (string) parse_url($path, PHP_URL_HOST);
        self::$callCount[$name] = (self::$callCount[$name] ?? 0) + 1;
        $next = array_shift(self::$scenarios[$name]);

        if ($next === false) {
            return false;
        }

        $this->payload = (string) $next;
        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr($this->payload, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function stream_stat(): array
    {
        return [];
    }
}
