<?php

namespace Schemastud\JsonNs\Exceptions;

use RuntimeException;

/**
 * A namespace the host DECLARES (its fleet manifest names the prefix→URI binding) has no
 * published vocabulary artifact behind it (JN-16 / ADR-0192 §7). Distinct from an *unknown*
 * namespace — which stays a permissive pass under `RefusalPolicy::Optional` — because a host
 * that declares a namespace and never ships its artifact would otherwise validate everything
 * permissively and report success. Declared-but-missing is a deployment defect, not an
 * authoring choice, so it fails loudly.
 */
class VocabularyArtifactMissing extends RuntimeException
{
    public static function for(string $uri): self
    {
        return new self(
            "The namespace \"{$uri}\" is declared by this host but no vocabulary artifact is "
            .'published for it — publish the artifact into the schema registry (fleet tier) or '
            .'remove the declaration.',
        );
    }
}
