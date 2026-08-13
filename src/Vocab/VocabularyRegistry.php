<?php

namespace Schemastud\JsonNs\Vocab;

use Closure;
use Schemastud\JsonNs\Exceptions\VocabularyArtifactMissing;
use Schemastud\JsonNs\NamespaceScope;
use Schemastud\JsonNs\NamespaceUri;

/**
 * The store of validation vocabularies: namespace URI → the JSON-Schema (2020-12) a subtree
 * governed by that namespace must validate against (asset 05 §7). This is the framework-free
 * *port* the enforcement shim keys off — a host binds its own store behind it (the same shape
 * as the beam `SchemaTargetResolver` seam), so php-json-ns never depends UP into beam.
 *
 * Lookup is **version-aware**, and that is the whole SchemaLadderMigrator seam (§7 build item):
 * a namespace URI carries an optional trailing version (`.../3`, {@see NamespaceUri}). A
 * document that is mid-migration is *pinned to its own era's vocab URI* by the resolver, so we
 * must resolve THAT era's schema, not the latest. {@see schemaFor()} therefore tries the exact
 * (possibly pinned) URI first, and only then falls back to the version-free stem ("latest").
 * A migrating, older-era document keys straight onto its registered older-era vocab and is
 * never spuriously refused for failing a newer vocabulary it was never authored against.
 *
 * Keys are stored by raw URI string (URI-is-identity, §5): a stem and a pin are distinct keys.
 */
class VocabularyRegistry
{
    /** @var array<string, object|bool> raw-URI-string → decoded opis schema (object|bool) */
    private array $schemas;

    /**
     * The host-store seam (JN-16 / ADR-0192 §6): a lookup the host binds behind this port —
     * raw-URI-string → decoded opis schema (object|bool), or null when the store has nothing.
     * Consulted after the in-memory map, with the same exact-then-stem identity.
     *
     * @var (Closure(string): (object|bool|null))|null
     */
    private ?Closure $lookup;

    /**
     * Namespace URIs (stems or pins) the host DECLARES: an unresolved lookup for one of these
     * is a hard {@see VocabularyArtifactMissing}, never a permissive miss (JN-16).
     *
     * @var array<int, string>
     */
    private array $declared;

    /**
     * @param  array<string, object|bool>  $schemas
     * @param  (Closure(string): (object|bool|null))|null  $lookup
     * @param  array<int, string>  $declared
     */
    private function __construct(array $schemas, ?Closure $lookup = null, array $declared = [])
    {
        $this->schemas = $schemas;
        $this->lookup = $lookup;
        $this->declared = $declared;
    }

    public static function make(): self
    {
        return new self([]);
    }

    /**
     * A registry BACKED by a host store (JN-16): `$lookup` resolves a raw URI string against
     * the host's schema registry (the beam `SchemaRegistry` seam); `$declared` lists the
     * namespace URIs the host's manifest declares, whose absence from the store is a hard
     * error rather than an unknown-namespace pass.
     *
     * @param  Closure(string): (object|bool|null)  $lookup
     * @param  array<int, string>  $declared
     */
    public static function backedBy(Closure $lookup, array $declared = []): self
    {
        return new self([], $lookup, array_values(array_map('strval', $declared)));
    }

    /**
     * Register the vocab schema for a namespace URI. The schema is an opis-shaped decoded
     * JSON Schema (a `stdClass` object, or a bool for the always/never schema). A pinned URI
     * (`.../3`) and its version-free stem are independent registrations — register both if a
     * namespace both has a "latest" and pins historical eras.
     */
    public function register(string $uri, object|bool $schema): self
    {
        $clone = new self($this->schemas, $this->lookup, $this->declared);
        $clone->schemas[$uri] = $schema;

        return $clone;
    }

    /** Register from a JSON string (convenience for fixtures/tests). */
    public function registerJson(string $uri, string $json): self
    {
        return $this->register($uri, json_decode($json, false, flags: JSON_THROW_ON_ERROR));
    }

    /** Is any vocab (exact or stem) registered for this URI? Declared-but-missing reads as absent. */
    public function has(NamespaceUri $uri): bool
    {
        try {
            return $this->schemaFor($uri) !== null;
        } catch (VocabularyArtifactMissing) {
            return false;
        }
    }

    /**
     * The vocab schema to enforce for a resolved namespace URI, or null when nothing is
     * registered for it. **Version-aware resolution (the migrator seam):**
     *
     *   1. exact raw URI — a version-pinned, mid-migration era resolves to ITS era's vocab;
     *   2. the version-free stem — the "latest" fallback for an unpinned handle.
     *
     * Because a migrating document's scope resolves to its correct-era (pinned) URI upstream
     * (in {@see NamespaceScope}), step 1 lands it on its own vocab — it is
     * NOT judged against a newer stem vocabulary it never declared.
     */
    public function schemaFor(NamespaceUri $uri): object|bool|null
    {
        // 1. Exact (possibly pinned) URI — the mid-migration era's own vocab.
        if (array_key_exists((string) $uri, $this->schemas)) {
            return $this->schemas[(string) $uri];
        }

        // 2. Version-free stem — "latest" for an unpinned handle.
        if ($uri->isPinned() && array_key_exists($uri->stem(), $this->schemas)) {
            return $this->schemas[$uri->stem()];
        }

        // 3. The host store (JN-16), same exact-then-stem identity.
        if ($this->lookup !== null) {
            $found = ($this->lookup)((string) $uri);

            if ($found === null && $uri->isPinned()) {
                $found = ($this->lookup)($uri->stem());
            }

            if ($found !== null) {
                return $found;
            }
        }

        // 4. Declared-but-missing is a HARD error (JN-16): the host's manifest names this
        //    namespace, so an absent artifact is a deployment defect — silently passing would
        //    let a host that never ships the artifact report success on everything. Same
        //    exact-then-stem identity as steps 1–3: the stem inherits declaredness only for a
        //    PINNED handle (an era of the declared namespace). An UNPINNED URI whose last path
        //    segment merely sits under a declared namespace (`<base>/splice/grounding` under a
        //    declared `<base>/splice`) is a DIFFERENT namespace — unknown, not declared — and
        //    keeps the permissive unknown-namespace pass.
        if (in_array((string) $uri, $this->declared, true)
            || ($uri->isPinned() && in_array($uri->stem(), $this->declared, true))) {
            throw VocabularyArtifactMissing::for((string) $uri);
        }

        return null;
    }
}
