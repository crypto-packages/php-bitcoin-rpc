<?php

declare(strict_types=1);

namespace CryptoPackages\BitcoinRpc\Tests;

use CryptoPackages\BitcoinRpc\BitcoinRpcClient;
use CryptoPackages\BitcoinRpc\Exceptions\RpcException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\TestCase;

class BitcoinRpcClientTest extends TestCase
{
    public function testHttp200WithSuccessResult(): void
    {
        $client = $this->clientWithResponses([
            new GuzzleResponse(200, [], json_encode([
                'result' => 123456,
                'error'  => null,
                'id'     => 0,
            ])),
        ]);

        $this->assertSame(123456, $client->getblockcount()->get());
    }

    public function testHttp200WithJsonRpcError(): void
    {
        $client = $this->clientWithResponses([
            new GuzzleResponse(200, [], json_encode([
                'result' => null,
                'error'  => [
                    'code'    => -25,
                    'message' => 'Missing inputs',
                ],
                'id' => 0,
            ])),
        ]);

        try {
            $client->sendrawtransaction('010203');
            $this->fail('Expected RpcException');
        } catch (RpcException $e) {
            $this->assertSame('Missing inputs', $e->getMessage());
            $this->assertSame(-25, $e->getCode());
            $this->assertSame(-25, $e->getRpcError()['code']);
        }
    }

    public function testHttp500WithJsonRpcErrorUsesNodeMessage(): void
    {
        $client = $this->clientWithResponses([
            new GuzzleResponse(500, [], json_encode([
                'result' => null,
                'error'  => [
                    'code'    => -26,
                    'message' => 'min relay fee not met',
                ],
                'id' => 0,
            ])),
        ]);

        try {
            $client->sendrawtransaction('deadbeef');
            $this->fail('Expected RpcException');
        } catch (RpcException $e) {
            $this->assertSame('min relay fee not met', $e->getMessage());
            $this->assertSame(-26, $e->getCode());
            $this->assertStringNotContainsString('500 Internal Server Error', $e->getMessage());
        }
    }

    public function testHttp500WithNonJsonBodyThrowsExplicitFailure(): void
    {
        $client = $this->clientWithResponses([
            new GuzzleResponse(500, [], '<html>proxy error</html>'),
        ]);

        try {
            $client->getblockcount();
            $this->fail('Expected RpcException');
        } catch (RpcException $e) {
            $this->assertStringContainsString('HTTP 500', $e->getMessage());
            $this->assertStringContainsString('proxy error', $e->getMessage());
            $this->assertSame(500, $e->getCode());
        }
    }

    public function testHttp401IsAuthFailure(): void
    {
        $client = $this->clientWithResponses([
            new GuzzleResponse(401, [], 'Unauthorized'),
        ]);

        try {
            $client->getblockcount();
            $this->fail('Expected RpcException');
        } catch (RpcException $e) {
            $this->assertStringContainsString('authentication failed', $e->getMessage());
            $this->assertSame(401, $e->getCode());
        }
    }

    public function testWalletHelpersUnchanged(): void
    {
        $client = $this->clientWithResponses([]);

        $bound = $client->setWallet('main');
        $this->assertSame('/', $client->getPath());
        $this->assertSame('/wallet/main', $bound->getPath());

        $client->wallet(null);
        $this->assertSame('/', $client->getPath());
        $client->wallet('x');
        $this->assertSame('/wallet/x', $client->getPath());
    }

    /**
     * @param GuzzleResponse[] $responses
     */
    private function clientWithResponses(array $responses): BitcoinRpcClient
    {
        $mock = new MockHandler($responses);
        $http = new GuzzleClient([
            'handler'     => HandlerStack::create($mock),
            'http_errors' => false,
        ]);

        return new BitcoinRpcClient([
            'url'     => 'http://127.0.0.1:8332/',
            'auth'    => ['u', 'p'],
            'timeout' => 1,
        ], $http);
    }
}
