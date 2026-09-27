<?php

declare(strict_types=1);

namespace CryptoPackages\BitcoinRpc\Exceptions;

use Exception;

/**
 * Thrown when Bitcoin Core returns a JSON-RPC error object.
 */
class RpcException extends Exception
{
    /** @var array|null */
    private $rpcError;

    /**
     * @param array|null $rpcError Decoded JSON-RPC "error" object
     */
    public function __construct(string $message, int $code = 0, ?array $rpcError = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->rpcError = $rpcError;
    }

    public function getRpcError(): ?array
    {
        return $this->rpcError;
    }
}
