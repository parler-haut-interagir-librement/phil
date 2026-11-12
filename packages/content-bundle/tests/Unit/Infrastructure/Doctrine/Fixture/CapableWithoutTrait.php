<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\ContentBundle\Domain\Capability\TemplateCapable;

/**
 * An entity that implements a capability with properties of its own names: the mapping cannot find them.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_content_probe_capable_without_trait')]
class CapableWithoutTrait implements TemplateCapable
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id = 1;

    private ?string $key = null;

    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    public function id(): int
    {
        return $this->id;
    }

    public function getTemplateKey(): ?string
    {
        return $this->key;
    }

    public function setTemplateKey(?string $templateKey): void
    {
        $this->key = $templateKey;
    }

    public function getTemplateData(): array
    {
        return $this->values;
    }

    public function setTemplateData(array $templateData): void
    {
        $this->values = $templateData;
    }
}
