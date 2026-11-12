<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Publishable;

/**
 * `Publishable`, mapped: the columns `publication_state`, `published_at` and `unpublished_at`, in UTC.
 *
 * @phpstan-require-implements Publishable
 */
trait PublishableEntity
{
    #[ORM\Column(name: 'publication_state', type: Types::STRING, length: 32, options: ['default' => PublicationWorkflow::DRAFT])]
    protected string $publicationState = PublicationWorkflow::DRAFT;

    #[ORM\Column(name: 'published_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'unpublished_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $unpublishedAt = null;

    public function getPublicationState(): string
    {
        return $this->publicationState;
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function setPublicationState(string $state, array $context = []): void
    {
        $this->publicationState = $state;
    }

    public function isPublished(): bool
    {
        return PublicationWorkflow::PUBLISHED === $this->publicationState;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function unpublishedAt(): ?DateTimeImmutable
    {
        return $this->unpublishedAt;
    }

    public function markPublished(DateTimeImmutable $at): void
    {
        $this->publishedAt ??= $at;
        $this->unpublishedAt = null;
    }

    public function markUnpublished(DateTimeImmutable $at): void
    {
        $this->unpublishedAt = $at;
    }
}
