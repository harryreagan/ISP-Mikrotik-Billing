<?php

namespace App\Services;

use Exception;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

class MikrotikApiService
{
    protected $client;

    public function __construct($host, $port, $username, $password, int $timeout = 8)
    {
        try {
            $effectiveTimeout = max($timeout, 8);
            $config = new Config([
                'host'           => $host,
                'user'           => $username,
                'pass'           => $password,
                'port'           => $port ?? 8728,
                'timeout'        => $effectiveTimeout,
                'socket_timeout' => $effectiveTimeout,
                'attempts'       => 1,
            ]);

            $attempts = 2;
            $lastEx = null;

            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                try {
                    // Instantiate Client without autoConnect so we control the login handshake
                    $client = new Client($config, false);

                    $refMethod = new \ReflectionMethod(Client::class, 'openSocket');
                    $refMethod->setAccessible(true);
                    $refMethod->invoke($client);

                    $socket = $client->getSocket();
                    if (!$socket) {
                        throw new Exception('Socket connection failed to ' . $host . ':' . $port);
                    }

                    $stream = new \RouterOS\Streams\ResourceStream($socket);
                    $stream->setThrowTimeoutException($config->get('throw_timeout_exception'));

                    $refProp = new \ReflectionProperty(Client::class, 'connector');
                    $refProp->setAccessible(true);
                    $refProp->setValue($client, new \RouterOS\APIConnector($stream));

                    $user = (string) $username;
                    $pass = (string) $password;

                    // 1. Initial /login handshake query (RouterOS v6 & v7)
                    $handshake = $client->query(new Query('/login'))->read(false);
                    $salt = null;
                    if (is_array($handshake)) {
                        foreach ($handshake as $line) {
                            if (is_string($line) && str_starts_with($line, '=ret=')) {
                                $salt = substr($line, 5);
                                break;
                            }
                        }
                    }

                    // 2. Modern login with plaintext credentials (RouterOS v6.43+ and v7)
                    $qModern = new Query('/login', [
                        '=name=' . $user,
                        '=password=' . $pass,
                    ]);
                    $resp = $client->query($qModern)->read(false);

                    // 3. Fallback to legacy MD5 challenge if !trap returned and salt present (pre-6.43)
                    if (is_array($resp) && isset($resp[0]) && $resp[0] === '!trap' && $salt) {
                        $qLegacy = new Query('/login', [
                            '=name=' . $user,
                            '=response=00' . md5(chr(0) . $pass . pack('H*', $salt)),
                        ]);
                        $resp = $client->query($qLegacy)->read(false);
                    }

                    if (is_array($resp) && isset($resp[0]) && $resp[0] === '!trap') {
                        $msg = 'Invalid username or password';
                        foreach ($resp as $w) {
                            if (is_string($w) && str_starts_with($w, '=message=')) {
                                $msg = substr($w, 9);
                                break;
                            }
                        }
                        throw new Exception('MikroTik API authentication failed: ' . $msg);
                    }

                    $this->client = $client;
                    $lastEx = null;
                    break;
                } catch (\Throwable $e) {
                    $lastEx = $e;
                    if ($attempt < $attempts) {
                        usleep(600000); // 600ms backoff
                    }
                }
            }

            if (!$this->client && $lastEx) {
                throw $lastEx;
            }
        } catch (Exception $e) {
            throw new Exception('Failed to connect to MikroTik: ' . $e->getMessage());
        }
    }

    /**
     * Execute a MikroTik API command
     *
     * @param  string  $command  The RouterOS command (e.g., '/system/resource/print')
     * @param  array  $params  Optional command parameters
     * @return array|string
     */
    public function executeCommand(string $command, array $params = [], array $filters = [])
    {
        try {
            $query = new Query($command);

            $isPrint = str_ends_with(trim($command), '/print');

            // Add filters (?) for /print queries, or (=) equal parameters for /set, /remove, /enable, /disable
            foreach ($filters as $key => $value) {
                if ($isPrint) {
                    $query->where($key, $value);
                } else {
                    $query->equal($key, $value);
                }
            }

            // Add parameters (=)
            foreach ($params as $key => $value) {
                $query->equal($key, $value);
            }

            $response = $this->client->query($query)->read();

            return $response;
        } catch (Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }
}
