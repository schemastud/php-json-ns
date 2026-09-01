<?php

use Schemastud\JsonNs\Overlay;
use Schemastud\JsonNs\Resolver;

// Coverage 3: @namespaced overlay — inline string desugars to single-entry array;
// sidecar array form; scope pointers relative to the carrier; re-namespaces unowned keys.

it('desugars a string shorthand to a single whole-subtree entry (§4b)', function () {
    $entries = Overlay::desugar('splice');

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->scope())->toBe('')
        ->and($entries[0]->namespace())->toBe('splice');
});

it('parses the sidecar array long form', function () {
    $entries = Overlay::desugar([
        ['scope' => '/config/grounding', 'namespace' => 'splice'],
    ]);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->scope())->toBe('/config/grounding')
        ->and($entries[0]->namespace())->toBe('splice');
});

it('makes the inline and sidecar forms EXACTLY equivalent (§4b claim)', function () {
    $inline = Resolver::for(fixtureData('overlay-inline'));
    $sidecar = Resolver::for(fixtureData('overlay-sidecar'));

    $ptr = '/config/grounding/sources';

    $a = $inline->scopeAt($ptr);
    $b = $sidecar->scopeAt($ptr);

    expect((string) $a->uri())->toBe((string) $b->uri())
        ->and($a->source())->toBe('overlay')
        ->and($b->source())->toBe('overlay');
});

it('re-namespaces unowned plain keys WITHOUT rewriting them to CURIE form', function () {
    $resolver = Resolver::for(fixtureData('overlay-inline'));

    // 'sources' and 'max_depth' are plain unprefixed keys authored by someone else,
    // yet governed by 'splice' via the overlay — never renamed to splice:sources.
    $sources = $resolver->scopeAt('/config/grounding/sources');
    $maxDepth = $resolver->scopeAt('/config/grounding/max_depth');

    expect((string) $sources->uri())->toBe('https://schemas.splicewire.app/splice/grounding')
        ->and($sources->prefix())->toBe('splice')
        ->and((string) $maxDepth->uri())->toBe('https://schemas.splicewire.app/splice/grounding');
});

it('scopes the overlay to its subtree — siblings outside the scope fall to base', function () {
    // A key sitting OUTSIDE /config/grounding is not governed by the overlay.
    $resolver = Resolver::for(fixtureData('overlay-sidecar'));

    expect($resolver->scopeAt('/config')->isBase())->toBeTrue();
});

it('reads relative scope pointers against the carrier (leading slash tolerated)', function () {
    $entries = Overlay::desugar([['scope' => 'a/b', 'namespace' => 'x']]);

    // "a/b" and "/a/b" both mean the same relative pointer; governance is by prefix segments.
    expect($entries[0]->governs('/a/b'))->toBeTrue()
        ->and($entries[0]->governs('/a/b/c'))->toBeTrue()
        ->and($entries[0]->governs('/a/bx'))->toBeFalse();
});

it('skips malformed sidecar entries tolerantly (enforcement is a build handoff)', function () {
    $entries = Overlay::desugar([
        ['namespace' => ''],
        ['scope' => '/x'],
        'not-an-object',
        ['scope' => '/ok', 'namespace' => 'splice'],
    ]);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->namespace())->toBe('splice');
});
