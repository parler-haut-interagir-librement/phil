<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application\Capability;

use Phil\Contact\ContactCard;
use Phil\Contact\ContactCardArray;
use Phil\Contact\Exception\ContactException;
use Phil\ContentBundle\Application\DataMapper\SubmittedData;
use Phil\ContentBundle\Application\Pipeline\DataMapper;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\DirectoryBundle\Domain\Capability\ContactCapable;

/**
 * The contact card through the pipeline of content-bundle: `contact` submitted as the array of `ContactCardArray`,
 * kept on the shared row, carried by publishing, and normalized back to that array.
 */
final readonly class ContactPipeline implements DataMapper, Merger, Normalizer
{
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void
    {
        if (!$unlocalized instanceof ContactCapable || !SubmittedData::has($data, 'contact')) {
            return;
        }

        $contact = SubmittedData::map($data, 'contact');

        try {
            $unlocalized->setContact(null === $contact ? null : ContactCardArray::to($contact));
        } catch (ContactException $contactException) {
            throw InvalidContent::because($contactException->getMessage());
        }
    }

    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        if ($target instanceof ContactCapable && $source instanceof ContactCapable && $source->getContact() instanceof ContactCard) {
            $target->setContact($source->getContact());
        }
    }

    public function normalize(DimensionContent $content): array
    {
        if (!$content instanceof ContactCapable) {
            return [];
        }

        $contact = $content->getContact();

        return ['contact' => $contact instanceof ContactCard ? ContactCardArray::from($contact) : null];
    }
}
