<?php

declare(strict_types=1);

namespace Kumpara\Transport;

use Kumpara\Exception\ConnectionException;

final class CurlTransport implements Transport
{
    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): Response
    {
        $responseHeaders = [];
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $result = curl_exec($ch);
        if ($result === false) {
            $message = curl_error($ch);
            curl_close($ch);
            throw new ConnectionException('Could not reach Kumpara: ' . $message);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return new Response($status, $responseHeaders, (string)$result);
    }
}
