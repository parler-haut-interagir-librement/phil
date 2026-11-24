<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\ValueObject;

use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Model\Read;

/**
 * An end screen: the first whose condition holds for the answer is shown, the last having none, so that every
 * respondent reaches one. It may name a profile — the result a respondent is given, such as a score band — and may
 * lead elsewhere: its redirect is an absolute address on an origin the application allows, checked when the
 * questionnaire is saved and again when the respondent is sent there.
 */
final readonly class Ending
{
    public const int MAX_MESSAGE = 5000;

    private const array MEMBERS = ['name', 'title', 'message', 'profile', 'redirect', 'when'];

    public string $title;

    public string $message;

    public ?string $profile;

    /**
     * @param array<string, mixed>|null $when
     *
     * @throws InvalidQuestionnaire when a text is out of bounds
     */
    public function __construct(
        public string $name,
        string $title,
        string $message = '',
        ?string $profile = null,
        public ?string $redirect = null,
        public ?array $when = null,
    ) {
        Read::name($name, 'an ending');
        $this->title = Read::text($title, 'the title of the ending "'.$name.'"', Choice::MAX_LABEL);
        $this->message = Read::text($message, 'the message of the ending "'.$name.'"', self::MAX_MESSAGE, true);
        $this->profile = null === $profile ? null : Read::text($profile, 'the profile of the ending "'.$name.'"', Choice::MAX_LABEL);

        if (null !== $redirect && (\strlen($redirect) > 2048 || '' === trim($redirect))) {
            throw InvalidQuestionnaire::because(\sprintf('The redirect of the ending "%s" is an address of at most 2048 characters.', $name));
        }
    }

    /**
     * @throws InvalidQuestionnaire when the ending is not of the shape `toArray()` writes
     */
    public static function fromArray(mixed $data): self
    {
        $ending = Read::map($data, 'an ending', self::MEMBERS);

        return new self(
            Read::string($ending, 'name', 'an ending'),
            Read::string($ending, 'title', 'an ending'),
            Read::optionalString($ending, 'message', 'an ending') ?? '',
            Read::optionalString($ending, 'profile', 'an ending'),
            Read::optionalString($ending, 'redirect', 'an ending'),
            Read::condition($ending, 'when', 'an ending'),
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
            'message' => '' === $this->message ? null : $this->message,
            'profile' => $this->profile,
            'redirect' => $this->redirect,
            'when' => $this->when,
        ], static fn (mixed $value): bool => null !== $value);
    }
}
