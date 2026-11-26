<?php

declare(strict_types=1);

namespace Kumpara;

use Kumpara\Exception\SignatureException;

/**
 * Checks a webhook from Kumpara before you trust it.
 *
 *     $event = Kumpara\Webhook::constructEvent(
 *         file_get_contents('php://input'),
 *         $_SERVER['HTTP_KUMPARA_SIGNATURE'] ?? null,
 *         getenv('KUMPARA_WEBHOOK_SECRET')
 *     );
 *
 * Kumpara-Signature is "t=<unix time>,v1=<hex>": an HMAC-SHA256 of t, a dot and the raw body, with the webhook's
 * secret (whsec_...). Use the raw body exactly as it came, not a re-encoded copy.
 */
final class Webhook
{
    public const DEFAULT_TOLERANCE = 300;   // seconds between the signature's time and now

    /**
     * @return array{id: string, type: string, created: int, livemode: bool, tenantId: int|null, data: array} the event
     * @throws SignatureException when the signature is missing, wrong or too old, or the body is not an event
     */
    public static function constructEvent(string $payload, ?string $signatureHeader, string $secret,
                                          int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): array
    {
        self::verify($payload, $signatureHeader, $secret, $tolerance, $now);

        $event = json_decode($payload, true);
        if (!is_array($event) || !is_string($event['id'] ?? null) || !is_string($event['type'] ?? null)) {
            throw new SignatureException('The webhook body is not a Kumpara event.');
        }

        return $event;
    }

    /**
     * @throws SignatureException
     */
    public static function verify(string $payload, ?string $signatureHeader, string $secret,
                                  int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): void
    {
        if ($secret === '') {
            throw new SignatureException('No webhook secret. Set KUMPARA_WEBHOOK_SECRET (whsec_...).');
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', (string)$signatureHeader) as $part) {
            [$key, $value] = array_map('trim', explode('=', $part, 2)) + [1 => ''];
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int)$value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }
        if ($timestamp === null || $signatures === []) {
            throw new SignatureException('Missing or malformed Kumpara-Signature header.');
        }

        if ($tolerance > 0 && abs(($now ?? time()) - $timestamp) > $tolerance) {
            throw new SignatureException('The webhook signature is too old.');
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }
        throw new SignatureException('The webhook signature does not match.');
    }
}
