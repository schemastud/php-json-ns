<?php

namespace Schemastud\JsonNs\Vocab;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Parsers\DefaultVocabulary;
use Opis\JsonSchema\Parsers\SchemaParser;
use Opis\JsonSchema\Parsers\Vocabulary;
use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\Validator;
use Schemastud\JsonNs\Curie;
use Schemastud\JsonNs\NamespaceScope;
use Schemastud\JsonNs\Resolver;
use Schemastud\JsonNs\Vocabulary as GrammarWords;

/**
 * The `$vocabulary` enforcement shim, top to bottom — the SEAM the task asks for. It joins the
 * two halves of asset 05 §7:
 *
 *   - php-json-ns's scoping model (the DRIVER): for each namespaced subtree of an instance
 *     document, `Resolver::scopeAt()` yields the governing {@see NamespaceScope}, whose
 *     `validationVocabulary()` is the URI (possibly version-pinned) to enforce.
 *   - opis/json-schema (the ENGINE): a custom {@see Vocabulary}
 *     carrying our {@see VocabularyKeywordParser} → {@see VocabularyKeyword} enforces the URI's
 *     vocab schema, since opis parses `$vocabulary` but never applies it (01 §3b).
 *
 * "Validation-scope == namespace-scope" (§7) is literal here: the subtree boundary we validate
 * is exactly the CURIE-key / `@namespaced` subtree boundary of §4. We walk those boundaries,
 * resolve each one's scope, and enforce its vocab against the subtree's own instance data.
 *
 * Framework-free: opis (Apache-2.0, pure PHP, pulls no illuminate) is the only dependency; the
 * vocab store is the injected {@see VocabularyRegistry} port — no dependency UP into beam. The
 * SchemaLadderMigrator interaction is handled at the URI seam (see {@see VocabularyRegistry}
 * and {@see VocabularyKeyword}): the scope resolves a mid-migration document to its correct-era
 * URI, so the enforcement keys onto that era's vocab rather than spuriously refusing it.
 */
class VocabularyValidator
{
    private VocabularyRegistry $registry;

    private RefusalPolicy $policy;

    /** The trivial trigger schema: an object carrying `$vocabulary` so our keyword fires. */
    private const TriggerSchema = '{"$vocabulary": {}}';

    public function __construct(VocabularyRegistry $registry, RefusalPolicy $policy = RefusalPolicy::Optional)
    {
        $this->registry = $registry;
        $this->policy = $policy;
    }

    /**
     * Validate a decoded JSON document's namespaced subtrees against their namespace
     * vocabularies. Returns the list of {@see ValidationError}s (empty == all conforming).
     *
     * @param  array<string, mixed>  $document  a decoded JSON document (objects as arrays)
     * @return array<int, ValidationError>
     */
    public function validate(array $document): array
    {
        $resolver = Resolver::for($document);
        $errors = [];

        foreach ($this->namespacedSubtrees($document) as [$pointer, $subtree]) {
            $scope = $resolver->scopeAt($pointer);

            $error = $this->enforce($scope, $subtree);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function passes(array $document): bool
    {
        return $this->validate($document) === [];
    }

    /**
     * Enforce one resolved scope's vocabulary against one subtree's instance data. The custom
     * opis vocabulary is built PER subtree because the scope (and thus the URI to enforce) is a
     * property of the subtree, not of the schema text.
     */
    private function enforce(NamespaceScope $scope, mixed $subtree): ?ValidationError
    {
        $extra = (new DefaultVocabulary)->appendKeyword(
            new VocabularyKeywordParser($scope, $this->registry, $this->policy),
        );

        $loader = new SchemaLoader(new SchemaParser([], [], $extra), null, true);
        $validator = new Validator($loader);

        $result = $validator->validate(
            $this->toOpisData($subtree),
            json_decode(self::TriggerSchema, false, flags: JSON_THROW_ON_ERROR),
        );

        return $result->error();
    }

    /**
     * Enumerate the namespaced subtree boundaries of a document as [pointer, subtree] pairs:
     * every CURIE-keyed node (§4a) and every node an `@namespaced` overlay governs (§4b). These
     * are exactly the boundaries §7 makes the validation-scope. Recurses the whole tree.
     *
     * @param  array<string, mixed>  $document
     * @return array<int, array{0: string, 1: mixed}>
     */
    private function namespacedSubtrees(array $document): array
    {
        $out = [];
        $this->walk($document, '', $out, $document);

        return $out;
    }

    /**
     * @param  array<int, array{0: string, 1: mixed}>  $out
     * @param  array<string, mixed>  $document
     */
    private function walk(mixed $node, string $pointer, array &$out, array $document): void
    {
        if (! is_array($node)) {
            return;
        }

        // A node carrying an `@namespaced` overlay is a namespaced boundary itself.
        if (isset($node[GrammarWords::Namespaced])) {
            $out[] = [$pointer, $node];
        }

        foreach ($node as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $childPointer = $pointer.'/'.str_replace(['~', '/'], ['~0', '~1'], $key);

            // A CURIE-keyed node is a namespaced boundary; its value is the subtree.
            if (Curie::tryParse($key) !== null) {
                $out[] = [$childPointer, $value];
            }

            $this->walk($value, $childPointer, $out, $document);
        }
    }

    /**
     * Convert a decoded-as-array subtree back to the object/scalar shape opis validates
     * (opis works on `stdClass`/scalars, not assoc arrays). An empty array is ambiguous; we
     * treat it as an empty object, which is the common CMS-body case.
     */
    private function toOpisData(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        // A list stays a list; an assoc array (or empty) becomes an object.
        $isList = array_is_list($value) && $value !== [];

        if ($isList) {
            return array_map(fn ($v) => $this->toOpisData($v), $value);
        }

        $obj = new \stdClass;

        foreach ($value as $k => $v) {
            // Grammar words (`@namespace`, `@namespaced`) are dialect meta, not instance data —
            // they are not part of what the namespace vocabulary validates.
            if ($k === GrammarWords::Namespace || $k === GrammarWords::Namespaced) {
                continue;
            }

            $obj->{(string) $k} = $this->toOpisData($v);
        }

        return $obj;
    }
}
