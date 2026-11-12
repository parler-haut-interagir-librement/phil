<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Content;

use Phil\ContentBundle\Application\Content\SingleKinds;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Normalizer\ExcerptNormalizer;
use Phil\ContentBundle\Application\Pipeline\ContentNormalizer;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Infrastructure\Http\ContentUrlGenerator;
use Phil\ContentBundle\Infrastructure\Http\ResourceRoutes;
use Phil\ContentBundle\Infrastructure\Twig\ContentRuntime;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryContents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * A kind with a single content: declared by its resource key, created once, found without an identifier.
 */
#[CoversClass(SingleKinds::class)]
#[CoversClass(ContentManager::class)]
#[CoversClass(ContentRuntime::class)]
final class SingleKindsTest extends TestCase
{
    public function testItKeepsEachKindOnceInItsOrder(): void
    {
        $singles = SingleKinds::fromArray(['home_page', 'contact', 'home_page']);

        self::assertSame(['home_page', 'contact'], $singles->all());
        self::assertTrue($singles->isSingle('contact'));
        self::assertFalse($singles->isSingle('news'));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function notResourceKeys(): iterable
    {
        yield 'upper case' => ['Home', '"Home" is not.'];
        yield 'a hyphen' => ['home-page', '"home-page" is not.'];
        yield 'an integer' => [3, '"int" is not.'];
        yield 'empty' => ['', '"" is not.'];
    }

    #[DataProvider('notResourceKeys')]
    public function testItRefusesWhatIsNotAResourceKey(mixed $key, string $end): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIs('A single kind is a resource key, a lower-case letter then up to 63 lower-case letters, digits or "_"; '.$end);

        SingleKinds::fromArray([$key]);
    }

    public function testASingleKindIsCreatedOnceAndFoundWithoutItsIdentifier(): void
    {
        $contents = new InMemoryContents(singles: ['example']);
        $home = $contents->manager->create('example', 'fr', ['slug' => 'accueil']);

        self::assertTrue($home->equals($contents->manager->single('example') ?? self::fail('The root is found.')));

        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIs('"example" is a single kind and already has its content: edit it instead.');

        $contents->manager->create('example', 'en', ['slug' => 'home']);
    }

    public function testASingleKindHasNoContentBeforeItIsCreated(): void
    {
        self::assertNull(new InMemoryContents(singles: ['example'])->manager->single('example'));
    }

    public function testAKindNotDeclaredSingleIsCreatedManyTimes(): void
    {
        $contents = new InMemoryContents();
        $contents->manager->create('example', 'fr', ['slug' => 'un']);
        $contents->manager->create('example', 'fr', ['slug' => 'deux']);

        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIs('"example" is not a single kind; declare it under "phil_content.singles".');

        $contents->manager->single('example');
    }

    public function testTheTwigFunctionReadsNothingBeforeTheSingleContentIsCreated(): void
    {
        $contents = new InMemoryContents(singles: ['example']);

        self::assertNull($this->runtime($contents)->single('example', 'fr'));
    }

    public function testTheTwigFunctionReadsNothingBeforeTheSingleContentIsPublished(): void
    {
        $contents = new InMemoryContents(singles: ['example']);
        $contents->manager->create('example', 'fr', ['excerptTitle' => 'Accueil']);

        self::assertNull($this->runtime($contents)->single('example', 'fr'));
    }

    public function testTheTwigFunctionReadsTheLiveSingleContent(): void
    {
        $contents = new InMemoryContents(singles: ['example']);
        $home = $contents->manager->create('example', 'fr', ['excerptTitle' => 'Accueil']);
        $contents->manager->publish($home, 'fr');

        self::assertSame('Accueil', $this->runtime($contents)->single('example', 'fr')['excerptTitle'] ?? null);
    }

    public function testTheTwigFunctionRefusesAKindNotDeclaredSingle(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIs('"news" is not a single kind; declare it under "phil_content.singles".');

        $this->runtime(new InMemoryContents(singles: ['example']))->single('news');
    }

    private function runtime(InMemoryContents $contents): ContentRuntime
    {
        return new ContentRuntime(
            $contents->roots,
            $contents->reader,
            new ContentNormalizer([new ExcerptNormalizer()]),
            $contents->locales,
            new ContentUrlGenerator($contents->routes, new ResourceRoutes([]), new UrlGenerator(new RouteCollection(), new RequestContext())),
            new RequestStack(),
            SingleKinds::fromArray(['example']),
        );
    }
}
