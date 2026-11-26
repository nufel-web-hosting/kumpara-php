<?php

declare(strict_types=1);

namespace Kumpara\Exception;

/**
 * A webhook whose Kumpara-Signature does not match, is too old, or whose body is not a Kumpara event.
 */
class SignatureException extends KumparaException
{
}
