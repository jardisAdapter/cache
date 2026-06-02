<?php

declare(strict_types=1);

namespace JardisAdapter\Cache\Exception;

use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

/**
 * PSR-16 compliant invalid argument exception.
 */
class InvalidArgumentException extends \InvalidArgumentException implements PsrInvalidArgumentException
{
}
