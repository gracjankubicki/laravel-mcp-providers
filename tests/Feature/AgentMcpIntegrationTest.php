<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\McpProviders\Auth\BearerAuthStrategy;
use Laravel\McpProviders\Auth\HeaderAuthStrategy;
use Laravel\McpProviders\Contracts\McpClient;
use Laravel\McpProviders\Tools\AbstractMcpTool;
use Prism\Prism\PrismServiceProvider;
use Tests\Support\FakeMcpClient;
use Tests\TestCase;

final class AgentMcpIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            AiServiceProvider::class,
            PrismServiceProvider::class,
        ]);
    }

    public function test_agent_invokes_two_mcp_tools_from_two_servers_end_to_end(): void
    {
        $this->app['config']->set('ai.default', 'openai');
        $this->app['config']->set('ai.providers.openai', [
            'driver' => 'openai',
            'key' => 'test-key',
        ]);

        $this->app['config']->set('mcp-providers.servers', [
            'gdocs' => [
                'endpoint' => 'http://mcp.test/gdocs',
                'timeout' => 11,
                'auth' => [
                    'strategy' => BearerAuthStrategy::class,
                    'token' => 'gdocs-secret',
                ],
                'retry' => [
                    'attempts' => 2,
                    'backoff_ms' => 150,
                    'max_backoff_ms' => 450,
                ],
            ],
            'n8n' => [
                'endpoint' => 'http://mcp.test/n8n',
                'timeout' => 17,
                'auth' => [
                    'strategy' => HeaderAuthStrategy::class,
                    'header' => 'X-API-Key',
                    'value' => 'n8n-key',
                ],
                'retry' => [
                    'attempts' => 4,
                    'backoff_ms' => 200,
                    'max_backoff_ms' => 800,
                ],
            ],
        ]);

        $mcpClient = new FakeMcpClient;
        $mcpClient->callResultsByKey['http://mcp.test/gdocs|search_docs'] = ['docs' => ['Laravel']];
        $mcpClient->callResultsByKey['http://mcp.test/n8n|run_workflow'] = 'workflow started';
        $this->app->instance(McpClient::class, $mcpClient);

        $responses = [
            $this->openAiToolCallResponse(),
            $this->openAiFinalResponse(),
        ];

        $capturedPayloads = [];
        $requestIndex = 0;

        Http::fake(function (HttpRequest $request) use (&$capturedPayloads, &$requestIndex, $responses) {
            $capturedPayloads[] = json_decode($request->body(), true);

            return Http::response($responses[$requestIndex++] ?? end($responses), 200);
        });

        $agent = new IntegrationMcpAgent([
            $this->app->make(GdocsSearchDocsTool::class),
            $this->app->make(N8nRunWorkflowTool::class),
        ]);

        $response = $agent->prompt('Find docs and run workflow');

        $this->assertSame('Done: docs found and workflow started.', $response->text);
        Http::assertSentCount(2);

        $this->assertCount(2, $mcpClient->toolCalls);
        $this->assertSame('http://mcp.test/gdocs', $mcpClient->toolCalls[0]['endpoint']);
        $this->assertSame('search_docs', $mcpClient->toolCalls[0]['tool_name']);
        $this->assertSame(['query' => 'laravel'], $mcpClient->toolCalls[0]['arguments']);
        $this->assertSame('Bearer gdocs-secret', $mcpClient->toolCalls[0]['headers']['Authorization']);
        $this->assertSame(11, $mcpClient->toolCalls[0]['timeout']);
        $this->assertSame(2, $mcpClient->toolCalls[0]['retry_attempts']);

        $this->assertSame('http://mcp.test/n8n', $mcpClient->toolCalls[1]['endpoint']);
        $this->assertSame('run_workflow', $mcpClient->toolCalls[1]['tool_name']);
        $this->assertSame(['workflow_id' => 'wf-123'], $mcpClient->toolCalls[1]['arguments']);
        $this->assertSame('n8n-key', $mcpClient->toolCalls[1]['headers']['X-API-Key']);
        $this->assertSame(17, $mcpClient->toolCalls[1]['timeout']);
        $this->assertSame(4, $mcpClient->toolCalls[1]['retry_attempts']);

        $this->assertCount(2, $capturedPayloads);
        $this->assertIsArray($capturedPayloads[1]['input']);

        $toolOutputs = array_values(array_filter(
            $capturedPayloads[1]['input'],
            static fn (mixed $message): bool => is_array($message)
                && ($message['type'] ?? null) === 'function_call_output'
        ));

        $this->assertCount(2, $toolOutputs);
        $this->assertSame('call_1', $toolOutputs[0]['call_id']);
        $this->assertSame('{"docs":["Laravel"]}', $toolOutputs[0]['output']);
        $this->assertSame('call_2', $toolOutputs[1]['call_id']);
        $this->assertSame('workflow started', $toolOutputs[1]['output']);
    }

    /**
     * @return array<string, mixed>
     */
    private function openAiToolCallResponse(): array
    {
        return [
            'id' => 'resp_1',
            'model' => 'gpt-5.2',
            'output' => [
                [
                    'id' => 'fc_1',
                    'type' => 'function_call',
                    'status' => 'completed',
                    'name' => 'GdocsSearchDocsTool',
                    'arguments' => '{"schema_definition":{"query":"laravel"}}',
                    'call_id' => 'call_1',
                ],
                [
                    'id' => 'fc_2',
                    'type' => 'function_call',
                    'status' => 'completed',
                    'name' => 'N8nRunWorkflowTool',
                    'arguments' => '{"schema_definition":{"workflow_id":"wf-123"}}',
                    'call_id' => 'call_2',
                ],
            ],
            'usage' => [
                'input_tokens' => 10,
                'input_tokens_details' => ['cached_tokens' => 0],
                'output_tokens' => 8,
                'output_tokens_details' => ['reasoning_tokens' => 0],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function openAiFinalResponse(): array
    {
        return [
            'id' => 'resp_2',
            'model' => 'gpt-5.2',
            'output' => [
                [
                    'id' => 'msg_1',
                    'type' => 'message',
                    'status' => 'completed',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Done: docs found and workflow started.',
                        ],
                    ],
                ],
            ],
            'usage' => [
                'input_tokens' => 14,
                'input_tokens_details' => ['cached_tokens' => 0],
                'output_tokens' => 6,
                'output_tokens_details' => ['reasoning_tokens' => 0],
            ],
        ];
    }
}

final class IntegrationMcpAgent implements Agent, HasTools
{
    use Promptable;

    /**
     * @param  list<object>  $tools
     */
    public function __construct(private readonly array $tools) {}

    public function instructions(): string
    {
        return 'Use tools when needed.';
    }

    public function tools(): iterable
    {
        return $this->tools;
    }
}

final class GdocsSearchDocsTool extends AbstractMcpTool
{
    public function serverSlug(): string
    {
        return 'gdocs';
    }

    public function rawToolName(): string
    {
        return 'search_docs';
    }

    public function description(): string
    {
        return 'Search docs';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
        ];
    }
}

final class N8nRunWorkflowTool extends AbstractMcpTool
{
    public function serverSlug(): string
    {
        return 'n8n';
    }

    public function rawToolName(): string
    {
        return 'run_workflow';
    }

    public function description(): string
    {
        return 'Run workflow';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'workflow_id' => $schema->string()->required(),
        ];
    }
}
