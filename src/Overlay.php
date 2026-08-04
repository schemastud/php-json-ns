<?php

namespace Schemastud\JsonNs;

/**
 * One parsed `@namespaced` entry — "namespace this subtree" (asset 05 §4b). It re-scopes
 * keys the carrier does NOT own (plain unprefixed keys authored by someone else) to a
 * prefix *without rewriting* them to CURIE form — the `@vocab`-scoped-to-a-subtree move.
 *
 * Two surface forms desugar to the same shape:
 *   - String shorthand (inline): `"@namespaced": "splice"` ≡ one entry,
 *     `{ scope: "", namespace: "splice" }` — the whole subtree rooted at the carrier.
 *   - Array long form (sidecar): each entry `{ scope, namespace }`.
 *
 * `scope` is a JSON Pointer **relative to the node carrying the `@namespaced`**, so the
 * block travels correctly when its subtree is `$ref`'d / dereferenced / overlaid — the
 * scope does not break when the node moves.
 */
class Overlay
{
    /** JSON Pointer relative to the carrying node ("" == the carrier itself). */
    private string $scope;

    private string $namespace;

    private function __construct(string $scope, string $namespace)
    {
        $this->scope = $scope;
        $this->namespace = $namespace;
    }

    /**
     * Desugar a raw `@namespaced` value (string shorthand OR sidecar array) into a list of
     * overlay entries. Anything malformed is skipped rather than throwing — the grammar is
     * tolerant at parse time; enforcement is a build handoff (§7).
     *
     * @return array<int, self>
     */
    public static function desugar(mixed $raw): array
    {
        // String shorthand → single entry over the whole carrier subtree.
        if (is_string($raw)) {
            return [new self('', $raw)];
        }

        if (! is_array($raw)) {
            return [];
        }

        $entries = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $namespace = $entry['namespace'] ?? null;

            if (! is_string($namespace) || $namespace === '') {
                continue;
            }

            $scope = $entry['scope'] ?? '';
            $scope = is_string($scope) ? $scope : '';

            $entries[] = new self(self::normalizeScope($scope), $namespace);
        }

        return $entries;
    }

    /**
     * A relative JSON Pointer canonicalized: "" / "/" mean the carrier itself; otherwise a
     * single leading "/" is ensured (an authored "a/b" and "/a/b" are the same relative
     * pointer) and any trailing "/" is dropped.
     */
    private static function normalizeScope(string $scope): string
    {
        if ($scope === '' || $scope === '/') {
            return '';
        }

        return '/'.trim($scope, '/');
    }

    /** The JSON Pointer (relative to the carrier) this overlay governs. */
    public function scope(): string
    {
        return $this->scope;
    }

    /** The prefix to which the scoped subtree's unowned keys are bound. */
    public function namespace(): string
    {
        return $this->namespace;
    }

    /**
     * Does this overlay's scope govern the pointer `$relative` (relative to the same
     * carrier)? An overlay at "" governs everything; an overlay at "/a" governs "/a",
     * "/a/b", … but not "/ab".
     */
    public function governs(string $relative): bool
    {
        if ($this->scope === '') {
            return true;
        }

        return $relative === $this->scope
            || str_starts_with($relative, $this->scope.'/');
    }

    /** Depth of the scope pointer — deeper (longer) scopes are nearer and shadow (§4c). */
    public function depth(): int
    {
        return $this->scope === '' ? 0 : substr_count($this->scope, '/');
    }
}
