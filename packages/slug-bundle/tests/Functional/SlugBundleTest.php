<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Tests\Functional;

use Override;
use Phil\Slug\Sluggers;
use Phil\SlugBundle\PhilSlugBundle;
use Phil\SlugBundle\Tests\Functional\App\Service\DirectoryNames;
use Phil\SlugBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;

#[CoversClass(PhilSlugBundle::class)]
final class SlugBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
    }

    public function testSluggersAreAutowiredByName(): void
    {
        $names = $this->service(DirectoryNames::class);
        self::assertInstanceOf(DirectoryNames::class, $names);

        self::assertSame(['les-gardiens', 'gardiens'], [$names->slugger->slug('Les Gardiens')->value, $names->directorySlugger->slug('Les Gardiens')->value]);
    }

    public function testANamedSluggerIsDecoratedByTheApplication(): void
    {
        $sluggers = $this->service(Sluggers::class);
        self::assertInstanceOf(Sluggers::class, $sluggers);

        self::assertSame(['default', 'directory', 'titles'], $sluggers->names());
        self::assertSame(['saison', 'saison-2'], [$sluggers->slug('Saison 2', 'titles')->value, $sluggers->slug('Saison 2')->value]);
        self::assertSame('a-very-long-title', $sluggers->slug('A very long title that goes on', 'titles')->value);
    }

    public function testTwigHasTheFilter(): void
    {
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        self::assertSame('l-ordre|ordre|', $twig->createTemplate("{{ name|phil_slug }}|{{ name|phil_slug('directory') }}|{{ '?!'|phil_slug }}")->render(['name' => "L'Ordre"]));
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
