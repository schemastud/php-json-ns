<?php

namespace Schemastud\JsonNs\Exceptions;

use RuntimeException;

/**
 * Thrown when a CURIE key or an `@namespaced` overlay names a prefix that no `@namespace`
 * in the lexically-enclosing scope binds.
 */
class UnboundPrefix extends RuntimeException
{
    public static function at(string $prefix, string $pointer): self
    {
        return new self(sprintf(
            'Prefix "%s" is not bound by any @namespace enclosing "%s".',
            $prefix,
            $pointer === '' ? '#' : $pointer,
        ));
    }
}
