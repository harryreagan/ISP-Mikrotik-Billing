<?php

namespace App\Services;

use Exception;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use RouterOS\Streams\ResourceStream;
use RouterOS\APIConnector;
use RouterOS\Exceptions\BadCredentialsException;

class RobustRouterosClient extends Client
{
    protected Config $cfg;

    public function __construct(Config $config)
    {
        $this->cfg = $config;
        parent::__construct($config, false); // Do not auto-connect

        $refMethod = new \ReflectionMethod(Client::class, 'openSocket');
        $refMethod->setAccessible(true);
        $refMethod->invoke($this);

        if (null === $this->getSocket()) {
            throw new Exception("Unable to establish socket to " . $config->get('host'));
        }

        $stream = new ResourceStream($this->getSocket());
        $stream->setThrowTimeoutException($config->get('throw_timeout_exception'));

        $refProp = new \ReflectionProperty(Client::class, 'connector');
        $refProp->setAccessible(true);
        $refProp->setValue($this, new APIConnector($stream));

        $this->authenticate();
    }

    private function authenticate(): void
    {
        // Step 1: Direct modern login (RouterOS v6.43+ and all RouterOS v7) with plaintext credentials
        $qModern = new Query('/login', [
            '=name=' . $this->cfg->get('user'),
            '=password=' . $this->cfg->get('pass'),
        ]);
        $resp = $this->query($qModern)->read(false);

        if (!empty($resp[0]) && '!done' === $resp[0]) {
            return; // Clean instant authentication!
        }

        // Step 2: Fallback for legacy pre-6.43 RouterOS firmware using MD5 challenge-response
        if (!empty($resp[0]) && '!trap' === $resp[0]) {
            try {
                $refMethodClose = new \ReflectionMethod(Client::class, 'closeSocket');
                $refMethodClose->setAccessible(true);
                $refMethodClose->invoke($this);

                $refMethodOpen = new \ReflectionMethod(Client::class, 'openSocket');
                $refMethodOpen->setAccessible(true);
                $refMethodOpen->invoke($this);

                if (null !== $this->getSocket()) {
                    $stream = new ResourceStream($this->getSocket());
                    $stream->setThrowTimeoutException($this->cfg->get('throw_timeout_exception'));

                    $refProp = new \ReflectionProperty(Client::class, 'connector');
                    $refProp->setAccessible(true);
                    $refProp->setValue($this, new APIConnector($stream));

                    $resp1 = $this->query('/login')->read(false);
                    $salt = null;
                    if (is_array($resp1)) {
                        foreach ($resp1 as $line) {
                            if (is_string($line) && str_starts_with($line, '=ret=')) {
                                $salt = substr($line, 5);
                                break;
                            }
                        }
                    }

                    if ($salt) {
                        $qLegacy = new Query('/login', [
                            '=name=' . $this->cfg->get('user'),
                            '=response=00' . md5(chr(0) . $this->cfg->get('pass') . pack('H*', $salt)),
                        ]);
                        $respLegacy = $this->query($qLegacy)->read(false);
                        if (!empty($respLegacy[0]) && '!done' === $respLegacy[0]) {
                            return;
                        }
                    }
                }
            } catch (\Throwable $e) {}

            throw new BadCredentialsException('Invalid user name or password');
        }

        if (!empty($resp[0]) && '!trap' === $resp[0]) {
            throw new BadCredentialsException('Invalid user name or password');
        }
    }
}
