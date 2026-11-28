<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Content;

use DateTimeImmutable;
use DateTimeZone;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;

/**
 * A template as the field values of its localized row: the definition, the revision of the draft, when it was saved, in
 * UTC, and who saved it. A row written around the bundle is read through the same checks as a saved template.
 */
final readonly class TemplatePayload
{
    private const string MOMENT = 'Y-m-d\TH:i:s.uP';

    /**
     * @param int<1, max> $revision
     */
    public function __construct(
        public TemplateDefinition $definition,
        public int $revision,
        public DateTimeImmutable $savedAt,
        public ?string $author = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data the field values of a row
     *
     * @throws InvalidTemplate when they hold no readable template
     */
    public static function fromData(array $data, string $dimension): self
    {
        $definition = $data['definition'] ?? null;
        $revision = $data['revision'] ?? null;
        $savedAt = \is_string($data['savedAt'] ?? null) ? DateTimeImmutable::createFromFormat(self::MOMENT, $data['savedAt']) : null;
        $author = $data['author'] ?? null;

        if (!\is_array($definition) || !\is_int($revision) || $revision < 1 || !$savedAt instanceof DateTimeImmutable || (null !== $author && !\is_string($author))) {
            throw InvalidTemplate::because(\sprintf('The stored email template %s holds no readable template.', $dimension));
        }

        return new self(TemplateDefinition::fromArray($definition), $revision, $savedAt, $author);
    }

    /**
     * @return array{definition: array<string, mixed>, revision: int<1, max>, savedAt: string, author: ?string}
     */
    public function toData(): array
    {
        return [
            'definition' => $this->definition->toArray(),
            'revision' => $this->revision,
            'savedAt' => $this->savedAt->setTimezone(new DateTimeZone('UTC'))->format(self::MOMENT),
            'author' => $this->author,
        ];
    }
}
