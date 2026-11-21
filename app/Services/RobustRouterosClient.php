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
        // Step 1: Initial /login handshake sentence
        $resp1 = $this->query('/login')->read(false);

        // Step 2: Modern login (v6.43+ and v7) with plaintext name and password
        $q2 = new Query('/login', [
            '=name=' . $this->cfg->get('user'),
            '=password=' . $this->cfg->get('pass'),
        ]);
        $resp2 = $this->query($q2)->read(false);

        if (!empty($resp2[0]) && '!done' === $resp2[0]) {
            return; // Authenticated successfully!
        }

        // If rejected with !trap, check if legacy MD5 auth is needed (pre-6.43)
        if (!empty($resp2[0]) && '!trap' === $resp2[0] && !empty($resp1['after']['ret'])) {
            $qLegacy = new Query('/login', [
                '=name=' . $this->cfg->get('user'),
                '=response=00' . md5(chr(0) . $this->cfg->get('pass') . pack('H*', $resp1['after']['ret'])),
            ]);
            $respLegacy = $this->query($qLegacy)->read(false);
            if (!empty($respLegacy[0]) && '!done' === $respLegacy[0]) {
                return;
            }
        }

        if (!empty($resp2[0]) && '!trap' === $resp2[0]) {
            throw new BadCredentialsException('Invalid user name or password');
        }
    }
}
