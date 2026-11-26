<?php

declare(strict_types=1);

namespace Kumpara\Resource;

use Kumpara\Client;

/**
 * @internal The shared base of the resource classes.
 */
abstract class Resource
{
    public function __construct(protected readonly Client $client)
    {
    }
}
