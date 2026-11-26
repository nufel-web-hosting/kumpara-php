<?php

declare(strict_types=1);

namespace Kumpara\Tests;

use Kumpara\Exception\SignatureException;
use Kumpara\Webhook;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test';
    private const NOW = 1763571234;

    private function body(): string
    {
        return '{"id":"evt_1","type":"invoice.paid","created":1763571234,"livemode":false,"tenantId":null,"data":{"invoice":{"id":26}}}';
    }

    private function header(string $body, int $t = self::NOW, string $secret = self::SECRET): string
    {
        return 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $body, $secret);
    }

    public function testValidEvent(): void
    {
        $event = Webhook::constructEvent($this->body(), $this->header($this->body()), self::SECRET, 300, self::NOW + 10);

        self::assertSame('invoice.paid', $event['type']);
        self::assertSame(26, $event['data']['invoice']['id']);
    }

    public function testTamperedBody(): void
    {
        $this->expectException(SignatureException::class);
        Webhook::constructEvent(str_replace('26', '27', $this->body()), $this->header($this->body()), self::SECRET, 300, self::NOW);
    }

    public function testWrongSecret(): void
    {
        $this->expectException(SignatureException::class);
        Webhook::constructEvent($this->body(), $this->header($this->body(), self::NOW, 'whsec_other'), self::SECRET, 300, self::NOW);
    }

    public function testTooOld(): void
    {
        $this->expectException(SignatureException::class);
        Webhook::constructEvent($this->body(), $this->header($this->body()), self::SECRET, 300, self::NOW + 301);
    }

    public function testMissingHeader(): void
    {
        $this->expectException(SignatureException::class);
        Webhook::constructEvent($this->body(), null, self::SECRET);
    }

    public function testEmptySecret(): void
    {
        $this->expectException(SignatureException::class);
        Webhook::constructEvent($this->body(), $this->header($this->body()), '');
    }
}
