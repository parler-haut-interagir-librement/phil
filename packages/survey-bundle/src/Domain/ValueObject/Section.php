<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\ValueObject;

use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Model\Read;

/**
 * A section: a title, an optional description, its questions in order, and the condition that shows it — a hidden
 * section hides every question in it.
 */
final readonly class Section
{
    public const int MAX_QUESTIONS = 100;

    public const int MAX_DESCRIPTION = 2000;

    private const array MEMBERS = ['name', 'title', 'description', 'questions', 'shown_when'];

    public string $title;

    public string $description;

    /**
     * @param list<Question>            $questions at least one
     * @param array<string, mixed>|null $shownWhen
     *
     * @throws InvalidQuestionnaire when the section has no question, or too many
     */
    public function __construct(
        public string $name,
        string $title,
        public array $questions,
        string $description = '',
        public ?array $shownWhen = null,
    ) {
        Read::name($name, 'a section');
        $this->title = Read::text($title, 'the title of the section "'.$name.'"', Choice::MAX_LABEL);
        $this->description = Read::text($description, 'the description of the section "'.$name.'"', self::MAX_DESCRIPTION, true);

        if ([] === $questions || \count($questions) > self::MAX_QUESTIONS) {
            throw InvalidQuestionnaire::because(\sprintf('A section has 1 to %d questions: "%s".', self::MAX_QUESTIONS, $name));
        }
    }

    /**
     * @throws InvalidQuestionnaire when the section is not of the shape `toArray()` writes
     */
    public static function fromArray(mixed $data): self
    {
        $section = Read::map($data, 'a section', self::MEMBERS);

        return new self(
            Read::string($section, 'name', 'a section'),
            Read::string($section, 'title', 'a section'),
            array_map(Question::fromArray(...), Read::list($section, 'questions', 'a section')),
            Read::optionalString($section, 'description', 'a section') ?? '',
            Read::condition($section, 'shown_when', 'a section'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'title' => $this->title,
            'description' => '' === $this->description ? null : $this->description,
            'shown_when' => $this->shownWhen,
            'questions' => array_map(static fn (Question $question): array => $question->toArray(), $this->questions),
        ], static fn (mixed $value): bool => null !== $value);
    }
}
