<?php

use Schemastud\JsonNs\NamespaceScope;
use Schemastud\JsonNs\Resolver;

// Coverage 5: the namespace-aware validation-scoping model (§7). A subtree's
// validation-scope == its namespace-scope. This EXPOSES the model for a later enforcement
// ticket — there is NO enforcement engine here.

it('yields, for a pointer, its resolved namespace scope (the model a later ticket enforces)', function () {
    $scope = Resolver::for(fixture('curie-key'))->scopeAt('/splice:grounding');

    expect($scope)->toBeInstanceOf(NamespaceScope::class)
        ->and($scope->pointer())->toBe('/splice:grounding')
        ->and($scope->source())->toBe('curie');
});

it('makes validation-scope identical to namespace-scope (§7 — same subtree boundary)', function () {
    $resolver = Resolver::for(fixture('overlay-inline'));
    $scope = $resolver->scopeAt('/config/grounding/sources');

    // The validation vocabulary IS the governing namespace URI — one boundary, not two.
    expect((string) $scope->validationVocabulary())->toBe((string) $scope->uri())
        ->and((string) $scope->validationVocabulary())->toBe('https://schemas.splicewire.app/splice/grounding');
});

it('applies §4c precedence: CURIE key beats overlay beats base', function () {
    // CURIE wins.
    $curie = Resolver::for(fixture('curie-key'))->scopeAt('/splice:grounding');
    // Overlay governs an unowned key.
    $overlay = Resolver::for(fixture('overlay-inline'))->scopeAt('/config/grounding/sources');
    // Nothing governs → base.
    $base = Resolver::for(fixture('declaration'))->scopeAt('/@namespace');

    expect($curie->source())->toBe('curie')
        ->and($overlay->source())->toBe('overlay')
        ->and($base->source())->toBe('base')
        ->and($base->isBase())->toBeTrue()
        ->and($base->validationVocabulary())->toBeNull();
});

it('falls unowned base keys to the unprefixed base/commons vocabulary (§4c.4)', function () {
    $scope = Resolver::for(fixture('declaration'))->scopeAt('/splice:grounding/sources');

    // 'sources' under a CURIE-keyed node is not itself CURIE'd and has no overlay → base.
    expect($scope->isBase())->toBeTrue()
        ->and($scope->prefix())->toBeNull();
});
