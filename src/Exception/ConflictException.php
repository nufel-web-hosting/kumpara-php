<?php

declare(strict_types=1);

namespace Kumpara\Exception;

/**
 * 409 conflict: cannot be done now. getField() says why, for example settings (sender details missing) or numberStart.
 */
class ConflictException extends ApiException
{
}
