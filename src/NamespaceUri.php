<?php

namespace Schemastud\JsonNs;

use Stringable;

/**
 * A namespace URI is *just a URI* — `SchemaId`-shaped (asset 05 §8): a `<base>/<name>`
 * stem plus an optional trailing monotonic integer version. This is the framework-free
 * mirror of `Splicewire\Beam\Schema\SchemaId` (the grammar the host reuses); the spec is
 * explicit that a namespace URI must not invent a second URI grammar.
 *
 * The value object is PURE and TOTAL: a well-formed URI round-trips byte-for-byte, and a
 * version-free or malformed tail yields a null version rather than throwing — so
 * "is this pinned?" is a cheap total query.
 *
 * Because the URI is identity (§5), a version-free stem and a pinned `.../3` are simply
 * *different URIs* with different match scopes: a handler on the stem fires across all
 * versions in the namespace; a handler on `.../3` fires only for version 3. No new
 * machinery — pinning is a suffix, unpinning is its absence.
 *
 * Generational markers a vocabulary needs (à la `.../draft/2020-12/vocab/core`) live as
 * ordinary path text inside the stem, never as the trailing-integer version — so the
 * integer keeps meaning exactly one thing (schema-instance version).
 */
class NamespaceUri implements Stringable
{
    private string $raw;

    private string $stem;

    private ?int $version;

    private function __construct(string $raw, string $stem, ?int $version)
    {
        $this->raw = $raw;
        $this->stem = $stem;
        $this->version = $version;
    }

    /**
     * Parse a namespace URI. Tolerant: when the trailing segment is not a non-negative
     * integer (version-free or malformed), the version is null and the whole input is the
     * stem, so the value still round-trips.
     */
    public static function from(string $uri): self
    {
        $pos = strrpos($uri, '/');

        if ($pos === false) {
            return new self($uri, $uri, null);
        }

        $stem = substr($uri, 0, $pos);
        $tail = substr($uri, $pos + 1);

        $version = ($tail !== '' && ctype_digit($tail)) ? (int) $tail : null;

        return new self($uri, $stem, $version);
    }

    /** The `<base>/<name>` stem — the URI sans any trailing version segment. */
    public function stem(): string
    {
        return $this->stem;
    }

    /** The trailing integer version, or null when the URI is version-free (unpinned). */
    public function version(): ?int
    {
        return $this->version;
    }

    /**
     * True when the URI carries no version — an *unpinned handle* that resolves to
     * "latest". "Latest" is a resolver/store concern (§8), not grammar; the grammar only
     * reports pinned-ness.
     */
    public function isUnpinned(): bool
    {
        return $this->version === null;
    }

    /** True when the URI pins exactly one version. */
    public function isPinned(): bool
    {
        return $this->version !== null;
    }

    /** A sibling URI on the same stem at the given version. */
    public function withVersion(int $version): self
    {
        return new self($this->stem.'/'.$version, $this->stem, $version);
    }

    /** True iff two URIs share the same stem (same namespace, any version). */
    public function sameStemAs(self $other): bool
    {
        return $this->stem === $other->stem;
    }

    /** URI-is-identity equality: exact string identity (a stem and a pin are distinct). */
    public function equals(self $other): bool
    {
        return $this->raw === $other->raw;
    }

    public function __toString(): string
    {
        return $this->raw;
    }
}
