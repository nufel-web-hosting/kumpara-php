<?php

declare(strict_types=1);

namespace Kumpara\Exception;

/**
 * No answer from Kumpara (DNS, TLS, timeout). The call may or may not have been run: use externalRef to repeat it safely.
 */
class ConnectionException extends KumparaException
{
}
