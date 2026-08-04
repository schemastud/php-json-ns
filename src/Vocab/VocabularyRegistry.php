<?php

namespace Schemastud\JsonNs\Vocab;

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
     * @param  array<string, object|bool>  $schemas
     */
    private function __construct(array $schemas)
    {
        $this->schemas = $schemas;
    }

    public static function make(): self
    {
        return new self([]);
    }

    /**
     * Register the vocab schema for a namespace URI. The schema is an opis-shaped decoded
     * JSON Schema (a `stdClass` object, or a bool for the always/never schema). A pinned URI
     * (`.../3`) and its version-free stem are independent registrations — register both if a
     * namespace both has a "latest" and pins historical eras.
     */
    public function register(string $uri, object|bool $schema): self
    {
        $clone = new self($this->schemas);
        $clone->schemas[$uri] = $schema;

        return $clone;
    }

    /** Register from a JSON string (convenience for fixtures/tests). */
    public function registerJson(string $uri, string $json): self
    {
        return $this->register($uri, json_decode($json, false, flags: JSON_THROW_ON_ERROR));
    }

    /** Is any vocab (exact or stem) registered for this URI? */
    public function has(NamespaceUri $uri): bool
    {
        return $this->schemaFor($uri) !== null;
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

        return null;
    }
}
