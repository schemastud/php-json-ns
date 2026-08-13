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

    /**
     * JSON Schema 2020-12's own `$vocabulary` keyword (URI→bool; the bool is that vocabulary's
     * required/optional bit) — not an `@`-key of ours, but the standard word the enforcement
     * shim reads (JN-17).
     */
    public const VocabularyKeyword = '$vocabulary';

    /** The CURIE prefix/local separator. */
    public const CurieSeparator = ':';

    /**
     * The `@namespace`/`@namespaced` members of a node — the declarations a governing schema
     * overlays onto a submitted document before scoping (ADR-0193 §1). One extraction shared
     * by every gate, so overlay semantics cannot drift between enforcement sites.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public static function declarationsOf(array $node): array
    {
        return array_intersect_key($node, [
            self::Namespace => true,
            self::Namespaced => true,
        ]);
    }
}
