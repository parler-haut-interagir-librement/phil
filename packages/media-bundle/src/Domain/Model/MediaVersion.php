<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use Symfony\Component\Uid\Uuid;

/**
 * A previous file of a medium, kept when a new one replaced it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_media_version')]
#[ORM\UniqueConstraint(columns: ['media', 'number'])]
final class MediaVersion
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\ManyToOne(targetEntity: Media::class)]
        #[ORM\JoinColumn(name: 'media', nullable: false, onDelete: 'CASCADE')]
        public readonly Media $media,
        #[ORM\Column(name: 'number')]
        public readonly int $number,
        #[ORM\Column(name: 'filename', length: 255)]
        public readonly string $filename,
        #[ORM\Column(name: 'mime_type', length: 100)]
        public readonly string $mimeType,
        #[ORM\Column(name: 'size')]
        public readonly int $size,
        #[ORM\Column(name: 'storage_key', length: 400)]
        public readonly string $storageKey,
        #[ORM\Column(name: 'replaced_at')]
        private DateTimeImmutable $replacedAt,
    ) {
    }

    public static function of(Media $media, int $number, FileInfo $file, string $storageKey, DateTimeImmutable $replacedAt): self
    {
        return new self(Uuid::v7()->toRfc4122(), $media, $number, $file->filename, $file->mimeType, $file->size, $storageKey, $replacedAt->setTimezone(new DateTimeZone('UTC')));
    }

    public function replacedAt(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->replacedAt->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
    }
}
