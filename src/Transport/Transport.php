<?php

declare(strict_types=1);

namespace Kumpara\Transport;

use Kumpara\Exception\ConnectionException;

/**
 * Sends one HTTP request. The default is CurlTransport; pass your own (for example in tests) as the
 * client's `transport` option.
 */
interface Transport
{
    /**
     * @param array<string, string> $headers
     * @throws ConnectionException when no HTTP answer came back (DNS, TLS, timeout)
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): Response;
}
