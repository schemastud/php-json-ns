<?php

namespace Schemastud\JsonNs;

/**
 * A compact CURIE key, `prefix:local` — the data-key axis (asset 05 §2, §4a): the
 * ergonomic 90% case where the namespace rides on the key itself. A bare key (no colon, or
 * a JSON-Pointer / URI-shaped key) is *not* a CURIE — it is governed by an ancestor
 * `@namespaced`/base default, not by prefix resolution.
 */
class Curie
{
    private string $prefix;

    private string $local;

    private function __construct(string $prefix, string $local)
    {
        $this->prefix = $prefix;
        $this->local = $local;
    }

    /**
     * Parse a key into a CURIE, or return null when the key is not compact-CURIE-shaped.
     *
     * A key is a CURIE only when it has exactly the `prefix:local` shape with a non-empty
     * prefix and local part and no path/scheme characters — so `@id`, `sources`,
     * `/config/x`, and `https://…` are all left untouched (not CURIEs).
     */
    public static function tryParse(string $key): ?self
    {
        $pos = strpos($key, Vocabulary::CurieSeparator);

        if ($pos === false || $pos === 0) {
            return null;
        }

        $prefix = substr($key, 0, $pos);
        $local = substr($key, $pos + 1);

        // A URI scheme (`https://…`) has `//` right after the colon; that is not a CURIE.
        if ($local === '' || str_starts_with($local, '/')) {
            return null;
        }

        // Prefixes are simple identifiers — a colon anywhere in the "local" part (a second
        // separator, or a scheme deeper in) disqualifies the key as a compact CURIE.
        if (str_contains($local, Vocabulary::CurieSeparator)) {
            return null;
        }

        // An `@`-grammar key (`@namespace`, `@namespaced`, `@id`) is never a CURIE.
        if (str_starts_with($prefix, '@')) {
            return null;
        }

        return new self($prefix, $local);
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function local(): string
    {
        return $this->local;
    }

    public function __toString(): string
    {
        return $this->prefix.Vocabulary::CurieSeparator.$this->local;
    }
}
