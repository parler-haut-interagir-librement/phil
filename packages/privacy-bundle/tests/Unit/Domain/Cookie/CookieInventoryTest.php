<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Cookie;

use Phil\PrivacyBundle\Domain\Consent\ConsentCategory;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Cookie\CookieGroup;
use Phil\PrivacyBundle\Domain\Cookie\CookieInventory;
use Phil\PrivacyBundle\Domain\Cookie\CookieStorage;
use Phil\PrivacyBundle\Domain\Cookie\DeclaredCookie;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CookieInventory::class)]
#[CoversClass(DeclaredCookie::class)]
#[CoversClass(CookieGroup::class)]
#[CoversClass(CookieStorage::class)]
final class CookieInventoryTest extends TestCase
{
    public function testTheInventoryIsReadByCategory(): void
    {
        $inventory = CookieInventory::fromArray([
            'analytics' => [
                '_pk_id.*' => ['purpose' => ['en' => 'Tells visits apart', 'fr' => 'Distingue les visites'], 'duration' => 'P13M', 'provider' => 'Matomo'],
                '_pk_ses.*' => ['purpose' => 'One visit', 'duration' => 'PT30M'],
            ],
            'videos' => [
                'yt-remote' => ['purpose' => 'The player', 'duration' => 'session', 'storage' => 'local_storage'],
            ],
            'empty' => [],
        ], $this->policy());

        self::assertCount(3, $inventory->cookies);
        $first = $inventory->cookies[0];
        self::assertSame(['_pk_id.*', 'analytics', 'P13M', 'Matomo', CookieStorage::Cookie], [$first->name, $first->category, $first->duration, $first->provider, $first->storage]);
        self::assertSame('Distingue les visites', $first->purpose('fr'));
        self::assertSame('Tells visits apart', $first->purpose('de'));
        self::assertSame('One visit', $inventory->cookies[1]->purpose('fr'));
        self::assertSame(['_pk_id.*', '_pk_ses.*'], $inventory->namesOf('analytics'));
        self::assertSame([], $inventory->namesOf(null));
        self::assertTrue($inventory->cookies[2]->isSession());
        self::assertFalse($first->isSession());
        self::assertSame(CookieStorage::LocalStorage, $inventory->cookies[2]->storage);
    }

    public function testAPurposeWithoutEnglishFallsBackOnTheFirst(): void
    {
        self::assertSame('Premier', new DeclaredCookie('a', null, ['fr' => 'Premier', 'es' => 'Segundo'], 'P1D')->purpose('de'));
    }

    public function testAFamilyIsAPrefixAndANameIsExact(): void
    {
        $family = new DeclaredCookie('_pk_id.*', 'analytics', ['en' => 'x'], 'P1D');
        $exact = new DeclaredCookie('_visit', 'analytics', ['en' => 'x'], 'P1D');

        self::assertTrue($family->isFamily());
        self::assertTrue($family->matches('_pk_id.1.abcd'));
        self::assertTrue($family->matches('_pk_id.'));
        self::assertFalse($family->matches('_pk_id'));
        self::assertFalse($family->matches('x_pk_id.1'));
        self::assertFalse($exact->isFamily());
        self::assertTrue($exact->matches('_visit'));
        self::assertFalse($exact->matches('_visit2'));
        self::assertFalse($exact->matches('_visi'));
    }

    public function testWhatIsUndeclaredIsNamedOnceInOrder(): void
    {
        $inventory = new CookieInventory([
            new DeclaredCookie('_visit', 'analytics', ['en' => 'x'], 'P1D'),
            new DeclaredCookie('player', 'videos', ['en' => 'x'], 'P1D', storage: CookieStorage::LocalStorage),
        ]);

        self::assertTrue($inventory->declares('_visit'));
        self::assertFalse($inventory->declares('player'));
        self::assertTrue($inventory->declares('player', CookieStorage::LocalStorage));
        self::assertSame(['b', 'player', 'a'], $inventory->undeclared(['b', '_visit', 'player', 'b', 'a']));
        self::assertSame([], $inventory->undeclared([]));
    }

