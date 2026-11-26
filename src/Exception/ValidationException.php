<?php

declare(strict_types=1);

namespace Kumpara\Exception;

/**
 * 422 validation_error: something in the request is wrong. getField() names the field, for example plan when the free invoices are used up.
 */
class ValidationException extends ApiException
{
}
