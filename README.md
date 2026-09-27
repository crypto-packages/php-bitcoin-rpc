# crypto-packages/php-bitcoin-rpc

Minimal Bitcoin JSON-RPC client for PHP 7.4+, based on Guzzle.

Built for our own apps first. Minimal feature set by design.

## Install

```bash
composer require crypto-packages/php-bitcoin-rpc
```

## Usage

### Basic auth (Bitcoin Core default)

```php
use CryptoPackages\BitcoinRpc\BitcoinRpcClient;

$client = new BitcoinRpcClient([
    'url'     => 'https://127.0.0.1:8332/',
    'auth'    => ['rpcuser', 'rpcpassword'],
    'timeout' => 30,
    'headers' => [],
]);
```

### Bearer token

`auth` is HTTP Basic only. For Bearer (or any custom auth), skip `auth` and set the header:

```php
$client = new BitcoinRpcClient([
    'url'     => 'https://rpc.example.com/',
    'timeout' => 30,
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
    ],
]);
```

### Calls

```php
// Magic RPC call
$count = $client->getblockcount()->get();

// Per-call wallet path (mutates this instance)
$utxos = $client->wallet('main')->listunspent(1, 9999999, [$address])->get();

// Bound wallet client (returns a new instance)
$withWallet = $client->setWallet('main');
$withWallet->getbalance()->get();

// null / '' → no /wallet/... path
$client->wallet(null)->getblockcount()->get();
$plain = $client->setWallet(null);
```

## Errors

- Transport / HTTP: Guzzle exceptions (`GuzzleHttp\Exception\...`)
- JSON-RPC `error` object: `CryptoPackages\BitcoinRpc\Exceptions\RpcException`

## License

MIT
