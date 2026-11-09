<?php

namespace App\Services\HubConnector;

use App\Models\HubOutbox;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HubClient
{
    protected string $baseUrl;
    protected string $key;
    protected string $secret;
    protected int $timeout;
    protected int $maxRetries;

    public function __construct()
    {
        $this->baseUrl = config('hub.url', 'http://127.0.0.1:8000');
        $this->key = config('hub.key', '');
        $this->secret = config('hub.secret', '');
        $this->timeout = config('hub.timeout', 10);
        $this->maxRetries = config('hub.max_retries', 3);
    }

    public function isConfigured(): bool
    {
        return !empty($this->key) && !empty($this->secret) && !empty($this->baseUrl);
    }

    /**
     * Send signed request to Hub
     */
    public function send(string $method, string $path, array $data = [], bool $queueOnFailure = true): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Hub connector is not configured. Missing HUB_KEY or HUB_SECRET.',
            ];
        }

        $method = strtoupper($method);
        $normalizedPath = '/' . ltrim($path, '/');
        $url = $this->baseUrl . $normalizedPath;

        $body = ($method === 'GET') ? '' : json_encode($data, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $nonce = Str::random(32);

        $payloadToSign = $method . "\n" . $normalizedPath . "\n" . $timestamp . "\n" . $nonce . "\n" . $body;
        $signature = hash_hmac('sha256', $payloadToSign, $this->secret);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Hub-Key' => $this->key,
            'X-Hub-Timestamp' => $timestamp,
            'X-Hub-Nonce' => $nonce,
            'X-Hub-Signature' => $signature,
        ];

        try {
            $response = Http::withHeaders($headers)
                ->timeout($this->timeout)
                ->retry($this->maxRetries, 200, throw: false)
                ->send($method, $url, ['body' => $body]);

            $statusCode = $response->status();
            $result = $response->json() ?? [];

            if ($response->successful()) {
                return array_merge(['success' => true, 'status_code' => $statusCode], $result);
            }

            if ($statusCode === 403 && isset($result['status']) && $result['status'] === 'suspended') {
                Log::warning('[HubConnector] ISP account suspended by Hub Super Admin.');
                return [
                    'success' => false,
                    'suspended' => true,
                    'status_code' => 403,
                    'error' => $result['error'] ?? 'ISP account is suspended by HSmart Platforms Super Admin.',
                ];
            }

            $errorMessage = $result['error'] ?? "Hub returned HTTP status {$statusCode}";

            if ($queueOnFailure && $method !== 'GET') {
                $this->queueToOutbox($normalizedPath, $method, $data, $errorMessage);
            }

            return [
                'success' => false,
                'status_code' => $statusCode,
                'error' => $errorMessage,
            ];
        } catch (\Throwable $e) {
            $errorMessage = 'Hub connection error: ' . $e->getMessage();
            Log::error('[HubConnector] ' . $errorMessage);

            if ($queueOnFailure && $method !== 'GET') {
                $this->queueToOutbox($normalizedPath, $method, $data, $errorMessage);
            }

            return [
                'success' => false,
                'status_code' => 0,
                'error' => $errorMessage,
            ];
        }
    }

    /**
     * Store failed outbound payload into local outbox table for retry
     */
    protected function queueToOutbox(string $endpoint, string $method, array $payload, string $error): void
    {
        try {
            HubOutbox::create([
                'endpoint' => $endpoint,
                'method' => $method,
                'payload' => $payload,
                'attempts' => 1,
                'status' => 'pending',
                'last_error' => $error,
            ]);
        } catch (\Throwable $e) {
            Log::error('[HubConnector] Could not write to outbox: ' . $e->getMessage());
        }
    }

    /**
     * Flush outbox: Process queued pending messages
     */
    public function flushOutbox(int $batchSize = 25): int
    {
        $pendingItems = HubOutbox::where('status', 'pending')
            ->orderBy('id', 'asc')
            ->take($batchSize)
            ->get();

        $processed = 0;

        foreach ($pendingItems as $item) {
            $result = $this->send($item->method, $item->endpoint, $item->payload, queueOnFailure: false);

            if ($result['success'] ?? false) {
                $item->update([
                    'status' => 'sent',
                    'attempts' => $item->attempts + 1,
                    'last_error' => null,
                ]);
                $processed++;
            } else {
                $item->increment('attempts');
                $item->update([
                    'status' => $item->attempts >= 5 ? 'failed' : 'pending',
                    'last_error' => $result['error'] ?? 'Unknown send failure',
                ]);
            }
        }

        return $processed;
    }
}

