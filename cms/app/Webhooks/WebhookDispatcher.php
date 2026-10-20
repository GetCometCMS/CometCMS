<?php

declare(strict_types=1);

namespace CometCMS\Webhooks;

use CometCMS\Core\Security;
use CometCMS\Logging\Logger;

final class WebhookDispatcher
{
    public function __construct(private readonly Logger $logger = new Logger(), private readonly WebhookSigner $signer = new WebhookSigner()) {}

    public function dispatch(string $event, array $data): void
    {
        foreach ((array) comet_config('webhooks', []) as $webhook) {
            if (!is_array($webhook) || ($webhook['enabled'] ?? true) === false) {
                continue;
            }

            if (!in_array($event, (array) ($webhook['events'] ?? []), true)) {
                continue;
            }

            if (!$this->sendWebhook($webhook, $event, $data)) {
                $url = (string) ($webhook['url'] ?? '');
                $this->logger->warning('webhook failed', ['event' => $event, 'url' => $url]);
            }
        }
    }

    public function sendWebhook(array $webhook, string $event, array $data): bool
    {
        $url = (string) ($webhook['url'] ?? '');
        $secret = (string) ($webhook['secret'] ?? '');

        if ($url === '') {
            return false;
        }

        $payload = json_encode([
            'event' => $event,
            'occurred_at' => Security::now(),
            'data' => $data,
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return false;
        }

        $headers = ['Content-Type: application/json'];

        if ($secret !== '') {
            $headers[] = 'X-CometCMS-Signature: ' . $this->signer->sign($payload, $secret);
        }

        return $this->send($url, $payload, $headers);
    }

    private function send(string $url, string $payload, array $headers): bool
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
            ]);
            curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

            return $status >= 200 && $status < 300;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'timeout' => 3,
            ],
        ]);

        return @file_get_contents($url, false, $context) !== false;
    }
}
