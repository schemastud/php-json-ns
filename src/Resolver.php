<?php

namespace Schemastud\JsonNs;

use Schemastud\JsonNs\Exceptions\UnboundPrefix;

/**
 * The public resolve API for the `@namespace` grammar (asset 05). Given a decoded JSON
 * document (nested arrays), it answers the two resolution questions the grammar owns:
 *
 *   - {@see resolveCurie()} — split a `prefix:local` key and resolve the prefix → URI in
 *     the `@namespace` lexically enclosing its use.
 *   - {@see scopeAt()} — the resolved {@see NamespaceScope} governing any JSON Pointer,
 *     applying the §4c precedence (CURIE key > nearest overlay > inherited overlay > base)
 *     and the §5 composition-safety rule (a subtree carries its own `@namespace`; nearer
 *     maps shadow outer ones).
 *
 * Resolution is **late + scope-local**: a prefix is interpreted only against the
 * `@namespace` enclosing its use, and identity is the resolved **URI** — so prefix
 * collisions across independently-authored subtrees cannot corrupt a binding. Alias-override
 * is additive: a second prefix bound to the same URI is the same namespace (handled by
 * {@see NamespaceMap} — the URI, not the prefix, is compared).
 *
 * This is the grammar + resolution + scoping model ONLY — no handler dispatch (ticket 06),
 * no validation enforcement engine (§7 build handoff).
 */
class Resolver
{
    /** @var array<string, mixed> the decoded document root */
    private array $document;