    public function testTheSameNameMayLiveInTwoPlaces(): void
    {
        $inventory = new CookieInventory([
            new DeclaredCookie('a', null, ['en' => 'x'], 'P1D'),
            new DeclaredCookie('a', null, ['en' => 'x'], 'P1D', storage: CookieStorage::SessionStorage),
        ]);

        self::assertCount(2, $inventory->cookies);
    }

    public function testAnAddedLineComesFirstAndKeepsEveryOther(): void
    {
        $inventory = new CookieInventory([
            new DeclaredCookie('a', 'analytics', ['en' => 'x'], 'P1D'),
            new DeclaredCookie('b', 'analytics', ['en' => 'x'], 'P1D'),
        ])->with(new DeclaredCookie('choice', null, ['en' => 'x'], 'P6M'));

        self::assertSame(['choice', 'a', 'b'], array_map(static fn (DeclaredCookie $cookie): string => $cookie->name, $inventory->cookies));
    }

    public function testTheLinesOfOneCategoryAreGivenInOrder(): void
    {
        $own = new DeclaredCookie('choice', null, ['en' => 'x'], 'P6M');
        $first = new DeclaredCookie('a', 'analytics', ['en' => 'x'], 'P1D');
        $second = new DeclaredCookie('b', 'analytics', ['en' => 'x'], 'P1D');
        $inventory = new CookieInventory([$first, $own, $second]);

        self::assertSame([$first, $second], $inventory->ofCategory('analytics'));
        self::assertSame([$own], $inventory->ofCategory(null));
        self::assertSame([], $inventory->ofCategory('videos'));
    }

    public function testTheGroupsAreTheConsentsOwnThenEachCategoryInOrder(): void
    {
        $inventory = CookieInventory::fromArray(['videos' => ['v' => ['purpose' => 'x', 'duration' => 'P1D']]])
            ->with(new DeclaredCookie('choice', null, ['en' => 'Keeps the choice'], 'P6M'));
        $groups = $inventory->groups($this->policy());

        self::assertSame([null, 'session', 'analytics', 'videos', 'empty'], array_map(static fn (CookieGroup $group): ?string => $group->category?->name, $groups));
        self::assertSame(['choice'], array_map(static fn (DeclaredCookie $cookie): string => $cookie->name, $groups[0]->cookies));
        self::assertSame([], $groups[1]->cookies);
        self::assertSame(['v'], array_map(static fn (DeclaredCookie $cookie): string => $cookie->name, $groups[3]->cookies));
        self::assertSame('choice', $inventory->cookies[0]->name);
        self::assertSame(['session', 'analytics', 'videos', 'empty'], array_map(static fn (CookieGroup $group): ?string => $group->category?->name, CookieInventory::fromArray([])->groups($this->policy())));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function badInventories(): iterable
    {
        yield 'a list' => [['a'], 'The cookie inventory maps each consent category to its cookies.'];
        yield 'a category of text' => [['analytics' => 'x'], 'The cookie inventory maps each consent category to its cookies.'];
        yield 'an unknown category' => [['marketing' => ['a' => ['purpose' => 'x', 'duration' => 'P1D']]], 'The cookie "a" is declared under "marketing", which is not a consent category.'];
        yield 'a line of text' => [['analytics' => ['a' => 'x']], 'The cookie "a" is a map with "purpose" and "duration".'];
        yield 'an unknown key' => [['analytics' => ['a' => ['purpose' => 'x', 'duration' => 'P1D', 'domain' => 'x']]], 'The cookie "a" has an unknown key "domain"'];
        yield 'no purpose' => [['analytics' => ['a' => ['duration' => 'P1D']]], 'The cookie "a" needs a "purpose"'];
        yield 'no duration' => [['analytics' => ['a' => ['purpose' => 'x']]], 'The cookie "a" needs a "purpose"'];
        yield 'a provider that is not text' => [['analytics' => ['a' => ['purpose' => 'x', 'duration' => 'P1D', 'provider' => 3]]], 'The cookie "a" needs a "purpose"'];
        yield 'an unknown storage' => [['analytics' => ['a' => ['purpose' => 'x', 'duration' => 'P1D', 'storage' => 'indexeddb']]], 'The cookie "a" needs a "purpose"'];
        yield 'a storage that is not text' => [['analytics' => ['a' => ['purpose' => 'x', 'duration' => 'P1D', 'storage' => 1]]], 'The cookie "a" needs a "purpose"'];
        yield 'purposes not by locale' => [['analytics' => ['a' => ['purpose' => ['x'], 'duration' => 'P1D']]], 'The purposes of the cookie "a" are texts by locale.'];
        yield 'a purpose not text' => [['analytics' => ['a' => ['purpose' => ['en' => 1], 'duration' => 'P1D']]], 'The purposes of the cookie "a" are texts by locale.'];
        yield 'twice' => [['analytics' => ['a' => ['purpose' => 'x', 'duration' => 'P1D']], 'videos' => ['a' => ['purpose' => 'x', 'duration' => 'P1D']]], 'The cookie "a" is declared twice.'];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('badInventories')]
    public function testABadInventoryIsRefused(array $data, string $message): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        CookieInventory::fromArray($data, $this->policy());
    }

