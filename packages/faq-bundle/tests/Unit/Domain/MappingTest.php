<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Phil\FaqBundle\Domain\Model\Question;
use Phil\FaqBundle\Domain\Model\QuestionTranslation;
use Phil\FaqBundle\Domain\Model\Section;
use Phil\FaqBundle\Domain\Model\SectionTranslation;
use Phil\FaqBundle\Domain\Model\SpentVoteToken;
use Phil\FaqBundle\Domain\Model\UnansweredSearch;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\FaqBundle\Tests\Fixtures\Database;
use Phil\FaqBundle\Tests\Fixtures\Faq;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The tables the models map to, column by column: a migration of an application is written from them.
 */
#[CoversClass(Section::class)]
#[CoversClass(SectionTranslation::class)]
#[CoversClass(Question::class)]
#[CoversClass(QuestionTranslation::class)]
#[CoversClass(SpentVoteToken::class)]
#[CoversClass(UnansweredSearch::class)]
final class MappingTest extends TestCase
{
    public function testEachModelMapsToItsTable(): void
    {
        $entityManager = Database::entityManager();
        // One of each, built as the bundle builds them, so that the test runs the constructors whose mapping it reads.
        $editor = Faq::editor($entityManager, new MockClock('2026-11-21 10:00:00 UTC'));
        $section = $editor->addSection(Locale::fromString('en'), 'Orders');
        $editor->addQuestion($section->id, Locale::fromString('en'), 'Q?', 'a');
        self::assertSame(64, \strlen(new SpentVoteToken(str_repeat('a', 64), new DateTimeImmutable('2026-11-22 10:00:00 UTC'))->id));
        UnansweredSearch::restored('en', 'abc', 1, new DateTimeImmutable('2026-11-21'));
        $tables = [];

        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $tables[$metadata->getName()] = $this->summary($metadata);
        }

        $entityManager->getConnection()->close();
        ksort($tables);

        self::assertSame([
            Question::class => [
                'table phil_faq_question unique phil_faq_question_anchor(anchor) index phil_faq_question_order(section_id,position)',
                'id' => 'id string length=36 {"fixed":true} id',
                'anchor' => 'anchor string length=100',
                'position' => 'position integer',
                'helpfulYes' => 'helpful_yes integer {"default":0}',
                'helpfulNo' => 'helpful_no integer {"default":0}',
                'section' => 'join section_id CASCADE not null',
            ],
            QuestionTranslation::class => [
                'table phil_faq_question_translation unique phil_faq_question_locale(question_id,locale)',
                'id' => 'id string length=36 {"fixed":true} id',
                'locale' => 'locale string length=16',
                'text' => 'question string length=300',
                'answerSource' => 'answer_source text',
                'answerFormat' => 'answer_format string length=16',
                'answerHtml' => 'answer_html text',
                'published' => 'published boolean',
                'updatedAt' => 'updated_at datetime_utc_immutable',
                'question' => 'join question_id CASCADE not null',
            ],
            Section::class => [
                'table phil_faq_section unique phil_faq_section_key(section_key)',
                'id' => 'id string length=36 {"fixed":true} id',
                'key' => 'section_key string length=100',
                'position' => 'position integer',
            ],
            SectionTranslation::class => [
                'table phil_faq_section_translation unique phil_faq_section_locale(section_id,locale)',
                'id' => 'id string length=36 {"fixed":true} id',
                'locale' => 'locale string length=16',
                'title' => 'title string length=150',
                'section' => 'join section_id CASCADE not null',
            ],
            SpentVoteToken::class => [
                'table phil_faq_spent_vote index phil_faq_spent_vote_expiry(expires_at)',
                'id' => 'id string length=64 {"fixed":true} id',
                'expiresAt' => 'expires_at datetime_utc_immutable',
            ],
            UnansweredSearch::class => [
                'table phil_faq_unanswered_search index phil_faq_unanswered_count(locale,searches)',
                'id' => 'id string length=64 {"fixed":true} id',
                'locale' => 'locale string length=16',
                'query' => 'terms string length=100',
                'searches' => 'searches integer',
                'lastSearchedOn' => 'last_searched_on date_immutable',
            ],
        ], $tables);
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return array<int|string, string>
     */
    private function summary(ClassMetadata $metadata): array
    {
        $table = 'table '.$metadata->getTableName();

        foreach ($metadata->table['uniqueConstraints'] ?? [] as $name => $constraint) {
            $table .= ' unique '.$name.'('.$this->columns($constraint).')';
        }

        foreach ($metadata->table['indexes'] ?? [] as $name => $index) {
            $table .= ' index '.$name.'('.$this->columns($index).')';
        }

        $summary = [$table];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $summary[$field] = implode(' ', array_filter([
                $mapping->columnName,
                $mapping->type,
                null === $mapping->length ? null : 'length='.$mapping->length,
                true === $mapping->nullable ? 'nullable' : null,
                \in_array($mapping->options, [null, []], true) ? null : json_encode($mapping->options, \JSON_THROW_ON_ERROR),
                true === $mapping->id ? 'id' : null,
            ], static fn (?string $part): bool => null !== $part));
        }

        foreach ($metadata->associationMappings as $field => $mapping) {
            $join = $mapping->joinColumns[0] ?? null;
            $summary[$field] = 'join '.$join?->name.' '.$join?->onDelete.(false === $join?->nullable ? ' not null' : '');
        }

        return $summary;
    }

    private function columns(mixed $definition): string
    {
        $columns = \is_array($definition) ? $definition['columns'] ?? [] : [];

        return implode(',', array_filter(\is_array($columns) ? $columns : [], \is_string(...)));
    }
}
