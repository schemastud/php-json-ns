<?php

namespace Schemastud\JsonNs\Vocab;

/**
 * Required-vs-optional vocabulary refusal semantics (asset 05 §7 build-time item — decided at
 * BUILD, not in the map). JSON Schema 2020-12 gives `$vocabulary` a native `true`/`false` per
 * URI: `true` = REQUIRED (an implementation that does not understand it MUST refuse to process
 * the schema), `false` = OPTIONAL (understand it if you can; otherwise proceed). We mirror that
 * standard bit onto the *store side* of the enforcement seam:
 *
 *   - {@see Required}: a subtree resolves to a namespace URI whose vocab is UNKNOWN to the
 *     registry → HARD REFUSAL. The author asked to be validated against a vocabulary we cannot
 *     supply; per 2020-12 `$vocabulary: true`, silently passing would be a false "valid".
 *
 *   - {@see Optional}: an unknown/unregistered vocab is a PERMISSIVE PASS. Nothing to enforce,
 *     so nothing is refused (2020-12 `$vocabulary: false`).
 *
 * ── The decision, and why ──────────────────────────────────────────────────────────────────
 * DEFAULT = {@see Optional} (permissive pass on an unknown vocab). Rationale, three reasons,
 * all pulling the same way:
 *
 *   (1) It composes with migrate-on-read (§7's second build item). During a
 *       SchemaLadderMigrator pass a subtree may momentarily resolve to an era whose vocab is
 *       not yet (or no longer) in this process's registry. A hard default would turn a benign
 *       mid-migration window into a spurious refusal — exactly the failure §7 tells us to avoid.
 *       Permissive-by-default keeps the migrator seam non-brittle.
 *
 *   (2) It matches JSON Schema 2020-12's OWN default posture: the meta-schema declares the
 *       standard vocabularies with `true`, but any vocabulary a validator has never heard of is
 *       simply not applied — unknown ≠ invalid. We inherit that, and let an author OPT IN to
 *       strictness by declaring the URI Required.
 *
 *   (3) An *ABSENT* namespace (the base/commons scope, no URI at all — §4c.4) is ALWAYS a pass
 *       under either policy: there is no vocabulary to validate against, so there is nothing to
 *       refuse. "Absent" and "unknown-but-optional" both pass; only "unknown-and-required"
 *       refuses. This keeps the common legacy/unprefixed case frictionless.
 *
 * A caller that wants fail-closed strictness ({@see Required}) opts into it explicitly — the
 * strict posture is available, it is just not the silent default.
 */
enum RefusalPolicy
{
    /** Unknown/unregistered vocabulary → hard refusal (2020-12 `$vocabulary: true`). */
    case Required;

    /** Unknown/unregistered vocabulary → permissive pass (2020-12 `$vocabulary: false`). DEFAULT. */
    case Optional;

    public function isRequired(): bool
    {
        return $this === self::Required;
    }
}
