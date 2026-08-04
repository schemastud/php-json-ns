<?php

use Schemastud\JsonNs\Vocab\RefusalPolicy;
use Schemastud\JsonNs\Vocab\VocabularyRegistry;
use Schemastud\JsonNs\Vocab\VocabularyValidator;

// Coverage 6 (JN-05): the opis `$vocabulary` enforcement shim. opis/json-schema parses
// `$vocabulary` but does not APPLY it; this shim does, driven by php-json-ns's scoping model
// (Resolver::scopeAt → NamespaceScope::validationVocabulary). The seam is the custom opis
// keyword (VocabularyKeyword + VocabularyKeywordParser) wired through VocabularyValidator.

const GROUNDING = 'https://schemas.splicewire.app/splice/grounding';

function groundingRegistry(): VocabularyRegistry
{
    return VocabularyRegistry::make()
        ->registerJson(GROUNDING, fixtureJson('vocab/grounding.schema'));
}

it('passes a subtree that CONFORMS to its $vocabulary', function () {
    $validator = new VocabularyValidator(groundingRegistry());

    expect($validator->passes(fixture('vocab-conforms')))->toBeTrue()
        ->and($validator->validate(fixture('vocab-conforms')))->toBe([]);
});

it('refuses a subtree that VIOLATES its $vocabulary', function () {
    $validator = new VocabularyValidator(groundingRegistry());

    $errors = $validator->validate(fixture('vocab-violates'));

    // `sources` missing + `max_depth` over-max → the namespace vocabulary refuses the subtree.
    expect($errors)->not->toBe([])
        ->and($validator->passes(fixture('vocab-violates')))->toBeFalse()
        ->and($errors[0]->keyword())->toBe('$vocabulary');
});

// ── §7 edge case 1: required-vs-optional refusal semantics ──────────────────────────────────
// An unknown/unregistered vocabulary. DEFAULT = Optional → permissive pass; Required → refuse.

it('§7-edge-1: passes an UNKNOWN vocabulary under the default Optional policy', function () {
    // Registry knows grounding only; the `mystery:` namespace is unregistered.
    $validator = new VocabularyValidator(groundingRegistry());

    expect($validator->passes(fixture('vocab-unknown')))->toBeTrue();
});

it('§7-edge-1: REFUSES the same unknown vocabulary under an explicit Required policy', function () {
    $validator = new VocabularyValidator(groundingRegistry(), RefusalPolicy::Required);

    $errors = $validator->validate(fixture('vocab-unknown'));

    expect($errors)->not->toBe([])
        ->and($errors[0]->keyword())->toBe('$vocabulary');
});

it('§7-edge-1: an ABSENT namespace (base scope) always passes — nothing to refuse', function () {
    // `declaration` has only an @namespace decl and no namespaced subtree data.
    $required = new VocabularyValidator(groundingRegistry(), RefusalPolicy::Required);

    expect($required->passes(fixture('declaration')))->toBeTrue();
});

// ── §7 edge case 2: refusal × migrate-on-read (SchemaLadderMigrator seam) ────────────────────
// A document pinned to an OLDER era (`.../grounding/2`) with the old field shape must NOT be
// refused against the NEWER stem vocabulary — it keys onto its own era's vocab via the pinned
// URI the scope resolves. This models the migrator seam WITHOUT depending up into beam.

it('§7-edge-2: a version-pinned older-era document is NOT spuriously refused by the newer vocab', function () {
    // Both eras registered: the new stem (requires `sources`) AND the pinned v2 (wants `context`).
    $registry = VocabularyRegistry::make()
        ->registerJson(GROUNDING, fixtureJson('vocab/grounding.schema'))         // latest stem
        ->registerJson(GROUNDING.'/2', fixtureJson('vocab/grounding-v2.schema')); // pinned era

    $validator = new VocabularyValidator($registry);

    // The migrating doc uses the OLD `context` field and pins `.../grounding/2`. Under the new
    // stem vocab it would fail (missing `sources`); keyed to its pinned era it conforms.
    expect($validator->passes(fixture('vocab-migrating')))->toBeTrue();
});

it('§7-edge-2: control — the same older-era shape DOES fail the newer stem vocab (proving the pin matters)', function () {
    // Only the NEW stem is registered; a v2-shaped doc pinned to v2 falls back to stem → refused.
    // (Guards that the pin, not a permissive default, is what saved the migrating doc above.)
    $registry = VocabularyRegistry::make()
        ->registerJson(GROUNDING, fixtureJson('vocab/grounding.schema'));

    $validator = new VocabularyValidator($registry);

    // vocab-migrating pins `/2`; with no `/2` registered it falls to the stem vocab, which
    // requires `sources` the old-shape doc lacks → refused. This is the negative control.
    expect($validator->passes(fixture('vocab-migrating')))->toBeFalse();
});
