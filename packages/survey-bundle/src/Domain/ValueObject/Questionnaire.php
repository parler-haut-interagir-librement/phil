<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Logic\QuestionnaireLogic;
use Phil\SurveyBundle\Domain\Model\Read;
use Phil\SurveyBundle\Domain\Status\Anonymity;
use Phil\SurveyBundle\Domain\Status\Pacing;
use Phil\SurveyBundle\Domain\Status\QuestionnaireKind;

/**
 * A questionnaire: its sections and questions, its computed variables, its endings and its quotas, and how it is
 * answered — anonymously or not, at what pace, in what window, how long its responses are kept, whether respondents
 * see the results and may add to their response after sending it. A questionnaire built is valid: its names are
 * unique, its last ending has no condition, and every condition reads what it may, of the right types.
 *
 * Its data — `toArray()`, read back by `fromArray()` — is the structure the JSON schema of the bundle describes.
 */
final readonly class Questionnaire
{
    public const int MAX_SECTIONS = 50;

    public const int MAX_QUESTIONS = 200;

    public const int MAX_ENDINGS = 50;

    public const int MAX_QUOTAS = 50;

    public const int MAX_COMPUTED = 50;

    public const int MAX_RETENTION_DAYS = 3650;

    public const int MAX_DESCRIPTION = 5000;

    /** How many minutes before a session its feedback opens, and after it closes, by default: 15 minutes and 3 days. */
    public const int OPENS_BEFORE = 15;

    public const int CLOSES_AFTER = 4320;

    private const array MEMBERS = ['key', 'title', 'description', 'kind', 'anonymity', 'pacing', 'requires_member', 'sections', 'computed', 'endings', 'quotas', 'retention_days', 'results', 'window', 'feedback', 'addenda'];

    public string $title;

    public string $description;

    public QuestionnaireLogic $logic;

    /**
     * @param list<Section>                                            $sections         at least one
     * @param list<Ending>                                             $endings          at least one, the last without a condition
     * @param list<array{compute: string, type: string, value: mixed}> $computed         the computed variables, as phil/form-logic writes its steps
     * @param list<Quota>                                              $quotas
     * @param int|null                                                 $retentionDays    how many days its responses are kept, forever when null
     * @param int                                                      $minimumResponses how many responses a result counts before it is shown
     * @param int                                                      $opensBefore      the minutes a session's feedback opens before the session starts
     * @param int                                                      $closesAfter      the minutes it closes after the session ends
     *
     * @throws InvalidQuestionnaire naming the first rule the questionnaire breaks
     */
    public function __construct(
        public string $key,
        string $title,
        public array $sections,
        public array $endings,
        string $description = '',
        public QuestionnaireKind $kind = QuestionnaireKind::Survey,
        public Anonymity $anonymity = Anonymity::Anonymous,
        public Pacing $pacing = Pacing::Section,
        public bool $requiresMember = false,
        public array $computed = [],
        public array $quotas = [],
        public ?int $retentionDays = null,
        public bool $publicResults = false,
        public int $minimumResponses = 5,
        public ?DateTimeImmutable $opensAt = null,
        public ?DateTimeImmutable $closesAt = null,
        public int $opensBefore = self::OPENS_BEFORE,
        public int $closesAfter = self::CLOSES_AFTER,
        public bool $addenda = false,
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $key)) {
            throw InvalidQuestionnaire::because(\sprintf('The key of a questionnaire is 1 to 64 lower-case letters, digits, "_" or "-"; "%s" is not.', mb_substr($key, 0, 80)));
        }

        $this->title = Read::text($title, 'the title of the questionnaire', Choice::MAX_LABEL);
        $this->description = Read::text($description, 'the description of the questionnaire', self::MAX_DESCRIPTION, true);
        $this->checkCounts();
        $this->checkNames();
        $this->checkEndings();
        $this->checkPeriods();
        $this->logic = new QuestionnaireLogic($sections, $computed, $endings, $quotas);
    }

    /**
     * @throws InvalidQuestionnaire when the data is not a questionnaire of the shape `toArray()` writes
     */
    public static function fromArray(mixed $data): self
    {
        $q = Read::map($data, 'the questionnaire', self::MEMBERS);
        $what = 'the questionnaire';
        $results = Read::map($q['results'] ?? [], 'its results', ['public', 'minimum']);
        $window = Read::map($q['window'] ?? [], 'its window', ['opens_at', 'closes_at']);
        $feedback = Read::map($q['feedback'] ?? [], 'its feedback', ['opens_before', 'closes_after']);

        return new self(
            Read::string($q, 'key', $what),
            Read::string($q, 'title', $what),
            array_map(Section::fromArray(...), Read::list($q, 'sections', $what)),
            array_map(Ending::fromArray(...), Read::list($q, 'endings', $what)),
            Read::optionalString($q, 'description', $what) ?? '',
            self::enum(QuestionnaireKind::class, Read::optionalString($q, 'kind', $what), QuestionnaireKind::Survey),
            self::enum(Anonymity::class, Read::optionalString($q, 'anonymity', $what), Anonymity::Anonymous),
            self::enum(Pacing::class, Read::optionalString($q, 'pacing', $what), Pacing::Section),
            Read::bool($q, 'requires_member', $what, false),
            array_map(self::computed(...), Read::list($q, 'computed', $what)),
            array_map(Quota::fromArray(...), Read::list($q, 'quotas', $what)),
            Read::optionalInt($q, 'retention_days', $what),
            Read::bool($results, 'public', 'its results', false),
            Read::optionalInt($results, 'minimum', 'its results') ?? 5,
            self::instant(Read::optionalString($window, 'opens_at', 'its window')),
            self::instant(Read::optionalString($window, 'closes_at', 'its window')),
            Read::optionalInt($feedback, 'opens_before', 'its feedback') ?? self::OPENS_BEFORE,
            Read::optionalInt($feedback, 'closes_after', 'its feedback') ?? self::CLOSES_AFTER,
            Read::bool($q, 'addenda', $what, false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $window = array_filter(['opens_at' => $this->opensAt?->format(DateTimeInterface::ATOM), 'closes_at' => $this->closesAt?->format(DateTimeInterface::ATOM)], static fn (?string $instant): bool => null !== $instant);

        return array_filter([
            'key' => $this->key,
            'title' => $this->title,
            'description' => '' === $this->description ? null : $this->description,
            'kind' => $this->kind->value,
            'anonymity' => $this->anonymity->value,
            'pacing' => $this->pacing->value,
            'requires_member' => $this->requiresMember,
            'sections' => array_map(static fn (Section $section): array => $section->toArray(), $this->sections),
            'computed' => [] === $this->computed ? null : $this->computed,
            'endings' => array_map(static fn (Ending $ending): array => $ending->toArray(), $this->endings),
            'quotas' => [] === $this->quotas ? null : array_map(static fn (Quota $quota): array => $quota->toArray(), $this->quotas),
            'retention_days' => $this->retentionDays,
            'results' => ['public' => $this->publicResults, 'minimum' => $this->minimumResponses],
            'window' => [] === $window ? null : $window,
            'feedback' => $this->kind->needsSubject() ? ['opens_before' => $this->opensBefore, 'closes_after' => $this->closesAfter] : null,
            'addenda' => $this->addenda,
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * Whether a response names its respondent.
     */
    public function isAnonymous(): bool
    {
        return Anonymity::Anonymous === $this->anonymity;
    }

    /**
     * Whether only a signed-in member answers: always when responses name their respondent.
     */
    public function needsMember(): bool
    {
        return $this->requiresMember || !$this->isAnonymous();
    }

    /**
     * @return list<Question> the questions, section after section
     */
    public function questions(): array
    {
        return array_merge(...array_map(static fn (Section $section): array => $section->questions, $this->sections));
    }

    public function question(string $name): ?Question
    {
        foreach ($this->questions() as $question) {
            if ($question->name === $name) {
                return $question;
            }
        }

        return null;
    }

    public function ending(string $name): ?Ending
    {
        foreach ($this->endings as $ending) {
            if ($ending->name === $name) {
                return $ending;
            }
        }

        return null;
    }

    public function quota(string $name): ?Quota
    {
        foreach ($this->quotas as $quota) {
            if ($quota->name === $name) {
                return $quota;
            }
        }

        return null;
    }

    /**
     * Whether the questionnaire's own window is open at this instant; a session's feedback has its own window too.
     */
    public function isOpenAt(DateTimeImmutable $now): bool
    {
        return (!$this->opensAt instanceof DateTimeImmutable || $now >= $this->opensAt)
            && (!$this->closesAt instanceof DateTimeImmutable || $now <= $this->closesAt);
    }

    private function checkCounts(): void
    {
        $questions = \count($this->questions());

        if ([] === $this->sections || \count($this->sections) > self::MAX_SECTIONS || $questions > self::MAX_QUESTIONS) {
            throw InvalidQuestionnaire::because(\sprintf('A questionnaire has 1 to %d sections and at most %d questions.', self::MAX_SECTIONS, self::MAX_QUESTIONS));
        }

        if ([] === $this->endings || \count($this->endings) > self::MAX_ENDINGS || \count($this->quotas) > self::MAX_QUOTAS || \count($this->computed) > self::MAX_COMPUTED) {
            throw InvalidQuestionnaire::because(\sprintf('A questionnaire has 1 to %d endings, at most %d quotas and %d computed variables.', self::MAX_ENDINGS, self::MAX_QUOTAS, self::MAX_COMPUTED));
        }
    }

    private function checkNames(): void
    {
        $variables = array_map(static fn (Question $question): string => $question->name, $this->questions());

        foreach ($this->computed as $step) {
            $variables[] = $step['compute'];
        }

        foreach ([
            'questions and computed variables' => $variables,
            'sections' => array_map(static fn (Section $section): string => $section->name, $this->sections),
            'endings' => array_map(static fn (Ending $ending): string => $ending->name, $this->endings),
            'quotas' => array_map(static fn (Quota $quota): string => $quota->name, $this->quotas),
        ] as $what => $names) {
            $twice = array_diff_assoc($names, array_unique($names));

            if ([] !== $twice) {
                throw InvalidQuestionnaire::because(\sprintf('The names of the %s are each given once; "%s" is not.', $what, reset($twice)));
            }
        }

        foreach ($this->quotas as $quota) {
            if (!$this->ending($quota->ending) instanceof Ending) {
                throw InvalidQuestionnaire::because(\sprintf('The quota "%s" leads to the ending "%s", which the questionnaire does not have.', $quota->name, $quota->ending));
            }
        }
    }

    private function checkEndings(): void
    {
        $last = \count($this->endings) - 1;

        foreach ($this->endings as $position => $ending) {
            if (($position === $last) !== (null === $ending->when)) {
                throw InvalidQuestionnaire::because(\sprintf('Every ending but the last has a condition, and the last has none, so that every respondent reaches one: "%s".', $ending->name));
            }
        }
    }

    private function checkPeriods(): void
    {
        if (null !== $this->retentionDays && ($this->retentionDays < 1 || $this->retentionDays > self::MAX_RETENTION_DAYS)) {
            throw InvalidQuestionnaire::because(\sprintf('Responses are kept 1 to %d days, or with no limit.', self::MAX_RETENTION_DAYS));
        }

        if ($this->minimumResponses < 1 || $this->minimumResponses > 1000) {
            throw InvalidQuestionnaire::because('A result counts 1 to 1000 responses before it is shown.');
        }

        if ($this->opensAt instanceof DateTimeImmutable && $this->closesAt instanceof DateTimeImmutable && $this->closesAt <= $this->opensAt) {
            throw InvalidQuestionnaire::because('A questionnaire closes after it opens.');
        }

        if ($this->opensBefore < 0 || $this->opensBefore > 43_200 || $this->closesAfter < 0 || $this->closesAfter > 129_600) {
            throw InvalidQuestionnaire::because('The feedback of a session opens 0 to 30 days before it, and closes 0 to 90 days after it.');
        }
    }

    /**
     * @template T of QuestionnaireKind|Anonymity|Pacing
     *
     * @param class-string<T> $enum
     * @param T               $default
     *
     * @return T
     */
    private static function enum(string $enum, ?string $value, QuestionnaireKind|Anonymity|Pacing $default): QuestionnaireKind|Anonymity|Pacing
    {
        if (null === $value) {
            return $default;
        }

        return $enum::tryFrom($value) ?? throw InvalidQuestionnaire::because(\sprintf('"%s" is not one of: %s.', mb_substr($value, 0, 40), implode(', ', array_column($enum::cases(), 'value'))));
    }

    /**
     * @return array{compute: string, type: string, value: mixed}
     */
    private static function computed(mixed $step): array
    {
        $read = Read::map($step, 'a computed variable', ['compute', 'type', 'value']);

        if (!\array_key_exists('value', $read)) {
            throw InvalidQuestionnaire::because('A computed variable has its value.');
        }

        return ['compute' => Read::string($read, 'compute', 'a computed variable'), 'type' => Read::string($read, 'type', 'a computed variable'), 'value' => $read['value']];
    }

    private static function instant(?string $text): ?DateTimeImmutable
    {
        if (null === $text) {
            return null;
        }

        $instant = DateTimeImmutable::createFromFormat('!'.DateTimeInterface::ATOM, $text);

        if (false === $instant || $instant->format(DateTimeInterface::ATOM) !== $text) {
            throw InvalidQuestionnaire::because(\sprintf('An instant of the window is written as 2026-11-21T19:00:00+00:00; "%s" is not.', mb_substr($text, 0, 40)));
        }

        return $instant->setTimezone(new DateTimeZone('UTC'));
    }
}
