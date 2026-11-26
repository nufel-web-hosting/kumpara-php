<?php

declare(strict_types=1);

namespace Kumpara\Exception;

/**
 * 401: no key, or the key does not exist (invalid_api_key, unauthorized).
 */
class AuthenticationException extends ApiException
{
}
