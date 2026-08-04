<?php

namespace Schemastud\JsonNs\Vocab;

use Opis\JsonSchema\Info\SchemaInfo;
use Opis\JsonSchema\Keyword;
use Opis\JsonSchema\Parsers\KeywordParser;
use Opis\JsonSchema\Parsers\SchemaParser;
use Opis\JsonSchema\Parsers\Vocabulary;
use Schemastud\JsonNs\NamespaceScope;

/**
 * The parse-time half of the opis extension seam: a {@see KeywordParser} that, whenever a
 * schema object carries the `$vocabulary` keyword, produces a {@see VocabularyKeyword} to
 * ENFORCE it (opis parses `$vocabulary` into nothing by default — this is the shim).
 *
 * opis wires a custom keyword by handing a {@see Vocabulary} carrying
 * this parser to the `SchemaParser`; opis then merges it into every `Draft` (see
 * `Draft::__construct`). This parser is `TYPE_APPEND` so `$vocabulary` enforcement runs after
 * the ordinary keywords have been parsed for the subtree.
 *
 * It is constructed with a resolved {@see NamespaceScope} because scope is a property of the
 * *instance subtree* (resolved upstream by `Resolver::scopeAt`), not of the schema text; the
 * enclosing {@see VocabularyValidator} builds one parser per resolved subtree scope. The
 * migrating-document / version-pinned URI concern is fully carried by that scope — this parser
 * adds no policy of its own.
 */
class VocabularyKeywordParser extends KeywordParser
{
    private NamespaceScope $scope;

    private VocabularyRegistry $registry;

    private RefusalPolicy $policy;

    public function __construct(NamespaceScope $scope, VocabularyRegistry $registry, RefusalPolicy $policy, string $keyword = '$vocabulary')
    {
        parent::__construct($keyword);
        $this->scope = $scope;
        $this->registry = $registry;
        $this->policy = $policy;
    }

    public function type(): string
    {
        return self::TYPE_APPEND;
    }

    public function parse(SchemaInfo $info, SchemaParser $parser, object $shared): ?Keyword
    {
        // Only the schema-document root carries `$vocabulary` in 2020-12; enforce there.
        if (! $this->keywordExists($info->data())) {
            return null;
        }

        return new VocabularyKeyword($this->scope, $this->registry, $this->policy);
    }
}
