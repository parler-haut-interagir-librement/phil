<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Override;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Phil\MediaBundle\Domain\ValueObject\MediaId;

/**
 * Prepares a `MediaField`: the medium found from the property's value — a `MediaId`, a `Media` or the identifier's text
 * — for its template, `null` when there is none or no longer; the picker opened on the field's collection.
 */
final readonly class MediaFieldConfigurator implements FieldConfiguratorInterface
{
    public function __construct(
        private MediaRepository $media,
    ) {
    }

    #[Override]
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return MediaField::class === $field->getFieldFqcn();
    }

    #[Override]
    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $field->setFormattedValue($this->medium($field->getValue()));
        $field->setFormTypeOptionIfNotSet('collection', $field->getCustomOption(MediaField::OPTION_COLLECTION));
    }

    private function medium(mixed $value): ?Media
    {
        if ($value instanceof Media || null === $value) {
            return $value;
        }

        if (!$value instanceof MediaId && !\is_string($value)) {
            return null;
        }

        try {
            return $this->media->get($value instanceof MediaId ? $value->value : MediaId::fromString($value)->value);
        } catch (InvalidMedia) {
            return null;
        }
    }
}
