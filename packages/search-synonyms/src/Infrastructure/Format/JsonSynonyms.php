<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Infrastructure\Format;

use JsonException;
use Phil\SearchSynonyms\Domain\Exception\InvalidSynonyms;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymList;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymSet;

/**
 * The synonyms of a language as JSON, for an export, an import, or an administration that edits them in a form:
 *
 * ```json
 * {"language": "fr", "rules": [{"synonyms": ["jdr", "jeu de rôle"]}, {"from": ["donjon"], "to": ["donjon", "dungeon"]}]}
 * ```
 *
 * A rule is equivalent phrases under `synonyms`, or a one-way rule with `from` and `to`. A document is 1 MiB at most;
 * one that is not shaped so is refused, with the position of its rule from 1.
 */
final readonly class JsonSynonyms
{
    public const int MAX_BYTES = 1_048_576;

    /**
     * @throws InvalidSynonyms
     */
    public function read(string $json): SynonymList
    {
        if (\strlen($json) > self::MAX_BYTES) {
            throw InvalidSynonyms::because(\sprintf('A synonyms document is %d bytes at most.', self::MAX_BYTES));
        }

        try {
            $document = json_decode($json, true, 5, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidSynonyms::because('A synonyms document is JSON: '.$jsonException->getMessage().'.');
        }

        if (!\is_array($document) || !\is_string($document['language'] ?? null) || !\is_array($document['rules'] ?? null) || !array_is_list($document['rules'])) {
            throw InvalidSynonyms::because('A synonyms document is an object with "language", a tag, and "rules", a list.');
        }

        if (\count($document['rules']) > SynonymList::MAX_SETS) {
            throw InvalidSynonyms::because(\sprintf('A language holds %d rules at most; %d given.', SynonymList::MAX_SETS, \count($document['rules'])));
        }

        $sets = [];

        foreach ($document['rules'] as $position => $rule) {
            try {
                $sets[] = $this->rule($rule);
            } catch (InvalidSynonyms $invalidSynonyms) {
                throw InvalidSynonyms::because(\sprintf('Rule %d: %s', $position + 1, $invalidSynonyms->getMessage()));
            }
        }

        return SynonymList::of($document['language'], $sets);
    }

    public function write(SynonymList $list): string
    {
        $rules = array_map(static fn (SynonymSet $set): array => $set->equivalent ? ['synonyms' => $set->from] : ['from' => $set->from, 'to' => $set->to], $list->sets);

        return json_encode(['language' => $list->language, 'rules' => $rules], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * @throws InvalidSynonyms
     */
    private function rule(mixed $rule): SynonymSet
    {
        if (!\is_array($rule)) {
            throw InvalidSynonyms::because('A rule is an object.');
        }

        if (\array_key_exists('synonyms', $rule)) {
            return SynonymSet::equivalent($this->phrases($rule['synonyms']));
        }

        if (\array_key_exists('from', $rule) && \array_key_exists('to', $rule)) {
            return SynonymSet::oneWay($this->phrases($rule['from']), $this->phrases($rule['to']));
        }

        throw InvalidSynonyms::because('A rule has "synonyms", or "from" and "to".');
    }

    /**
     * @return list<string>
     *
     * @throws InvalidSynonyms
     */
    private function phrases(mixed $phrases): array
    {
        if (!\is_array($phrases) || !array_is_list($phrases) || \count($phrases) > SynonymSet::MAX_PHRASES || [] !== array_filter($phrases, static fn (mixed $phrase): bool => !\is_string($phrase))) {
            throw InvalidSynonyms::because(\sprintf('Phrases are a list of at most %d strings.', SynonymSet::MAX_PHRASES));
        }

        /** @var list<string> $phrases */
        return $phrases;
    }
}
