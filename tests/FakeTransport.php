<?php

declare(strict_types=1);

namespace Kumpara\Tests;

use Kumpara\Exception\ConnectionException;
use Kumpara\Transport\Response;
use Kumpara\Transport\Transport;

/** Answers from a queue and remembers every request. */
final class FakeTransport implements Transport
{
    /** @var list<Response|ConnectionException> */
    private array $queue = [];
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $requests = [];

    public function push(int $status, array $json, array $headers = []): self
    {
        $this->queue[] = new Response($status, $headers, json_encode($json));
        return $this;
    }

    public function pushFailure(string $message = 'timeout'): self
    {
        $this->queue[] = new ConnectionException($message);
        return $this;
    }

    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): Response
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException('No answer queued for ' . $method . ' ' . $url);
        }
        if ($next instanceof ConnectionException) {
            throw $next;
        }
        return $next;
    }
}
