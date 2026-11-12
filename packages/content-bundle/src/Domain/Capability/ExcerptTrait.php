<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

/**
 * The implementation of `ExcerptCapable`, without any persistence mapping.
 *
 * @phpstan-require-implements ExcerptCapable
 */
trait ExcerptTrait
{
    private ?string $excerptTitle = null;

    private ?string $excerptDescription = null;

    private ?string $excerptImage = null;

    public function getExcerptTitle(): ?string
    {
        return $this->excerptTitle;
    }

    public function setExcerptTitle(?string $excerptTitle): void
    {
        $this->excerptTitle = null === $excerptTitle ? null : CapabilityText::line($excerptTitle, 'The excerpt title');
    }

    public function getExcerptDescription(): ?string
    {
        return $this->excerptDescription;
    }

    public function setExcerptDescription(?string $excerptDescription): void
    {
        $this->excerptDescription = null === $excerptDescription ? null : CapabilityText::text($excerptDescription, 'The excerpt description');
    }

    public function getExcerptImage(): ?string
    {
        return $this->excerptImage;
    }

    public function setExcerptImage(?string $excerptImage): void
    {
        $this->excerptImage = null === $excerptImage ? null : CapabilityText::reference($excerptImage, 'The excerpt image');
    }
}
