<?php

use Schemastud\JsonNs\NamespaceMap;
use Schemastud\JsonNs\NamespaceUri;
use Schemastud\JsonNs\Vocabulary;

// Coverage 1: `@namespace` parses to a prefix→URI map; CURIE prefixes resolve through it.

it('owns @namespace and @namespaced as the @-family grammar keys', function () {
    expect(Vocabulary::Namespace)->toBe('@namespace')
        ->and(Vocabulary::Namespaced)->toBe('@namespaced');
});

it('parses the asset-05 §3 declaration into a prefix→URI map', function () {
    $map = NamespaceMap::fromNode(fixture('declaration'));

    expect($map->has('frame'))->toBeTrue()
        ->and($map->has('beam'))->toBeTrue()
        ->and($map->has('splice'))->toBeTrue()
        ->and((string) $map->resolve('splice'))->toBe('https://schemas.splicewire.app/splice/grounding')
        ->and((string) $map->resolve('frame'))->toBe('https://schemas.splicewire.app/frame');
});

it('yields an empty map when no @namespace is present (legacy fallback territory)', function () {
    expect(NamespaceMap::fromNode(['foo' => 'bar'])->isEmpty())->toBeTrue();
});

it('resolves values as NamespaceUri value objects', function () {
    $uri = NamespaceMap::fromNode(fixture('declaration'))->resolve('splice');

    expect($uri)->toBeInstanceOf(NamespaceUri::class);
});
