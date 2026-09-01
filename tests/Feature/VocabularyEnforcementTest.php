<?php

use Opis\JsonSchema\Validator;
use Schemastud\JsonNs\Exceptions\VocabularyArtifactMissing;
use Schemastud\JsonNs\NamespaceUri;
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

    expect($validator->passes(fixtureData('vocab-conforms')))->toBeTrue()
        ->and($validator->validate(fixtureData('vocab-conforms')))->toBe([]);
});

it('refuses a subtree that VIOLATES its $vocabulary', function () {
    $validator = new VocabularyValidator(groundingRegistry());

    $errors = $validator->validate(fixtureData('vocab-violates'));

    // `sources` missing + `max_depth` over-max → the namespace vocabulary refuses the subtree.
    expect($errors)->not->toBe([])
        ->and($validator->passes(fixtureData('vocab-violates')))->toBeFalse()
        ->and($errors[0]->keyword())->toBe('$vocabulary');
});

// ── §7 edge case 1: required-vs-optional refusal semantics ──────────────────────────────────
// An unknown/unregistered vocabulary. DEFAULT = Optional → permissive pass; Required → refuse.

it('§7-edge-1: passes an UNKNOWN vocabulary under the default Optional policy', function () {
    // Registry knows grounding only; the `mystery:` namespace is unregistered.
    $validator = new VocabularyValidator(groundingRegistry());

    expect($validator->passes(fixtureData('vocab-unknown')))->toBeTrue();
});

it('§7-edge-1: REFUSES the same unknown vocabulary under an explicit Required policy', function () {
    $validator = new VocabularyValidator(groundingRegistry(), RefusalPolicy::Required);

    $errors = $validator->validate(fixtureData('vocab-unknown'));

    expect($errors)->not->toBe([])
        ->and($errors[0]->keyword())->toBe('$vocabulary');
});

it('§7-edge-1: an ABSENT namespace (base scope) always passes — nothing to refuse', function () {
    // `declaration` has only an @namespace decl and no namespaced subtree data.
    $required = new VocabularyValidator(groundingRegistry(), RefusalPolicy::Required);

    expect($required->passes(fixtureData('declaration')))->toBeTrue();
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
    expect($validator->passes(fixtureData('vocab-migrating')))->toBeTrue();
});

it('§7-edge-2: control — the same older-era shape DOES fail the newer stem vocab (proving the pin matters)', function () {
    // Only the NEW stem is registered; a v2-shaped doc pinned to v2 falls back to stem → refused.
    // (Guards that the pin, not a permissive default, is what saved the migrating doc above.)
    $registry = VocabularyRegistry::make()
        ->registerJson(GROUNDING, fixtureJson('vocab/grounding.schema'));

    $validator = new VocabularyValidator($registry);

    // vocab-migrating pins `/2`; with no `/2` registered it falls to the stem vocab, which
    // requires `sources` the old-shape doc lacks → refused. This is the negative control.
    expect($validator->passes(fixtureData('vocab-migrating')))->toBeFalse();
});

// ── JN-17: per-URI refusal semantics — the AUTHORED `$vocabulary` map's booleans are read ────
// 2020-12 defines `$vocabulary` as a URI→bool map (true = required: an implementation that
// cannot supply the vocabulary MUST refuse; false = optional: proceed). The authored bit wins
// per URI; the instance-wide RefusalPolicy decides only for URIs the author declared no bit for.

it('JN-17: an authored `true` REFUSES an unknown vocabulary even under the default Optional policy', function () {
    // Registry knows grounding only; the mystery vocab is unknown — but the document itself
    // declares it required, so the permissive instance default must NOT swallow the refusal.
    $validator = new VocabularyValidator(groundingRegistry());

    $errors = $validator->validate(fixtureData('vocab-declared-required'));

    expect($errors)->not->toBe([])
        ->and($errors[0]->keyword())->toBe('$vocabulary');
});

it('JN-17: an authored `false` PASSES an unknown vocabulary even under an instance-wide Required policy', function () {
    // The author explicitly marked the vocabulary optional; the strict instance default only
    // governs URIs with no authored bit.
    $validator = new VocabularyValidator(groundingRegistry(), RefusalPolicy::Required);

    expect($validator->passes(fixtureData('vocab-declared-optional')))->toBeTrue();
});

it('JN-17: a URI absent from the authored map still follows the instance-wide policy', function () {
    // The authored map declares only an unrelated URI; the mystery namespace keeps default
    // (Optional → pass, Required → refuse) semantics.
    $doc = fixtureData('vocab-unknown');
    $doc['$vocabulary'] = ['https://schemas.example.com/other/thing' => true];

    expect((new VocabularyValidator(groundingRegistry()))->passes($doc))->toBeTrue()
        ->and((new VocabularyValidator(groundingRegistry(), RefusalPolicy::Required))->passes($doc))->toBeFalse();
});

it('JN-17: a KNOWN vocabulary is still enforced regardless of its authored bit', function () {
    // `false` means "proceed if you cannot supply it" — it never disables enforcement of a
    // vocabulary the registry CAN supply.
    $doc = fixtureData('vocab-violates');
    $doc['$vocabulary'] = [GROUNDING => false];

    expect((new VocabularyValidator(groundingRegistry()))->passes($doc))->toBeFalse();
});

