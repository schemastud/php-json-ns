<?php

namespace Schemastud\JsonNs\Vocab;

use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Keywords\ErrorTrait;
use Opis\JsonSchema\Parsers\Vocabulary;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationContext;
use Schemastud\JsonNs\NamespaceScope;
use Schemastud\JsonNs\NamespaceUri;

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

    /** @var array<string, bool> the AUTHORED `$vocabulary` URI→bool map (JN-17) */
    private array $authored;

    /**
     * @param  array<string, bool>  $authored
     */
    public function __construct(NamespaceScope $scope, VocabularyRegistry $registry, RefusalPolicy $policy, array $authored = [])
    {
        $this->scope = $scope;
        $this->registry = $registry;
        $this->policy = $policy;
        $this->authored = $authored;
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
            // Unknown/unregistered vocabulary. Refusal semantics are PER-URI (JN-17): the
            // authored `$vocabulary` map's bool for this URI wins (2020-12 — true = required,
            // an implementation that cannot supply the vocabulary MUST refuse; false =
            // optional, proceed); the instance-wide RefusalPolicy decides only for URIs the
            // author declared no bit for.
            if ($this->requiredFor($uri)) {
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

    /**
     * Is an UNAVAILABLE vocabulary at this URI a refusal? The authored map's per-URI bool is
     * authoritative when present — exact (possibly pinned) URI first, then the version-free
     * stem (the same two-step identity the registry itself resolves by, so a pinned document
     * era inherits its stem's authored bit rather than silently falling to the default).
     * With no authored bit, the instance-wide {@see RefusalPolicy} decides.
     */
    private function requiredFor(NamespaceUri $uri): bool
    {
        if (array_key_exists((string) $uri, $this->authored)) {
            return $this->authored[(string) $uri];
        }

        if ($uri->isPinned() && array_key_exists($uri->stem(), $this->authored)) {
            return $this->authored[$uri->stem()];
        }

        return $this->policy->isRequired();
    }
}
