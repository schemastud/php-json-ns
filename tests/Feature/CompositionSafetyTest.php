<?php

use Schemastud\JsonNs\Resolver;

// Coverage 4: URI-is-identity resolution — late scope-local; a composed subtree carrying
// its own @namespace cannot be corrupted by an outer collision; alias-override is an
// additive same-URI re-bind (not a replace).

it('resolves a prefix late + scope-local: nearer @namespace shadows an outer one (§5)', function () {
    $resolver = Resolver::for(fixtureData('composition-safety'));

    // The inner subtree ('uses') carries its OWN @namespace binding 'splice' to the
    // grounding URI. The outer document binds the SAME prefix to a different, colliding URI.
    $inner = $resolver->resolveCurie('splice:grounding', '/uses');
    $outer = $resolver->resolveCurie('splice:outer');

    expect((string) $inner)->toBe('https://schemas.splicewire.app/splice/grounding')
        ->and((string) $outer)->toBe('https://schemas.splicewire.app/OUTER-COLLIDING-URI')
        ->and((string) $inner)->not->toBe((string) $outer);
});

it('cannot corrupt a composed subtree binding by an outer prefix collision (§5 safety)', function () {
    $resolver = Resolver::for(fixtureData('composition-safety'));

    // scopeAt on the inner CURIE key resolves through the inner @namespace — the URI is
    // identity, so the outer 'splice' rebind never reaches into the authored subtree.
    $scope = $resolver->scopeAt('/uses/splice:grounding');

    expect((string) $scope->uri())->toBe('https://schemas.splicewire.app/splice/grounding')
        ->and($scope->source())->toBe('curie');
});

it('treats alias-override as an additive same-URI re-bind, not a replace (§5)', function () {
    $resolver = Resolver::for(fixtureData('alias-override'));

    // Two prefixes, one URI ⇒ same namespace ⇒ same identity.
    $viaSplice = $resolver->resolveCurie('splice:grounding');
    $viaGrounding = $resolver->resolveCurie('grounding:sources');

    expect((string) $viaSplice)->toBe((string) $viaGrounding)
        ->and((string) $viaSplice)->toBe('https://schemas.splicewire.app/splice/grounding');
});

it('exposes the effective merged map at a pointer with inner shadowing outer', function () {
    $resolver = Resolver::for(fixtureData('composition-safety'));

    $rootMap = $resolver->effectiveMapAt('');
    $innerMap = $resolver->effectiveMapAt('/uses');

    expect((string) $rootMap->resolve('splice'))->toBe('https://schemas.splicewire.app/OUTER-COLLIDING-URI')
        ->and((string) $innerMap->resolve('splice'))->toBe('https://schemas.splicewire.app/splice/grounding');
});
