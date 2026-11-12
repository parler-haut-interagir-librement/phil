<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

/**
 * A dimension row rendered through a template: the template's key, and the values its fields hold.
 *
 * The key usually lives on the unlocalized row, so every locale renders the same layout, and the field values
 * on the localized rows. `TemplateMerger` reads the two in that order.
 *
 * Use `TemplateTrait` for the implementation. The persistence mapping of its properties belongs to the
 * infrastructure (`TemplateCapabilityMapping`), never to the trait.
 */
interface TemplateCapable
{
    public function getTemplateKey(): ?string;

    public function setTemplateKey(?string $templateKey): void;

    /**
     * @return array<string, mixed>
     */
    public function getTemplateData(): array;

    /**
     * @param array<string, mixed> $templateData
     */
    public function setTemplateData(array $templateData): void;
}
