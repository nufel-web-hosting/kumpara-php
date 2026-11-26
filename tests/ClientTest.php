<?php

declare(strict_types=1);

namespace Kumpara\Tests;

use Kumpara\Client;
use Kumpara\Exception\ConflictException;
use Kumpara\Exception\ConnectionException;
use Kumpara\Exception\RateLimitException;
use Kumpara\Exception\ServerException;
use Kumpara\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private FakeTransport $http;
    /** @var list<float> */
    private array $slept = [];

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->slept = [];
    }

    private function client(array $options = []): Client
    {
        return new Client('kp_test_abc', $options + [
            'transport' => $this->http,
            'sleep' => function (float $s): void { $this->slept[] = $s; },
        ]);
    }

    public function testSendsKeyJsonAndReturnsData(): void
    {
        $this->http->push(200, ['success' => true, 'message' => 'Fakturaen er laget', 'data' => ['id' => 26, 'kid' => '10000008']]);

        $invoice = $this->client()->invoices()->create([
            'customerId' => 7,
            'lines' => [['name' => 'Oppsett', 'quantity' => 1, 'unitPrice' => 95000]],
            'externalRef' => 'ordre-1001',
        ]);

        self::assertSame(26, $invoice['id']);
        $request = $this->http->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame('https://api.kumpara.no/v1/invoices', $request['url']);
        self::assertSame('Bearer kp_test_abc', $request['headers']['Authorization']);
        self::assertSame('application/json', $request['headers']['Content-Type']);
        self::assertArrayNotHasKey('X-Tenant-Id', $request['headers']);
        self::assertSame('ordre-1001', json_decode($request['body'], true)['externalRef']);
    }

    public function testQueryAndEmptyBody(): void
    {
        $this->http->push(200, ['success' => true, 'data' => ['invoices' => []]]);
        $this->http->push(200, ['success' => true, 'data' => []]);

        $client = $this->client();
        $client->invoices()->list(['filter' => 'overdue', 'q' => '', 'page' => 2]);
        $client->invoices()->markUnpaid(5);

        self::assertSame('https://api.kumpara.no/v1/invoices?filter=overdue&page=2', $this->http->requests[0]['url']);
        self::assertSame('{}', $this->http->requests[1]['body']);
    }

    public function testWithTenantSendsHeaderAndLeavesOriginal(): void
    {
        $this->http->push(200, ['success' => true, 'data' => []]);
        $this->http->push(200, ['success' => true, 'data' => []]);

        $client = $this->client();
        $client->withTenant(1234)->customers()->list();
        $client->customers()->list();

        self::assertSame('1234', $this->http->requests[0]['headers']['X-Tenant-Id']);
        self::assertArrayNotHasKey('X-Tenant-Id', $this->http->requests[1]['headers']);
    }

    public function testValidationErrorCarriesCodeAndField(): void
    {
        $this->http->push(422, ['success' => false, 'message' => 'Gratis har 5 fakturaer i måneden', 'code' => 'validation_error', 'field' => 'plan']);

        try {
            $this->client()->invoices()->create(['customerId' => 1, 'lines' => []]);
            self::fail('No exception');
        } catch (ValidationException $e) {
            self::assertSame(422, $e->getHttpStatus());
            self::assertSame('validation_error', $e->getErrorCode());
            self::assertSame('plan', $e->getField());
            self::assertSame('Gratis har 5 fakturaer i måneden', $e->getMessage());
        }
    }

    public function testConflictIsNotRetried(): void
    {
        $this->http->push(409, ['success' => false, 'message' => 'Mangler avsender', 'code' => 'conflict', 'field' => 'settings']);

        $this->expectException(ConflictException::class);
        $this->client()->invoices()->create(['customerId' => 1, 'lines' => [], 'externalRef' => 'x']);
    }

    public function testGetIsRetriedAfterServerError(): void
    {
        $this->http->push(500, ['success' => false, 'message' => 'Feil', 'code' => 'server_error']);
        $this->http->push(200, ['success' => true, 'data' => ['mode' => 'test']]);

        self::assertSame('test', $this->client()->me()['mode']);
        self::assertCount(2, $this->http->requests);
        self::assertCount(1, $this->slept);
    }

    public function testPostWithoutExternalRefIsNotRetried(): void
    {
        $this->http->push(500, ['success' => false, 'message' => 'Feil', 'code' => 'server_error']);

        $this->expectException(ServerException::class);
        try {
            $this->client()->invoices()->create(['customerId' => 1, 'lines' => []]);
        } finally {
            self::assertCount(1, $this->http->requests);
        }
    }

    public function testPostWithExternalRefIsRetriedAfterTimeout(): void
    {
        $this->http->pushFailure();
        $this->http->push(200, ['success' => true, 'data' => ['id' => 9]]);

        $invoice = $this->client()->invoices()->create(['customerId' => 1, 'lines' => [], 'externalRef' => 'ordre-9']);

        self::assertSame(9, $invoice['id']);
        self::assertCount(2, $this->http->requests);
    }

    public function testConnectionErrorGivesUpAfterMaxRetries(): void
    {
        $this->http->pushFailure()->pushFailure()->pushFailure();

        $this->expectException(ConnectionException::class);
        $this->client()->me();
    }

    public function testRateLimitWaitsRetryAfter(): void
    {
        $this->http->push(429, ['success' => false, 'message' => 'For mange kall', 'code' => 'rate_limited'], ['Retry-After' => '3']);
        $this->http->push(200, ['success' => true, 'data' => []]);

        $this->client()->customers()->create(['type' => 'person', 'name' => 'Kari']);

        self::assertSame([3.0], $this->slept);
    }

    public function testLongRetryAfterIsThrown(): void
    {
        $this->http->push(429, ['success' => false, 'message' => 'For mange kall', 'code' => 'rate_limited'], ['Retry-After' => '45']);

        try {
            $this->client()->me();
            self::fail('No exception');
        } catch (RateLimitException $e) {
            self::assertSame(45, $e->getRetryAfter());
            self::assertSame([], $this->slept);
        }
    }

    public function testPdfContentsDecodes(): void
    {
        $this->http->push(200, ['success' => true, 'data' => ['filename' => 'faktura-1.pdf', 'contentType' => 'application/pdf', 'size' => 8, 'base64' => base64_encode('%PDF-1.4')]]);

        self::assertSame('%PDF-1.4', $this->client()->invoices()->pdfContents(1));
    }

    public function testTestMode(): void
    {
        self::assertTrue($this->client()->isTestMode());
        self::assertFalse((new Client('kp_live_x', ['transport' => $this->http]))->isTestMode());
    }

    public function testMissingKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client('  ');
    }
}