it('JN-17: a pinned document era inherits its stem\'s authored bit', function () {
    // vocab-migrating pins `.../grounding/2`; nothing is registered at all, and the authored map
    // declares the version-free STEM required — the pinned URI resolves that bit via the same
    // exact-then-stem identity the registry uses.
    $doc = fixtureData('vocab-migrating');
    $doc['$vocabulary'] = [GROUNDING => true];

    $errors = (new VocabularyValidator(VocabularyRegistry::make()))->validate($doc);

    expect($errors)->not->toBe([])
        ->and($errors[0]->keyword())->toBe('$vocabulary');
});

// ── JN-16: the host-store seam — a registry BACKED by a host lookup, with declared-set guard ─
// `VocabularyRegistry::backedBy($lookup, $declared)`: the lookup resolves against the host's
// schema registry (exact URI then version-free stem); a DECLARED namespace with no artifact
// behind it is a hard VocabularyArtifactMissing — distinct from unknown-namespace, which stays
// a permissive pass under RefusalPolicy::Optional.

it('JN-16: a backed registry resolves through the host lookup, exact then stem', function () {
    $store = [
        GROUNDING => json_decode(fixtureJson('vocab/grounding.schema'), false),
    ];

    $registry = VocabularyRegistry::backedBy(
        fn (string $uri) => $store[$uri] ?? null,
    );

    // Exact stem hit; a pinned handle with no pinned entry falls to the stem.
    expect($registry->schemaFor(NamespaceUri::from(GROUNDING)))->not->toBeNull()
        ->and($registry->schemaFor(NamespaceUri::from(GROUNDING.'/7')))->not->toBeNull()
        ->and($registry->schemaFor(NamespaceUri::from('https://schemas.example.com/other/ns')))->toBeNull();

    // The whole validator path works over a backed registry.
    expect((new VocabularyValidator($registry))->passes(fixtureData('vocab-conforms')))->toBeTrue()
        ->and((new VocabularyValidator($registry))->passes(fixtureData('vocab-violates')))->toBeFalse();
});

it('JN-16: a DECLARED namespace with no artifact fails loudly; an unknown one still passes', function () {
    $registry = VocabularyRegistry::backedBy(
        fn (string $uri) => null,                                   // the host store is empty
        declared: ['https://schemas.example.com/mystery/thing'],    // …but the manifest declares it
    );

    // Declared-but-missing → hard error, even under the permissive default policy.
    expect(fn () => (new VocabularyValidator($registry))->validate(fixtureData('vocab-unknown')))
        ->toThrow(VocabularyArtifactMissing::class);

    // has() reads declared-but-missing as absent rather than throwing.
    expect($registry->has(NamespaceUri::from('https://schemas.example.com/mystery/thing')))->toBeFalse();

    // An entirely UNDECLARED namespace keeps the permissive unknown-namespace pass.
    $undeclared = VocabularyRegistry::backedBy(fn (string $uri) => null);
    expect((new VocabularyValidator($undeclared))->passes(fixtureData('vocab-unknown')))->toBeTrue();
});

it('JN-16: an UNPINNED namespace one segment under a declared one is unknown, not declared', function () {
    // `<base>/splice` is declared; `<base>/splice/grounding` is a DIFFERENT namespace whose last
    // path segment merely sits under it. Only a PINNED era (`<base>/splice/3`) inherits the
    // declared stem's bit — the unpinned sub-path keeps the permissive unknown pass (this is the
    // live grounding-namespace shape; regression for the code-review finding).
    $registry = VocabularyRegistry::backedBy(
        fn (string $uri) => null,
        declared: ['https://schemas.splicewire.app/splice'],
    );

    $doc = [
        '@namespace' => ['splice' => 'https://schemas.splicewire.app/splice/grounding'],
        'splice:grounding' => ['anything' => true],
    ];

    expect((new VocabularyValidator($registry))->passes($doc))->toBeTrue();

    // …while a PINNED era of the declared namespace still fails loudly when unpublished.
    expect(fn () => $registry->schemaFor(NamespaceUri::from('https://schemas.splicewire.app/splice/3')))
        ->toThrow(VocabularyArtifactMissing::class);
});

// ── JN-17 invariant: the dialect must not REDUCE standards conformance (ADR-0192 §7) ─────────
// Asset 05 §3 chose `@`-keys over `$`-keys partly so a dialect-bearing artifact stays plain,
// valid 2020-12 to a STOCK validator. Nothing had ever tested it.

it('JN-17 invariant: a dialect-bearing BeamSchema artifact still works under a STOCK 2020-12 validator', function () {
    $validator = new Validator; // stock opis — no shim, no custom vocabulary

    $schema = $validator->loader()->loadObjectSchema(
        json_decode(fixtureJson('beam-artifact'), false, flags: JSON_THROW_ON_ERROR),
    );

    // The artifact parses as an ordinary schema (its @namespace/@namespaced/CURIE keys are
    // unknown-keyword annotations, not errors) and still VALIDATES: conforming data passes…
    $ok = $validator->validate((object) ['title' => 'Hello', 'body' => 'World'], $schema);
    expect($ok->isValid())->toBeTrue();

    // …and violating data still fails — the dialect neither breaks nor lobotomizes validation.
    $bad = $validator->validate((object) ['body' => 'no title'], $schema);
    expect($bad->isValid())->toBeFalse();
});
