<?php

namespace Schemastud\JsonNs;

/**
 * The `@`-keyed grammar words this dialect owns — peers of json-reference's `@id`.
 * Borrowed from JSON-LD, RDF stripped (asset 05 §3).
 */
class Vocabulary
{
    /** The prefix→URI binding map — the singular `@`-key, a peer of `@id`/`@context`. */
    public const Namespace = '@namespace';

    /** The subtree-rescope overlay keyword (string shorthand or sidecar array). */
    public const Namespaced = '@namespaced';

    /** The CURIE prefix/local separator. */
    public const CurieSeparator = ':';
}
