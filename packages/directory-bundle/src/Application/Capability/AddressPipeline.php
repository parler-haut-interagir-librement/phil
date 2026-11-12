<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application\Capability;

use Phil\Address\Address;
use Phil\Address\AddressArray;
use Phil\Address\Exception\AddressException;
use Phil\ContentBundle\Application\DataMapper\SubmittedData;
use Phil\ContentBundle\Application\Pipeline\DataMapper;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\DirectoryBundle\Domain\Capability\AddressCapable;

/**
 * The postal address through the pipeline of content-bundle: `address` submitted as the array of `AddressArray` — its
 * country and its fields —, kept on the shared row, carried by publishing, and normalized back to that array.
 */
final readonly class AddressPipeline implements DataMapper, Merger, Normalizer
{
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void
    {
        if (!$unlocalized instanceof AddressCapable || !SubmittedData::has($data, 'address')) {
            return;
        }

        $address = SubmittedData::map($data, 'address');

        try {
            $unlocalized->setAddress(null === $address ? null : AddressArray::to($address));
        } catch (AddressException $addressException) {
            throw InvalidContent::because($addressException->getMessage());
        }
    }

    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        if ($target instanceof AddressCapable && $source instanceof AddressCapable && $source->getAddress() instanceof Address) {
            $target->setAddress($source->getAddress());
        }
    }

    public function normalize(DimensionContent $content): array
    {
        if (!$content instanceof AddressCapable) {
            return [];
        }

        $address = $content->getAddress();

        return ['address' => $address instanceof Address ? AddressArray::from($address) : null];
    }
}
