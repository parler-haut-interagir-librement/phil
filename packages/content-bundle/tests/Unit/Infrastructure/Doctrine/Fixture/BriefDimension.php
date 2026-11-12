<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Phil\ContentBundle\Domain\Capability\TemplateCapable;
use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;

/**
 * A row class that maps the template key itself, longer than the bundle would, and leaves the template data
 * to the bundle.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_content_probe_brief_dimension')]
class BriefDimension extends AbstractDimensionContent implements TemplateCapable
{
    #[ORM\Column(name: 'layout', length: 200, nullable: true)]
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

    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    public function setTemplateData(array $templateData): void
    {
        $this->templateData = $templateData;
    }
}
