<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Unit;

use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\DirectoryBundle\Application\Capability\RelatedPipeline;
use Phil\DirectoryBundle\Application\Genre;
use Phil\DirectoryBundle\Application\RelatedListings;
use Phil\DirectoryBundle\Application\Relation;
use Phil\DirectoryBundle\Application\Relations;
use Phil\DirectoryBundle\Domain\Capability\RelatedTrait;
use Phil\DirectoryBundle\Domain\Exception\ListingRefused;
use Phil\DirectoryBundle\Domain\Model\Listing;
use Phil\DirectoryBundle\Domain\Model\ListingDimension;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelatedPipeline::class)]
#[CoversClass(Relation::class)]
#[CoversClass(Relations::class)]
#[CoversClass(RelatedListings::class)]
#[CoversTrait(RelatedTrait::class)]
final class RelatedTest extends TestCase
{
    public function testTheRelatedListingsAreMappedMergedAndNormalized(): void
    {
        [$shared, $french] = $this->rows();
        $pipeline = new RelatedPipeline();

        $pipeline->map($shared, $french, ['related' => ['played_at' => [3 => ' a ', 5 => 'b', 'a', ''], 'empty' => []]]);
        self::assertSame(['played_at' => ['a', 'b']], $shared->getRelated());
        self::assertSame([], $french->getRelated());

        $merged = $this->rows()[1];
        $pipeline->merge($merged, $shared);
        $pipeline->merge($merged, $french);
        self::assertSame(['related' => ['played_at' => ['a', 'b']]], $pipeline->normalize($merged));

        $pipeline->map($shared, $french, []);
        self::assertSame(['played_at' => ['a', 'b']], $shared->getRelated(), 'absent keeps');
        $pipeline->map($shared, $french, ['related' => null]);
        self::assertSame(['related' => []], $pipeline->normalize($shared), 'null clears');

        $other = self::createStub(DimensionContent::class);
        $pipeline->map($other, $other, ['related' => ['x' => ['y']]]);
        $pipeline->merge($other, $merged);
        $pipeline->merge($merged, $other);
        self::assertSame([], $pipeline->normalize($other));
        self::assertSame(['played_at' => ['a', 'b']], $merged->getRelated());
    }

    public function testAShapeOtherThanListsByRelationIsInvalid(): void
    {
        self::assertSame(['r' => ['a', 'b'], 's' => [], 't' => ['c']], RelatedPipeline::shaped(['r' => [' a', 'a ', '  ', 'b'], 's' => [], 't' => ['c']]));

        $shape = 'The related listings are lists of identifiers by relation.';

        foreach ([[[['a']], $shape], [['r' => 'a'], $shape], [['r' => [1]], 'A related listing is named by its identifier.']] as [$related, $message]) {
            try {
                RelatedPipeline::shaped($related);
                self::fail(json_encode($related, \JSON_THROW_ON_ERROR));
            } catch (InvalidContent $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }
    }

    public function testRelationsAreFoundByKeyAndByGenre(): void
    {
        $playedAt = new Relation('played_at', 'larp', 'larp_site', 'Joué à', 'GN joués ici');
        $heardOn = new Relation('heard_on', 'larp', 'podcast', 'Entendu sur', 'GN entendus', false);
        $relations = new Relations([$playedAt, $heardOn]);

        self::assertTrue($playedAt->multiple);
        self::assertFalse($heardOn->multiple);
        self::assertSame([$playedAt, $heardOn], $relations->from('larp'));
        self::assertSame([$heardOn], $relations->to('podcast'));
        self::assertSame([], $relations->from('podcast'));
        self::assertSame($heardOn, $relations->get('heard_on'));
        self::assertTrue($relations->has('heard_on'));
        self::assertFalse($relations->has('nope'));
        self::assertFalse(new Relations()->has('heard_on'));

        $this->expectException(ListingRefused::class);
        $this->expectExceptionMessageIsOrContains('The directory has no relation "nope".');
        $relations->get('nope');
    }

    public function testARelationIsNamedLikeAGenre(): void
    {
        $this->expectException(ListingRefused::class);
        new Relation('Played at', 'larp', 'larp_site', 'a', 'b');
    }

    public function testRelatedListingsAreTitledByTheirWay(): void
    {
        $relation = new Relation('played_at', 'larp', 'larp_site', 'Joué à', 'GN joués ici');

        self::assertSame('Joué à', new RelatedListings($relation, false, new Genre('larp_site', 'terrains', 'Terrains'), [])->title());
        self::assertSame('GN joués ici', new RelatedListings($relation, true, new Genre('larp', 'gn', 'GN'), [])->title());
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
}
