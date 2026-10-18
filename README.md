# schemastud/php-json-ns

The **framework-free `@namespace` grammar**: a CURIE-shaped, RDF-stripped prefix→URI map
(`SchemaId`-shaped URIs) with `@namespaced` subtree overlays, late scope-local URI-is-identity
resolution, and a namespace-aware validation-scoping model.

```bash
composer require schemastud/php-json-ns
```

No Laravel here — only `opis/json-schema` + `rushing/php-popcorn`. The Laravel binding (URI-keyed
handler dispatch) is its sibling **`schemastud/laravel-json-ns`**.

The grammar's nouns, one file each under `src/`:

- `Curie`, `NamespaceUri`, `NamespaceUriKey` — the identifier shapes;
- `NamespaceMap` — the prefix→URI map a document declares;
- `NamespaceScope` — scope-local resolution (late, URI-is-identity);
- `Overlay` — `@namespaced` subtree overlays;
- `Resolver` — resolution over the above.

Start with [`AGENTS.md`](AGENTS.md); the contract docblocks on those classes are the reference.
Tests: `composer test`.
