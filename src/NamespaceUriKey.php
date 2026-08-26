<?php

namespace Schemastud\JsonNs;

use Rushing\Popcorn\Registries\AbsoluteUriKey;
use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\RegistryKey;

/**
 * A namespace URI as a popcorn {@see RegistryKey} — this package's own key type, which is the one
 * thing registry-kernel ticket 05's amendment sanctions and deliberately makes rare.
 *
 * ## Why json-ns earns one and almost nothing else does
 *
 * Because URI-is-identity is this package's whole thesis and it PREDATES popcorn: a CURIE key and an
 * `@namespaced` object that resolve to the same URI must hit the same registration (asset 05 §5). A
 * dotted key with the URI demoted to an alias would relocate that identity into a lookup table — which
 * is precisely the drift the doctrine exists to forbid.
 *
 * `RegistryKey` is a SEAM, not a grammar: the kernel COMPARES and JOINS segments and never parses one,
 * so a key type is CONSTRUCTED, never parsed, and popcorn's `[a-z0-9-]` charset governs only
 * {@see Key} — the canonical implementation for keys written as string literals. A URI's `:` and `/`
 * would fail that charset outright, and it does not have to survive it.
 *
 * What this class still owns after ticket 64 is exactly the pin rule below. Everything generic about
 * an absolute-URI key — one opaque segment, segment equality, the URI as rendering, the refusal to
 * root-stamp — moved down into {@see AbsoluteUriKey} in `rushing/php-popcorn`, which this extends. The
 * dependency edge is not new: `php-json-ns` already requires the kernel (registry-kernel ticket 56).
 *
 * ## The pin is a CHILD of its stem
 *
 * `https://…/grounding/2` segments as `['https://…/grounding', '2']`, which makes the version pin
 * structurally a child of the version-free stem. That is not decoration: it is what expresses the
 * dispatcher's pinned-then-stem fallback (asset 05 §8, asset 06 §7.1) as ordinary tree structure
 * instead of string surgery, and what lets `children()` enumerate the versions bound under a stem.
 *
 * ## The consequence to design around
 *
 * These entries are **not addressable through the global keyspace** — reachable through a namespace
 * registry and through {@see \Schemastud\JsonNs\Laravel\NamespaceDispatcher}, never through
 * `Popcorn::pop()` (registry-kernel ticket 20 D3, narrowed by ticket 64). That is the trade
 * URI-is-identity buys, taken deliberately.
 *
 * ⚠️ **The reason changed on 2026-08-26 while the behaviour did not**, and the distinction is worth
 * keeping straight. It used to be *"a foreign key is never stamped, because the kernel cannot construct
 * a consumer's key type."* Ticket 64 widened {@see \Rushing\Popcorn\Registries\BasicRegistry}'s door
 * from `instanceof Key` to {@see \Rushing\Popcorn\Registries\Rootable}, so the kernel now CAN address
 * types it ships — and this key's base, {@see AbsoluteUriKey}, is one of them. It stays unstamped
 * because that base **declines**: a dotted root over one opaque URI segment addresses nothing. So the
 * property this package relies on is no longer an incapacity it inherits by being a stranger; it is a
 * decision the kernel makes openly, in a type that could do otherwise.
 *
 * ## Why it lives HERE and not in the Laravel binding
 *
 * It shipped in `schemastud/laravel-json-ns` and imported exactly two framework-free things
 * ({@see RegistryKey}, {@see NamespaceUri}) — no container, no facade, no Laravel anything. The cost was
 * not cosmetic: `schemastud/laravel-data-schemas` requires `php-json-ns` and `rushing/php-popcorn` but
 * **not** `laravel-json-ns`, so the key type its schema family is meant to key on was unreachable without
 * a new Laravel dependency edge. Moved down a tier instead (registry-kernel ticket 56); the price is this
 * package's one dependency on the registry kernel, which is itself framework-free.
 */
class NamespaceUriKey extends AbsoluteUriKey
{
    /**
     * The one override, and the one thing about this key that is json-ns-specific: a {@see NamespaceUri}
     * is validated and rendered by THIS package, and a pinned version segments as a child of its stem.
     *
     * Deliberately does not call `parent::of()`. That parser exists to reject a relative path arriving
     * where an absolute URI was meant; a `NamespaceUri` has already been through a stricter gate than
     * "has a scheme", so routing through it would be a second, looser opinion about what this
     * package's identities are.
     *
     * ⚠️ The parameter type WIDENS the parent's `string`, which PHP permits and which is the point —
     * a `NamespaceUri` may be handed over already parsed. It does not narrow anything.
     */
    public static function of(string|NamespaceUri $uri): static
    {
        $parsed = $uri instanceof NamespaceUri ? $uri : NamespaceUri::from((string) $uri);

        return new static(
            $parsed->isPinned() ? [$parsed->stem(), (string) $parsed->version()] : [(string) $parsed],
            (string) $parsed,
        );
    }
}