    /**
     * @param  array<string, mixed>  $document  a decoded JSON document (objects as arrays)
     */
    public function __construct(array $document)
    {
        $this->document = $document;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public static function for(array $document): self
    {
        return new self($document);
    }

    // ---------------------------------------------------------------------
    // CURIE resolution (data-key axis, §4a)
    // ---------------------------------------------------------------------

    /**
     * Resolve a compact CURIE key (`prefix:local`) used at the given JSON Pointer to its
     * full namespace URI, interpreting the prefix through the `@namespace` lexically
     * enclosing that pointer. Returns null when the key is not CURIE-shaped.
     *
     * @param  string  $key  the raw key, e.g. "splice:grounding"
     * @param  string  $pointer  JSON Pointer of the NODE that carries the key (default: root)
     *
     * @throws UnboundPrefix when the prefix is bound nowhere in the enclosing scope
     */
    public function resolveCurie(string $key, string $pointer = ''): ?NamespaceUri
    {
        $curie = Curie::tryParse($key);

        if ($curie === null) {
            return null;
        }

        $map = $this->effectiveMapAt($pointer);
        $uri = $map->resolve($curie->prefix());

        if ($uri === null) {
            throw UnboundPrefix::at($curie->prefix(), $pointer);
        }

        return $uri;
    }

    /**
     * The effective (merged) `@namespace` map in force at a JSON Pointer: every enclosing
     * scope's `@namespace`, outer-to-inner, with nearer maps shadowing outer ones for a
     * shared prefix (§5 late scope-local resolution). A subtree's own `@namespace` therefore
     * always wins for its own keys — the composition-safety property.
     */
    public function effectiveMapAt(string $pointer): NamespaceMap
    {
        $map = NamespaceMap::empty();

        foreach ($this->nodesAlong($pointer) as $node) {
            if (is_array($node)) {
                $map = $map->overlay(NamespaceMap::fromNode($node));
            }
        }

        return $map;
    }

    // ---------------------------------------------------------------------
    // Scope resolution (§4c precedence + §7 validation-scope model)
    // ---------------------------------------------------------------------

    /**
     * The resolved namespace scope governing the node at a JSON Pointer. This is the model a
     * later validation ticket enforces against — a subtree's validation-scope == its
     * namespace-scope (§7).
     *
     * Precedence (§4c), tightest to loosest:
     *   1. an explicit CURIE key on the node's own key — always wins;
     *   2. the nearest `@namespaced` overlay governing the node (deeper scope shadows);
     *   3. an inherited (ancestor) `@namespaced` default;
     *   4. the base/commons default (unprefixed → base vocabulary).
     */
    public function scopeAt(string $pointer): NamespaceScope
    {
        // (1) A CURIE on the node's own leaf key is the node's own declaration.
        $leaf = self::leafKey($pointer);

        if ($leaf !== null) {
            $curie = Curie::tryParse($leaf);

            if ($curie !== null) {
                $parentPointer = self::parentPointer($pointer);
                $map = $this->effectiveMapAt($parentPointer);
                $uri = $map->resolve($curie->prefix());

                if ($uri === null) {
                    throw UnboundPrefix::at($curie->prefix(), $pointer);
                }

                return NamespaceScope::curie($pointer, $uri, $curie->prefix());
            }
        }

        // (2)/(3) The nearest enclosing @namespaced overlay governing this pointer.
        $governing = $this->nearestOverlay($pointer);

        if ($governing !== null) {
            [$prefix, $carrierPointer] = $governing;
            // The prefix is resolved in the @namespace enclosing the OVERLAY's carrier —
            // late + scope-local, so the overlay travels with its subtree.
            $map = $this->effectiveMapAt($carrierPointer);
            $uri = $map->resolve($prefix);

            if ($uri === null) {
                throw UnboundPrefix::at($prefix, $carrierPointer);
            }

            return NamespaceScope::overlay($pointer, $uri, $prefix);
        }

        // (4) Base/commons default — unprefixed.
        return NamespaceScope::base($pointer);
    }

    /**
     * Find the nearest `@namespaced` overlay governing the pointer, walking from the pointer
     * up to the root. "Nearest" combines two axes: an overlay carried on a deeper node wins
     * over one on a shallower node; within one carrier, a deeper `scope` wins (§4c).
     *
     * @return array{0: string, 1: string}|null [prefix, carrierPointer] or null
     */
    private function nearestOverlay(string $pointer): ?array
    {
        $carrier = $pointer;

        // Walk carriers from the node itself up to the root (deepest carrier first).
        while (true) {
            $node = $this->nodeAt($carrier);

            if (is_array($node) && isset($node[Vocabulary::Namespaced])) {
                $overlays = Overlay::desugar($node[Vocabulary::Namespaced]);
                $relative = self::relativePointer($carrier, $pointer);

                $best = null;

                foreach ($overlays as $overlay) {
                    if ($overlay->governs($relative)) {
                        if ($best === null || $overlay->depth() > $best->depth()) {
                            $best = $overlay;
                        }
                    }
                }

                if ($best !== null) {
                    return [$best->namespace(), $carrier];
                }
            }

            if ($carrier === '') {
                break;
            }

            $carrier = self::parentPointer($carrier);
        }

        return null;
    }

    // ---------------------------------------------------------------------
    // JSON Pointer plumbing (RFC 6901, minimal)
    // ---------------------------------------------------------------------

    /**
     * The chain of nodes from the root down to (and including) the node at the pointer.
     *
     * @return array<int, mixed>
     */
    private function nodesAlong(string $pointer): array
    {
        $nodes = [$this->document];
        $node = $this->document;

        foreach (self::segments($pointer) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                break;
            }
            $node = $node[$segment];
            $nodes[] = $node;
        }

        return $nodes;
    }

    /** The node at a JSON Pointer, or null when the path does not resolve. */
    private function nodeAt(string $pointer): mixed
    {
        $node = $this->document;

        foreach (self::segments($pointer) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }

        return $node;
    }

    /** @return array<int, string> */
    private static function segments(string $pointer): array
    {
        if ($pointer === '' || $pointer === '/') {
            return [];
        }

        $parts = explode('/', ltrim($pointer, '/'));

        return array_map(static fn (string $p): string => str_replace(['~1', '~0'], ['/', '~'], $p), $parts);
    }

    private static function leafKey(string $pointer): ?string
    {
        $segments = self::segments($pointer);

        return $segments === [] ? null : end($segments);
    }

    private static function parentPointer(string $pointer): string
    {
        $segments = self::segments($pointer);
        array_pop($segments);

        if ($segments === []) {
            return '';
        }

        return '/'.implode('/', array_map(
            static fn (string $p): string => str_replace(['~', '/'], ['~0', '~1'], $p),
            $segments,
        ));
    }

    /** The portion of $pointer relative to (below) $carrier, "" when they are equal. */
    private static function relativePointer(string $carrier, string $pointer): string
    {
        if ($carrier === '') {
            return $pointer;
        }

        if ($pointer === $carrier) {
            return '';
        }

        return substr($pointer, strlen($carrier));
    }
}
