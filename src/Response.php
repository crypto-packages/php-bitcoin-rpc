<?php

declare(strict_types=1);

namespace CryptoPackages\BitcoinRpc;

class Response
{
    /** @var mixed */
    private $result;

    /**
     * @param mixed $result JSON-RPC "result" value
     */
    public function __construct($result)
    {
        $this->result = $result;
    }

    /**
     * @return mixed
     */
    public function get()
    {
        return $this->result;
    }
}
