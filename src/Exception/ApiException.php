<?php

declare(strict_types=1);

namespace Kumpara\Exception;

use Kumpara\Transport\Response;

/**
 * The API answered with an error. getMessage() is the API's own text (Norwegian, for people); use
 * getErrorCode() and getField() in code. The subclasses follow the HTTP status.
 */
class ApiException extends KumparaException
{
    private int $httpStatus;
    private string $errorCode;
    private ?string $field;
    private ?int $retryAfter;
    private string $rawBody;

    final public function __construct(string $message, int $httpStatus, string $errorCode, ?string $field = null,
                                      ?int $retryAfter = null, string $rawBody = '')
    {
        parent::__construct($message, $httpStatus);
        $this->httpStatus = $httpStatus;
        $this->errorCode = $errorCode;
        $this->field = $field;
        $this->retryAfter = $retryAfter;
        $this->rawBody = $rawBody;
    }

    /**
     * @param array<string, mixed>|null $json the decoded answer, null when it was not JSON
     */
    public static function fromResponse(int $status, ?array $json, Response $response): self
    {
        $code = is_string($json['code'] ?? null) ? $json['code'] : self::codeFor($status);
        $message = is_string($json['message'] ?? null) && $json['message'] !== ''
            ? $json['message']
            : 'Kumpara answered HTTP ' . $status;
        $field = is_string($json['field'] ?? null) ? $json['field'] : null;
        $retryAfter = $response->header('Retry-After');

        $class = match (true) {
            $status === 401 => AuthenticationException::class,
            $status === 402, $code === 'payment_provider' => PaymentException::class,
            $status === 403 => PermissionException::class,
            $status === 404 => NotFoundException::class,
            $status === 409 => ConflictException::class,
            $status === 422 => ValidationException::class,
            $status === 429 => RateLimitException::class,
            $status >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class($message, $status, $code, $field,
            $retryAfter !== null && ctype_digit($retryAfter) ? (int)$retryAfter : null, $response->body);
    }

    private static function codeFor(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthorized',
            402 => 'payment_failed',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            409 => 'conflict',
            422 => 'validation_error',
            429 => 'rate_limited',
            default => 'server_error',
        };
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /** The stable error code, for example validation_error, conflict, rate_limited. */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** The field the error is about, when it is about one (for example email, settings, plan). */
    public function getField(): ?string
    {
        return $this->field;
    }

    /** Seconds to wait (429). */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }
}
