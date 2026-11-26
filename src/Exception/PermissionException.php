<?php

declare(strict_types=1);

namespace Kumpara\Exception;

/**
 * 403: the key lacks a scope (missing_scope), the action is only for the Kumpara app (not_allowed_for_api_key), or the account is closed (account_closed).
 */
class PermissionException extends ApiException
{
}
