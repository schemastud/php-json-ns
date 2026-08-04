<?php

namespace Schemastud\JsonNs;

/**
 * A parsed `@namespace` declaration: a prefix→URI map, the CURIE analogue of JSON-LD's
 * `@context` narrowed to prefix binding (asset 05 §3). Values are {@see NamespaceUri}
 * (`SchemaId`-shaped). Prefixes are the vendor-seam tiers (`frame`/`beam`/`splice`); base
 * and commons stay unprefixed.
 *
 * The map is a *local alias table*: the URI is identity (§5), the prefix is a local name
 * for it. Two prefixes → same URI ⇒ same namespace. Alias-override is therefore additive:
 * re-binding a second prefix to the same URI adds a name, it never renames the first
 * binding (there is no "rename someone else's prefix" operation).
 */
class NamespaceMap
{
    /** @var array<string, NamespaceUri> prefix → URI */
    private array $bindings;

    /**
     * @param  array<string, NamespaceUri>  $bindings
     */
    private function __construct(array $bindings)
    {
        $this->bindings = $bindings;
    }

    /**
     * Parse the `@namespace` prefix→URI object into a binding map. A missing or empty
     * `@namespace` yields an empty map (legacy schemas fall back to keyword auto-detection,
     * which is out of this core's scope — §6).
     *
     * @param  array<string, mixed>  $node  the JSON node carrying (maybe) an `@namespace`
     */
    public static function fromNode(array $node): self
    {
        $raw = $node[Vocabulary::Namespace] ?? [];

        return self::fromRaw(is_array($raw) ? $raw : []);
    }

    /**
     * @param  array<string, mixed>  $raw  the raw prefix→uri-string object
     */
    public static function fromRaw(array $raw): self
    {
        $bindings = [];

        foreach ($raw as $prefix => $uri) {
            if (is_string($prefix) && is_string($uri)) {
                $bindings[$prefix] = NamespaceUri::from($uri);
            }
        }

        return new self($bindings);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /** Does this map declare the given prefix? */
    public function has(string $prefix): bool
    {
        return isset($this->bindings[$prefix]);
    }

    /** Resolve a prefix to its URI, or null when the prefix is unbound in this map. */
    public function resolve(string $prefix): ?NamespaceUri
    {
        return $this->bindings[$prefix] ?? null;
    }

    /**
     * Overlay another map on top of this one — the inner (nearer) map shadows the outer for
     * any shared prefix (late scope-local resolution, §5). Used to build the effective map
     * at a node from its lexically-enclosing scopes.
     */
    public function overlay(self $inner): self
    {
        return new self([...$this->bindings, ...$inner->bindings]);
    }

    /** @return array<string, NamespaceUri> */
    public function all(): array
    {
        return $this->bindings;
    }

    public function isEmpty(): bool
    {
        return $this->bindings === [];
    }
}
