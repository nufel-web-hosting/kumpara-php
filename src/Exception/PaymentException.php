<?php

declare(strict_types=1);

namespace Kumpara\Exception;

/**
 * 402 payment_failed or 502 payment_provider: the card was declined or the payment provider did not answer. Nothing was made.
 */
class PaymentException extends ApiException
{
}
