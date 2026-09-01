<?php

use Schemastud\JsonNs\NamespaceMap;
use Schemastud\JsonNs\NamespaceUri;

// Coverage 2: URIs are SchemaId-shaped — version-free resolves latest (unpinned);
// integer-pinned resolves to that version.

it('treats a version-free stem as an unpinned handle that resolves to latest', function () {
    $uri = NamespaceUri::from('https://schemas.splicewire.app/splice/grounding');

    // A version-free URI is an unpinned handle; its whole string is its identity (it
    // round-trips), and it carries no version.
    expect($uri->isUnpinned())->toBeTrue()
        ->and($uri->isPinned())->toBeFalse()
        ->and($uri->version())->toBeNull()
        ->and((string) $uri)->toBe('https://schemas.splicewire.app/splice/grounding');
});

it('reads a trailing integer as a version pin (asset 05 §8 fixture)', function () {
    $uri = NamespaceUri::from('https://schemas.splicewire.app/splice/grounding/3');

    expect($uri->isPinned())->toBeTrue()
        ->and($uri->version())->toBe(3)
        ->and($uri->stem())->toBe('https://schemas.splicewire.app/splice/grounding');
});

it('makes the stem and a pin DIFFERENT URIs (different match scopes, §5/§8)', function () {
    $stem = NamespaceUri::from('https://schemas.splicewire.app/splice/grounding');
    $pin = NamespaceUri::from('https://schemas.splicewire.app/splice/grounding/3');

    // They are distinct URIs (distinct identities, §5). Because a version-free URI is itself
    // a stem, the pinned form's stem() is exactly the unpinned handle's string — that is the
    // stem→pin relationship, NOT withVersion() on the handle (which would append to the
    // handle's OWN stem). See report note on this SchemaId subtlety.
    expect($stem->equals($pin))->toBeFalse()
        ->and($pin->stem())->toBe((string) $stem)
        ->and($pin->version())->toBe(3)
        ->and($stem->version())->toBeNull();
});

it('round-trips byte-for-byte', function () {
    $raw = 'https://schemas.splicewire.app/splice/grounding/7';

    expect((string) NamespaceUri::from($raw))->toBe($raw);
});

it('keeps generational path text as stem, never as the version integer', function () {
    // à la .../draft/2020-12/vocab/core — "core" is not a version, so unpinned.
    $uri = NamespaceUri::from('https://schemas.splicewire.app/draft/2020-12/vocab/core');

    expect($uri->isUnpinned())->toBeTrue()
        ->and($uri->version())->toBeNull();
});

it('pins the two declared URIs distinctly in the §8 pinning fixture', function () {
    $map = NamespaceMap::fromNode(fixtureData('pinning'));

    // Under the SchemaId grammar the *unpinned URI string* IS the pinned URI's stem — so
    // the version-3 pin lives on the stem the unpinned handle names.
    expect($map->resolve('splice')->isUnpinned())->toBeTrue()
        ->and($map->resolve('splice_v3')->version())->toBe(3)
        ->and($map->resolve('splice_v3')->stem())->toBe((string) $map->resolve('splice'));
});
