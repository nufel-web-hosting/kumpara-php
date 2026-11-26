<?php

declare(strict_types=1);

namespace Kumpara;

use Kumpara\Exception\ApiException;
use Kumpara\Exception\ConnectionException;
use Kumpara\Resource\Customers;
use Kumpara\Resource\Invoices;
use Kumpara\Resource\Products;
use Kumpara\Resource\Tenants;
use Kumpara\Transport\CurlTransport;
use Kumpara\Transport\Transport;

/**
 * The Kumpara API client.
 *
 *     $kumpara = new Kumpara\Client();                    // reads KUMPARA_API_KEY
 *     $invoice = $kumpara->invoices()->create([...]);
 *
 * Every call returns the `data` part of the API's answer as an array, and throws an ApiException
 * (or one of its subclasses) when the API says no. Amounts are in øre (149 kr = 14900).
 */
final class Client
{
    public const VERSION = '0.1.0';
    public const DEFAULT_BASE_URL = 'https://api.kumpara.no/v1';

    private const MAX_RETRY_WAIT = 10;   // seconds; a longer Retry-After is thrown to the caller

    private string $apiKey;
    private string $baseUrl;
    private ?int $tenantId;
    private int $timeout;
    private int $maxRetries;
    private Transport $transport;
    /** @var callable(float): void */
    private $sleep;

    /**
     * @param string|null $apiKey kp_test_... or kp_live_...; null reads the KUMPARA_API_KEY environment variable
     * @param array{
     *     base_url?: string,
     *     tenant_id?: int|null,
     *     timeout?: int,
     *     max_retries?: int,
     *     transport?: Transport,
     *     sleep?: callable(float): void
     * } $options
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $apiKey ??= (getenv('KUMPARA_API_KEY') ?: null);
        if ($apiKey === null || trim($apiKey) === '') {
            throw new \InvalidArgumentException('No API key. Pass one, or set the KUMPARA_API_KEY environment variable.');
        }

        $this->apiKey = trim($apiKey);
        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $this->tenantId = $options['tenant_id'] ?? null;
        $this->timeout = $options['timeout'] ?? 30;
        $this->maxRetries = max(0, $options['max_retries'] ?? 2);
        $this->transport = $options['transport'] ?? new CurlTransport();
        $this->sleep = $options['sleep'] ?? static function (float $seconds): void {
            usleep((int)($seconds * 1_000_000));
        };
    }

    /**
     * The same client, working in one of your customers' accounts (X-Tenant-Id). The original is unchanged.
     */
    public function withTenant(?int $tenantId): self
    {
        $copy = clone $this;
        $copy->tenantId = $tenantId;

        return $copy;
    }

    /** True for a kp_test_ key: test account, no e-mail to customers, TEST on the PDF. */
    public function isTestMode(): bool
    {
        return str_starts_with($this->apiKey, 'kp_test_');
    }

    public function customers(): Customers
    {
        return new Customers($this);
    }

    public function products(): Products
    {
        return new Products($this);
    }

    public function invoices(): Invoices
    {
        return new Invoices($this);
    }

    public function tenants(): Tenants
    {
        return new Tenants($this);
    }

    /** The account the key works in, and `mode`: test or live. */
    public function me(): array
    {
        return $this->request('GET', '/me');
    }

    /** Look up a company in Enhetsregisteret by organisation number or name. */
    public function registry(string $query): array
    {
        return $this->request('GET', '/registry', ['q' => $query]);
    }

    /**
     * One API call. Retries what is safe to repeat: a 429 (the call was not run), and for GET, or a POST whose
     * body carries externalRef/ref (Kumpara gives the same result back), also network errors and 5xx answers.
     *
     * @internal Used by the resource classes; prefer those.
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $url = $this->baseUrl . $path;
        $query = array_filter($query, static fn($v) => $v !== null && $v !== '');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'kumpara-php/' . self::VERSION . ' PHP/' . PHP_VERSION,
        ];
        if ($this->tenantId !== null) {
            $headers['X-Tenant-Id'] = (string)$this->tenantId;
        }
        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = $body === []
                ? '{}'
                : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        $repeatable = $method === 'GET' || isset($body['externalRef']) || isset($body['ref']);

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->transport->send($method, $url, $headers, $payload, $this->timeout);
            } catch (ConnectionException $e) {
                if ($repeatable && $attempt < $this->maxRetries) {
                    ($this->sleep)($this->backoff($attempt));
                    continue;
                }
                throw $e;
            }

            $json = json_decode($response->body, true);
            if ($response->status >= 200 && $response->status < 300 && is_array($json) && ($json['success'] ?? false) === true) {
                return is_array($json['data'] ?? null) ? $json['data'] : [];
            }

            $error = ApiException::fromResponse($response->status, is_array($json) ? $json : null, $response);
            if ($attempt < $this->maxRetries) {
                $code = $error->getErrorCode();
                if ($response->status === 429 && $code === 'rate_limited') {
                    $wait = $error->getRetryAfter() ?? 1;
                    if ($wait <= self::MAX_RETRY_WAIT) {
                        ($this->sleep)((float)$wait);
                        continue;
                    }
                } elseif ($response->status >= 500 && $repeatable && $code !== 'payment_provider') {
                    ($this->sleep)($this->backoff($attempt));
                    continue;
                }
            }
            throw $error;
        }
    }

    private function backoff(int $attempt): float
    {
        return min(8.0, 0.5 * (2 ** $attempt)) + mt_rand(0, 250) / 1000;
    }
}
