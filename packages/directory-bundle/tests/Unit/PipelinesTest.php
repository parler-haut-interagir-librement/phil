<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Unit;

use Closure;
use Phil\Address\Address;
use Phil\Contact\ContactCard;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\DirectoryBundle\Application\Capability\AddressPipeline;
use Phil\DirectoryBundle\Application\Capability\ContactPipeline;
use Phil\DirectoryBundle\Application\Capability\EditedByPipeline;
use Phil\DirectoryBundle\Application\Capability\LocationPipeline;
use Phil\DirectoryBundle\Domain\Capability\AddressTrait;
use Phil\DirectoryBundle\Domain\Capability\ContactTrait;
use Phil\DirectoryBundle\Domain\Capability\EditedByTrait;
use Phil\DirectoryBundle\Domain\Capability\LocationTrait;
use Phil\DirectoryBundle\Domain\Model\Listing;
use Phil\DirectoryBundle\Domain\Model\ListingDimension;
use Phil\Geo\Coordinates;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContactPipeline::class)]
#[CoversClass(AddressPipeline::class)]
#[CoversClass(LocationPipeline::class)]
#[CoversClass(EditedByPipeline::class)]
#[CoversTrait(ContactTrait::class)]
#[CoversTrait(AddressTrait::class)]
#[CoversTrait(LocationTrait::class)]
#[CoversTrait(EditedByTrait::class)]
final class PipelinesTest extends TestCase
{
    public function testEachCapabilityIsMappedMergedAndNormalized(): void
    {
        [$shared, $french] = $this->rows();
        $data = [
            'contact' => ['name' => 'Ada', 'emails' => ['ada@example.com']],
            'address' => ['country' => 'CA', 'locality' => 'Montréal'],
            'location' => '45.5,-73.5',
            'editedBy' => 'ada',
        ];

        foreach ($this->pipelines() as $pipeline) {
            $pipeline->map($shared, $french, $data);
        }

        self::assertSame('Ada', $shared->getContact()?->name);
        self::assertSame('CA', $shared->getAddress()?->country);
        self::assertSame('45.5,-73.5', $shared->getLocation()?->toString());
        self::assertSame(['ada', null, null, null], [$french->getEditedBy(), $french->getContact(), $french->getAddress(), $french->getLocation()]);
        self::assertNull($shared->getEditedBy());

        $merged = $this->rows()[1];

        foreach ($this->pipelines() as $pipeline) {
            $pipeline->merge($merged, $shared);
            $pipeline->merge($merged, $french);
        }

        $normalized = array_merge(...array_map(static fn (ContactPipeline|AddressPipeline|LocationPipeline|EditedByPipeline $pipeline): array => $pipeline->normalize($merged), $this->pipelines()));
        $contact = $normalized['contact'];
        self::assertIsArray($contact);
        self::assertSame(['Ada', ['ada@example.com']], [$contact['name'], $contact['emails']]);
        self::assertSame(['country' => 'CA', 'locality' => 'Montréal'], $normalized['address']);
        self::assertSame(['45.5,-73.5', 'ada'], [$normalized['location'], $normalized['editedBy']]);

        $empty = $this->rows()[1];
        self::assertSame(['contact' => null, 'address' => null, 'location' => null, 'editedBy' => null], array_merge(...array_map(static fn (ContactPipeline|AddressPipeline|LocationPipeline|EditedByPipeline $pipeline): array => $pipeline->normalize($empty), $this->pipelines())));

        foreach ($this->pipelines() as $pipeline) {
            $pipeline->merge($merged, $empty);
        }

        self::assertSame(['Ada', 'CA', '45.5,-73.5', 'ada'], [$merged->getContact()?->name, $merged->getAddress()?->country, $merged->getLocation()?->toString(), $merged->getEditedBy()]);
    }

    public function testNullClearsAndAbsentKeepsAndOthersAreLeftAlone(): void
    {
        [$shared, $french] = $this->rows();
        $shared->setContact(ContactCard::of('Ada'));
        $shared->setAddress(Address::of('CA'));
        $shared->setLocation(Coordinates::of(1, 2));

        $french->setEditedBy('ada');

        foreach ($this->pipelines() as $pipeline) {
            $pipeline->map($shared, $french, []);
        }

        self::assertSame(['Ada', 'CA', '1,2', 'ada'], [$shared->getContact()?->name, $shared->getAddress()?->country, $shared->getLocation()?->toString(), $french->getEditedBy()]);

        foreach ($this->pipelines() as $pipeline) {
            $pipeline->map($shared, $french, ['contact' => null, 'address' => null, 'location' => null, 'editedBy' => null]);
        }

        self::assertSame([null, null, null, null], [$shared->getContact(), $shared->getAddress(), $shared->getLocation(), $french->getEditedBy()]);

        $other = self::createStub(DimensionContent::class);

        foreach ($this->pipelines() as $pipeline) {
            $pipeline->map($other, $other, ['contact' => ['name' => 'x'], 'address' => ['country' => 'CA'], 'location' => '1,2', 'editedBy' => 'x']);
            $pipeline->merge($other, $shared);
            $pipeline->merge($shared, $other);
            self::assertSame([], $pipeline->normalize($other));
        }

        self::assertNull($shared->getContact());
    }

    public function testHalfAPlaceIsNoPlace(): void
    {
        [$shared] = $this->rows();
        Closure::bind(static function (ListingDimension $row): void {
            $row->latitude = 45.5;
        }, null, ListingDimension::class)($shared);

        self::assertNull($shared->getLocation());
    }

    public function testRefusedValuesAreInvalidContent(): void
    {
        [$shared, $french] = $this->rows();

        foreach ([
            [new ContactPipeline(), ['contact' => ['phones' => [['role' => 'nope', 'number' => '1']]]]],
            [new AddressPipeline(), ['address' => ['country' => 'ZZ']]],
            [new LocationPipeline(), ['location' => '91,0']],
        ] as [$pipeline, $data]) {
            try {
                $pipeline->map($shared, $french, $data);
                self::fail(json_encode($data, \JSON_THROW_ON_ERROR));
            } catch (InvalidContent $invalid) {
                self::assertNotSame('', $invalid->getMessage());
            }
        }
    }

    /**
     * @return array{ListingDimension, ListingDimension}
     */
    private function rows(): array
    {
        $listing = new Listing();
        $shared = $listing->createDimensionContent(DimensionKey::unlocalized(Stage::Draft, Version::first()));
        $french = $listing->createDimensionContent(DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first()));
        self::assertInstanceOf(ListingDimension::class, $shared);
        self::assertInstanceOf(ListingDimension::class, $french);

        return [$shared, $french];
    }

    /**
     * @return list<ContactPipeline|AddressPipeline|LocationPipeline|EditedByPipeline>
     */
    private function pipelines(): array
    {
        return [new ContactPipeline(), new AddressPipeline(), new LocationPipeline(), new EditedByPipeline()];
    }
}
