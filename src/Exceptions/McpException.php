<?php

declare(strict_types=1);

namespace Laravel\McpProviders\Exceptions;

use RuntimeException;

final class McpException extends RuntimeException
{
    private const REASON_TRANSPORT = 'transport';

    private const REASON_INVALID_RESPONSE = 'invalid_response';

    private const REASON_RPC = 'rpc';

    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function transport(string $endpoint): self
    {
        return new self('Failed to call MCP endpoint: '.$endpoint, self::REASON_TRANSPORT);
    }

    public static function invalidResponse(string $message): self
    {
        return new self($message, self::REASON_INVALID_RESPONSE);
    }

    public static function rpc(string $method, string $message): self
    {
        return new self('MCP error for ['.$method.']: '.$message, self::REASON_RPC);
    }

    public function isTransient(): bool
    {
        return $this->reason === self::REASON_TRANSPORT;
    }
}
