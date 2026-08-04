<?php

use Schemastud\JsonNs\Curie;
use Schemastud\JsonNs\Exceptions\UnboundPrefix;
use Schemastud\JsonNs\Resolver;

// Coverage 1 (cont.): CURIE keys prefix:local resolve through the map to full URIs.

it('resolves a compact CURIE key to its full namespace URI (§4a fixture)', function () {
    $uri = Resolver::for(fixture('curie-key'))->resolveCurie('splice:grounding');

    expect((string) $uri)->toBe('https://schemas.splicewire.app/splice/grounding');
});

it('returns null for a non-CURIE key rather than misparsing it', function () {
    $resolver = Resolver::for(fixture('curie-key'));

    expect($resolver->resolveCurie('sources'))->toBeNull()
        ->and($resolver->resolveCurie('@id'))->toBeNull()
        ->and($resolver->resolveCurie('https://example.com/x'))->toBeNull();
});

it('throws on an unbound prefix', function () {
    Resolver::for(fixture('curie-key'))->resolveCurie('ghost:thing');
})->throws(UnboundPrefix::class);

it('recognises CURIE shape but not @-keys, pointers, or bare keys', function () {
    expect(Curie::tryParse('splice:grounding'))->not->toBeNull()
        ->and(Curie::tryParse('sources'))->toBeNull()
        ->and(Curie::tryParse('@namespace'))->toBeNull()
        ->and(Curie::tryParse('https://x/y'))->toBeNull()
        ->and(Curie::tryParse(':bad'))->toBeNull();
});

it('exposes the split prefix and local part', function () {
    $curie = Curie::tryParse('splice:grounding');

    expect($curie->prefix())->toBe('splice')
        ->and($curie->local())->toBe('grounding');
});
