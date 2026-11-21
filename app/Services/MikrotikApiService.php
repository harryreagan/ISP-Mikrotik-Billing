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
            $targetPort = (int) ($port ?: 8728);

            $attempts = 2;
            $lastEx = null;

            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                try {
                    $config = new Config([
                        'host'           => $host,
                        'user'           => (string) $username,
                        'pass'           => (string) $password,
                        'port'           => $targetPort,
                        'timeout'        => $effectiveTimeout,
                        'socket_timeout' => $effectiveTimeout,
                        'attempts'       => 1,
                        'delay'          => 1,
                    ]);

                    $this->client = new RobustRouterosClient($config);
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
                    $cleanKey = ltrim((string) $key, '?');
                    $query->where($cleanKey, (string) $value);
                } else {
                    $cleanKey = ltrim((string) $key, '=');
                    $query->equal($cleanKey, (string) $value);
                }
            }

            // Add parameters (=)
            foreach ($params as $key => $value) {
                $strKey = (string) $key;
                if ($isPrint && str_starts_with($strKey, '?')) {
                    $query->where(substr($strKey, 1), (string) $value);
                } else {
                    $cleanKey = ltrim($strKey, '=');
                    $query->equal($cleanKey, (string) $value);
                }
            }

            $response = $this->client->query($query)->read();

            return $response;
        } catch (Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    public function comm(string $command, array $params = [])
    {
        return $this->executeCommand($command, $params);
    }

    /**
     * Explicitly close API socket stream to prevent router socket pool exhaustion
     */
    public function close(): void
    {
        if ($this->client) {
            try {
                $refProp = new \ReflectionProperty(Client::class, 'socket');
                $refProp->setAccessible(true);
                $sock = $refProp->getValue($this->client);
                if (is_resource($sock)) {
                    @fclose($sock);
                }
            } catch (\Throwable $e) {}
            $this->client = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
