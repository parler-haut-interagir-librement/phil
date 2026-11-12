<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Phil\MediaBundle\Infrastructure\Form\MediaPickerType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * An EasyAdmin field for a property that holds a medium — a `phil_media` column —: its thumbnail and name on the index
 * and detail pages, the picker in the form. `setCollection()` opens the picker on a collection.
 *
 *     yield MediaField::new('cover')->setCollection('covers');
 */
final class MediaField implements FieldInterface
{
    use FieldTrait;

    public const string OPTION_COLLECTION = 'phil_media_collection';

    public const string TEMPLATE = '@PhilMedia/easyadmin/field.html.twig';

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return new self()
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath(self::TEMPLATE)
            ->setFormType(MediaPickerType::class)
            ->addCssClass('field-phil-media')
            ->setCustomOption(self::OPTION_COLLECTION, null);
    }

    public function setCollection(?string $collection): self
    {
        $this->setCustomOption(self::OPTION_COLLECTION, $collection);

        return $this;
    }
}
