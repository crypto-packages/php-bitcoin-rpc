<?php

declare(strict_types=1);

namespace CryptoPackages\BitcoinRpc;

use CryptoPackages\BitcoinRpc\Exceptions\RpcException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;

class BitcoinRpcClient
{
    /** @var ClientInterface */
    private $http;

    /** @var array */
    private $config;

    /** @var string */
    private $path = '/';

    /** @var int */
    private $rpcId = 0;

    /**
     * @param array{
     *     url?: string,
     *     auth?: array{0?: string, 1?: string},
     *     timeout?: float|int|null,
     *     headers?: array<string, string>
     * } $config
     * @param ClientInterface|null $http Optional Guzzle client (tests / injection)
     */
    public function __construct(array $config, ?ClientInterface $http = null)
    {
        $this->config = $this->normalizeConfig($config);
        $this->http = $http ?? $this->createHttpClient($this->config);
    }

    /**
     * Return a new client bound to a wallet (immutable).
     * null or '' means no wallet path.
     */
    public function setWallet(?string $name): self
    {
        $clone = clone $this;
        $clone->applyWallet($name);

        return $clone;
    }

    /**
     * Set wallet path on this instance (mutates for call-chain use).
     * null or '' clears the wallet path.
     */
    public function wallet(?string $name): self
    {
        $this->applyWallet($name);

        return $this;
    }

    /**
     * @return mixed
     */
    public function __call(string $method, array $params)
    {
        return $this->request($method, $params);
    }

    /**
     * @param array $params
     */
    public function request(string $method, array $params = []): Response
    {
        $response = $this->http->post($this->path, [
            'json' => [
                'jsonrpc' => '1.0',
                'id'      => $this->rpcId++,
                'method'  => strtolower($method),
                'params'  => array_values($params),
            ],
        ]);

        $raw = (string) $response->getBody();
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new RpcException('Invalid JSON-RPC response', 0, null);
        }

        if (array_key_exists('error', $decoded) && $decoded['error'] !== null) {
            $error = is_array($decoded['error']) ? $decoded['error'] : ['message' => (string) $decoded['error']];
            $message = isset($error['message']) ? (string) $error['message'] : 'JSON-RPC error';
            $code = isset($error['code']) ? (int) $error['code'] : 0;

            throw new RpcException($message, $code, $error);
        }

        return new Response($decoded['result'] ?? null);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    private function applyWallet(?string $name): void
    {
        if ($name === null || $name === '') {
            $this->path = '/';

            return;
        }

        $this->path = '/wallet/' . $name;
    }

    /**
     * @param array $config
     * @return array
     */
    private function normalizeConfig(array $config): array
    {
        if (!isset($config['url']) || !is_string($config['url']) || $config['url'] === '') {
            throw new InvalidArgumentException('Config key "url" is required and must be a non-empty string.');
        }

        $auth = $config['auth'] ?? null;
        if ($auth !== null) {
            if (!is_array($auth) || count($auth) < 2) {
                throw new InvalidArgumentException('Config key "auth" must be ["username", "password"].');
            }
        }

        return [
            'url'     => $config['url'],
            'auth'    => $auth,
            'timeout' => $config['timeout'] ?? 0,
            'headers' => $config['headers'] ?? [],
        ];
    }

    /**
     * @param array $config
     */
    private function createHttpClient(array $config): ClientInterface
    {
        $options = [
            'base_uri' => $config['url'],
            'headers'  => $config['headers'],
            'http_errors' => true,
        ];

        if ($config['auth'] !== null) {
            $options['auth'] = [$config['auth'][0], $config['auth'][1]];
        }

        $timeout = $config['timeout'];
        if ($timeout !== null && $timeout !== false && (float) $timeout > 0) {
            $options['timeout'] = (float) $timeout;
            $options['connect_timeout'] = (float) $timeout;
        }

        return new GuzzleClient($options);
    }
}
