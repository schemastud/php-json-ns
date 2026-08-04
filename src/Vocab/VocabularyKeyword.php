<?php

namespace Schemastud\JsonNs\Vocab;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Keywords\ErrorTrait;
use Opis\JsonSchema\Parsers\Vocabulary;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;
use Schemastud\JsonNs\NamespaceScope;

/**
 * The custom opis `Keyword` that ENFORCES `$vocabulary` scoping — the thing opis/json-schema
 * itself parses but never applies (01 §3b, §7). opis's extension seam is exactly this pair:
 * a {@see Keyword} (validation-time) produced by a {@see VocabularyKeywordParser} (parse-time),
 * registered on a custom {@see Vocabulary} handed to the SchemaParser.
 *
 * It is DRIVEN by php-json-ns's scoping model, never by re-deriving scope itself: the enclosing
 * {@see VocabularyValidator} resolves the instance pointer → {@see NamespaceScope} (via
 * `Resolver::scopeAt`) and hands this keyword the governing scope. The keyword's whole job is
 * the §7 enforcement step: "the subtree governed by namespace URI X must validate against X's
 * vocab schema", where X (possibly version-pinned for a migrating document) comes straight off
 * the scope's `validationVocabulary()`.
 *
 * The migrator seam (§7) lives entirely in the URI we key off: because the scope hands us the
 * document's *resolved, correct-era* URI, a mid-migration older-era document keys onto its own
 * era's vocab in the registry — this keyword never re-refuses a document for a newer vocabulary
 * it was not authored against.
 */
class VocabularyKeyword implements Keyword
{
    use ErrorTrait;

    private NamespaceScope $scope;

    private VocabularyRegistry $registry;

    private RefusalPolicy $policy;

    public function __construct(NamespaceScope $scope, VocabularyRegistry $registry, RefusalPolicy $policy)
    {
        $this->scope = $scope;
        $this->registry = $registry;
        $this->policy = $policy;
    }

    public function validate(ValidationContext $context, Schema $schema): ?ValidationError
    {
        $uri = $this->scope->validationVocabulary();

        // (§4c.4) Absent namespace — the base/commons scope has no vocabulary to enforce. Pass
        // under either policy: there is nothing to validate against, so nothing to refuse.
        if ($uri === null) {
            return null;
        }

        $vocabSchema = $this->registry->schemaFor($uri);

        if ($vocabSchema === null) {
            // Unknown/unregistered vocabulary. Refusal semantics are the RefusalPolicy decision
            // (see that enum): Optional (the DEFAULT) passes; Required fails closed.
            if ($this->policy->isRequired()) {
                return $this->error($schema, $context, '$vocabulary',
                    'Required vocabulary "{vocabulary}" is not registered; refusing to validate the subtree.',
                    ['vocabulary' => (string) $uri]);
            }

            return null;
        }

        // Enforce: the subtree instance must validate against its namespace's vocab schema.
        // Same dispatch pattern opis's own NotKeyword uses (loadObjectSchema + validate).
        if (is_bool($vocabSchema)) {
            if ($vocabSchema === true) {
                return null;
            }

            return $this->error($schema, $context, '$vocabulary',
                'Vocabulary "{vocabulary}" forbids all data.', ['vocabulary' => (string) $uri]);
        }

        $loaded = $context->loader()->loadObjectSchema($vocabSchema);
        $error = $context->validateSchemaWithoutEvaluated($loaded);

        if ($error === null) {
            return null;
        }

        return $this->error($schema, $context, '$vocabulary',
            'The data does not conform to its namespace vocabulary "{vocabulary}".',
            ['vocabulary' => (string) $uri], $error);
    }
}