    /**
     * @return iterable<string, array{string, string, array<string, string>, string|null, string}>
     */
    public static function badCookies(): iterable
    {
        yield 'a space' => ['a b', 'P1D', ['en' => 'x'], null, 'is not a cookie name'];
        yield 'a star alone' => ['*', 'P1D', ['en' => 'x'], null, 'is not a cookie name'];
        yield 'a star inside' => ['a*b', 'P1D', ['en' => 'x'], null, 'is not a cookie name'];
        yield 'too long' => [str_repeat('a', 129), 'P1D', ['en' => 'x'], null, 'is not a cookie name'];
        yield 'empty' => ['', 'P1D', ['en' => 'x'], null, 'is not a cookie name'];
        yield 'a duration of words' => ['a', 'a year', ['en' => 'x'], null, 'is "session" or an ISO 8601 duration'];
        yield 'an empty duration' => ['a', 'P', ['en' => 'x'], null, 'is "session" or an ISO 8601 duration'];
        yield 'no purpose' => ['a', 'P1D', [], null, 'states no purpose'];
        yield 'an empty purpose' => ['a', 'P1D', ['en' => ' '], null, 'The purpose of the cookie "a" in "en" is one line'];
        yield 'a long purpose' => ['a', 'P1D', ['en' => str_repeat('a', 501)], null, 'is one line of at most 500 characters'];
        yield 'an empty provider' => ['a', 'P1D', ['en' => 'x'], '', 'The provider of the cookie "a" is one line'];
        yield 'a provider on two lines' => ['a', 'P1D', ['en' => 'x'], "a\nb", 'The provider of the cookie "a" is one line'];
    }

    /**
     * @param array<string, string> $purposes
     */
    #[DataProvider('badCookies')]
    public function testABadCookieIsRefused(string $name, string $duration, array $purposes, ?string $provider, string $message): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        new DeclaredCookie($name, null, $purposes, $duration, $provider);
    }

    public function testAnEmptyCategoryIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The cookie "a" names an empty category.');

        CookieInventory::fromArray(['' => ['a' => ['purpose' => 'x', 'duration' => 'P1D']]]);
    }

    public function testTheLimitsOfACookieAreInclusive(): void
    {
        $cookie = new DeclaredCookie(str_repeat('a', 128).'*', null, ['en' => str_repeat('é', 500)], 'P1Y2M3W4DT5H6M7S', str_repeat('é', 500));

        self::assertTrue($cookie->isFamily());
        self::assertSame('P1Y2M3W4DT5H6M7S', $cookie->duration);
    }

    private function policy(): ConsentPolicy
    {
        return new ConsentPolicy('1', [new ConsentCategory('session', true), new ConsentCategory('analytics'), new ConsentCategory('videos'), new ConsentCategory('empty')]);
    }
}
