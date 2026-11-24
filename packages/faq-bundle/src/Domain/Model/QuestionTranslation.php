<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\Markup\MarkedUpText;

/**
 * A question in one language: its wording, its answer as it was written and as purified HTML, whether it is published
 * in that language, and when it last changed, in UTC.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_faq_question_locale', columns: ['question_id', 'locale'])]
final class QuestionTranslation
{
    public const string TABLE = 'phil_faq_question_translation';

    /** The longest wording of a question, in characters. */
    public const int MAX_QUESTION = 300;

    /** The longest source of an answer, in bytes, which every TEXT column of the supported databases holds. */
    public const int MAX_ANSWER_BYTES = 60_000;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Question::class)]
        #[ORM\JoinColumn(name: 'question_id', nullable: false, onDelete: 'CASCADE')]
        public readonly Question $question,
        #[ORM\Column(name: 'locale', length: 16)]
        public readonly string $locale,
        #[ORM\Column(name: 'question', length: self::MAX_QUESTION)]
        private string $text,
        #[ORM\Column(name: 'answer_source', type: 'text')]
        private string $answerSource,
        #[ORM\Column(name: 'answer_format', length: 16)]
        private string $answerFormat,
        #[ORM\Column(name: 'answer_html', type: 'text')]
        private string $answerHtml,
        #[ORM\Column(name: 'published')]
        private bool $published,
        #[ORM\Column(name: 'updated_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @throws InvalidFaq when the wording or the answer is empty or too long
     */
    public static function of(string $id, Question $question, Locale $locale, string $text, MarkedUpText $answer, bool $published, DateTimeImmutable $now): self
    {
        $answer = self::checkedAnswer($answer);

        return new self($id, $question, $locale->value, self::checkedText($text), $answer->source, $answer->format, $answer->html, $published, self::second($now));
    }

    public function text(): string
    {
        return $this->text;
    }

    /**
     * The answer as it was written, in its format, and as purified HTML.
     */
    public function answer(): MarkedUpText
    {
        return new MarkedUpText($this->answerSource, $this->answerFormat, $this->answerHtml);
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @throws InvalidFaq when the wording or the answer is empty or too long
     */
    public function rewrite(string $text, MarkedUpText $answer, DateTimeImmutable $now): void
    {
        $this->text = self::checkedText($text);
        $answer = self::checkedAnswer($answer);
        $this->answerSource = $answer->source;
        $this->answerFormat = $answer->format;
        $this->answerHtml = $answer->html;
        $this->updatedAt = self::second($now);
    }

    public function publish(bool $published, DateTimeImmutable $now): void
    {
        if ($published === $this->published) {
            return;
        }

        $this->published = $published;
        $this->updatedAt = self::second($now);
    }

    /**
     * @throws InvalidFaq when the wording is empty or too long
     */
    private static function checkedText(string $text): string
    {
        $text = trim($text);

        if ('' === $text || mb_strlen($text) > self::MAX_QUESTION) {
            throw InvalidFaq::because(\sprintf('The wording of a question is 1 to %d characters.', self::MAX_QUESTION));
        }

        return $text;
    }

    /**
     * @throws InvalidFaq when the answer is empty or too long
     */
    private static function checkedAnswer(MarkedUpText $answer): MarkedUpText
    {
        if ('' === trim($answer->source) || '' === trim($answer->html) || \strlen($answer->source) > self::MAX_ANSWER_BYTES) {
            throw InvalidFaq::because(\sprintf('The answer of a question is 1 to %d bytes, and shows some text.', self::MAX_ANSWER_BYTES));
        }

        return $answer;
    }

    /**
     * Stores keep seconds: the translation holds what a store gives back.
     */
    private static function second(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimestamp($moment->getTimestamp());
    }
}
