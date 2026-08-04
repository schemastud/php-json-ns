<?php

namespace Schemastud\JsonNs;

/**
 * The resolved namespace scope of a node — what {@see Resolver::scopeAt()} yields for a
 * given JSON Pointer. It is the single object a later validation ticket enforces against:
 * asset 05 §7 fixes that a subtree's *validation-scope* is exactly its *namespace-scope*,
 * so the same object answers both "which namespace governs this node?" and "which
 * `$vocabulary` must this subtree validate against?".
 *
 * This core exposes the model; it runs NO enforcement engine (the opis `$vocabulary` shim
 * is a build handoff, §7).
 */
class NamespaceScope
{
    private string $pointer;

    private ?NamespaceUri $uri;

    private ?string $prefix;

    private string $source;

    /** @param 'curie'|'overlay'|'base' $source */
    private function __construct(string $pointer, ?NamespaceUri $uri, ?string $prefix, string $source)
    {
        $this->pointer = $pointer;
        $this->uri = $uri;
        $this->prefix = $prefix;
        $this->source = $source;
    }

    /** A node governed by an explicit CURIE key — the tightest binding (§4c.1). */
    public static function curie(string $pointer, NamespaceUri $uri, string $prefix): self
    {
        return new self($pointer, $uri, $prefix, 'curie');
    }

    /** A node governed by the nearest `@namespaced` overlay (§4c.2/3). */
    public static function overlay(string $pointer, NamespaceUri $uri, string $prefix): self
    {
        return new self($pointer, $uri, $prefix, 'overlay');
    }

    /** A node governed by nothing — the base/commons default, unprefixed (§4c.4). */
    public static function base(string $pointer): self
    {
        return new self($pointer, null, null, 'base');
    }

    /** The absolute JSON Pointer this scope was resolved for. */
    public function pointer(): string
    {
        return $this->pointer;
    }

    /**
     * The governing namespace URI — the identity (§5). Null means the base/commons default
     * (unprefixed, no declared namespace).
     */
    public function uri(): ?NamespaceUri
    {
        return $this->uri;
    }

    /**
     * The prefix (local alias) that resolved to {@see uri()} at the bind site. Diagnostic
     * only — identity is the URI, never the prefix.
     */
    public function prefix(): ?string
    {
        return $this->prefix;
    }

    /** How the scope was determined: 'curie' | 'overlay' | 'base'. */
    public function source(): string
    {
        return $this->source;
    }

    /** True when the node falls to the unprefixed base/commons vocabulary. */
    public function isBase(): bool
    {
        return $this->uri === null;
    }

    /**
     * The `$vocabulary` identity a subtree in this scope validates against (§7). A namespace
     * *may* associate its URI with a validation vocabulary; the URI IS that identity here.
     * Null for the base scope. Enforcement is a build handoff — this only reports the binding.
     */
    public function validationVocabulary(): ?NamespaceUri
    {
        return $this->uri;
    }
}
