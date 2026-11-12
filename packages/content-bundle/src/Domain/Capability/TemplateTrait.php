<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

/**
 * The implementation of `TemplateCapable`, without any persistence mapping.
 *
 * @phpstan-require-implements TemplateCapable
 */
trait TemplateTrait
{
    private ?string $templateKey = null;

    /**
     * @var array<string, mixed>
     */
    private array $templateData = [];

    public function getTemplateKey(): ?string
    {
        return $this->templateKey;
    }

    public function setTemplateKey(?string $templateKey): void
    {
        $this->templateKey = $templateKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    /**
     * @param array<string, mixed> $templateData
     */
    public function setTemplateData(array $templateData): void
    {
        $this->templateData = $templateData;
    }
}
