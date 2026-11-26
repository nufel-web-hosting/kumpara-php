<?php

declare(strict_types=1);

namespace Kumpara\Exception;

/**
 * 429: too many calls (rate_limited, see getRetryAfter()) or too many invalid keys from your IP (too_many_invalid_keys).
 */
class RateLimitException extends ApiException
{
}
