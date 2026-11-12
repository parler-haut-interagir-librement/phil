<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Unit;

use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DirectoryBundle\Application\Genre;
use Phil\DirectoryBundle\Application\Genres;
use Phil\DirectoryBundle\Domain\Exception\ListingRefused;
use Phil\DirectoryBundle\Domain\Model\Listing;
use Phil\DirectoryBundle\Domain\Model\ListingDimension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Listing::class)]
#[CoversClass(ListingRefused::class)]
#[CoversClass(Genre::class)]
#[CoversClass(Genres::class)]
final class ListingTest extends TestCase
{
    public function testAListingIsWrittenByItsAuthorAndEditors(): void
    {
        $listing = new Listing();

        self::assertSame(['listing', ListingDimension::class], [Listing::resourceKey(), Listing::dimensionContentClass()]);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $listing->id()->toString());
        self::assertNotEquals($listing->id(), new Listing()->id());
        self::assertSame([null, null, []], [$listing->genre(), $listing->author(), $listing->editors()]);
        self::assertFalse($listing->mayEdit(null));

        $listing->assign('podcast', 'ada');
        self::assertSame(['podcast', 'ada'], [$listing->genre(), $listing->author()]);
        self::assertTrue($listing->mayEdit('ada'));
        self::assertTrue($listing->isAuthor('ada'));
        self::assertFalse($listing->isAuthor(null));
        self::assertFalse($listing->mayEdit('grace'));

        $listing->addEditor('grace');
        $listing->addEditor('grace');
        $listing->addEditor('ada');
        $listing->addEditor('bob');
        self::assertSame(['grace', 'bob'], $listing->editors());
        self::assertTrue($listing->mayEdit('grace'));
        self::assertFalse($listing->isAuthor('grace'));
        self::assertFalse($listing->mayEdit(null));

        $listing->removeEditor('grace');
        $listing->removeEditor('nobody');
        self::assertSame(['bob'], $listing->editors());
        self::assertFalse($listing->mayEdit('grace'));
        self::assertSame(['a', str_repeat('a', 64), 'a_1'], [Listing::genreKey('a'), Listing::genreKey(str_repeat('a', 64)), Listing::genreKey('a_1')]);
    }

    public function testAListingRefusesWhatItCannotHold(): void
    {
        $listing = new Listing();
        $listing->assign('podcast', 'ada');

        foreach ([
            [static fn () => $listing->assign('artist', 'ada'), 'A listing is put in its genre once.'],
            [static fn () => new Listing()->assign('Podcast', 'ada'), 'A genre is a lowercase letter followed by up to 63 lowercase letters, digits or underscores; "Podcast" is not.'],
            [static fn (): string => Listing::genreKey(str_repeat('a', 65)), '"'.str_repeat('a', 65).'" is not.'],
            [static fn (): string => Listing::genreKey("a\n"), "\"a\n\" is not."],
            [static fn (): string => Listing::genreKey('1a'), '"1a" is not.'],
            [static fn () => new Listing()->assign('podcast', ' '), 'A user is named by an identifier of 1 to 255 bytes.'],
            [static fn () => $listing->addEditor(str_repeat('a', 256)), 'A user is named by an identifier of 1 to 255 bytes.'],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail($message);
            } catch (ListingRefused $refused) {
                self::assertStringEndsWith($message, $refused->getMessage());
                self::assertInstanceOf(DomainError::class, $refused);
            }
        }

        $listing->addEditor(str_repeat('a', 255));

        for ($i = 2; $i <= Listing::MAX_EDITORS; ++$i) {
            $listing->addEditor('editor'.$i);
        }

        self::assertCount(50, $listing->editors());
        $this->expectException(ListingRefused::class);
        $this->expectExceptionMessageIsOrContains('A listing has 50 editors at most.');
        $listing->addEditor('one more');
    }

    public function testAnErasedUserIsForgotten(): void
    {
        $listing = new Listing();
        $listing->assign('podcast', 'ada');
        $listing->addEditor('grace');
        $listing->addEditor('linus');

        self::assertFalse($listing->forget('nobody'));
        self::assertTrue($listing->forget('grace'));
        self::assertSame(['linus'], $listing->editors());
        self::assertTrue($listing->forget('ada'));
        self::assertNull($listing->author());
        self::assertFalse($listing->mayEdit('ada'));
        self::assertTrue($listing->mayEdit('linus'));
        self::assertFalse($listing->forget('ada'));
    }

    public function testGenres(): void
    {
        $genres = new Genres([new Genre('podcast', '/balados/', 'Balados'), new Genre('artist', 'artistes', 'Artistes')]);

        self::assertSame(['balados', 'Balados'], [$genres->get('podcast')->path, $genres->get('podcast')->title]);
        self::assertSame(['podcast', 'artist'], array_map(static fn (Genre $genre): string => $genre->key, $genres->all()));
        $this->expectException(ListingRefused::class);
        $this->expectExceptionMessageIsOrContains('The directory has no genre "museum".');
        $genres->get('museum');
    }
}
