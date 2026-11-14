<?php

declare(strict_types=1);

namespace Phil\UxKitShadcn\Tests\Functional;

use Override;
use Phil\UxKitShadcn\Tests\Functional\App\InstallKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\UX\Toolkit\UXToolkitBundle;

/**
 * The recipes installed by `ux:install` in the subdirectory `ui` of the components and of the controllers: the
 * Toolkit prefixes the Stimulus identifiers of the kit's own controllers, `data-controller`, `data-action` and the
 * `data-<identifier>-*` attributes, written in markup or in a Twig hash, and leaves those of a bundle's controller as
 * they are.
 */
#[CoversNothing]
final class SubdirectoryInstallTest extends KernelTestCase
{
    private string $destination;

    #[Override]
    protected static function getKernelClass(): string
    {
        return InstallKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!class_exists(UXToolkitBundle::class)) {
            self::markTestSkipped('The UX Toolkit is installed for development only.');
        }

        $this->destination = sys_get_temp_dir().'/phil-ux-kit-shadcn-install/'.bin2hex(random_bytes(8));
        new Filesystem()->mkdir($this->destination);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->destination);
        parent::tearDown();
    }

    public function testTheCarouselIsInstalledUnderTheSubdirectoryWithItsIdentifierPrefixed(): void
    {
        $this->install('carousel');

        self::assertFileExists($this->destination.'/assets/controllers/ui/carousel_controller.js');
        self::assertFileDoesNotExist($this->destination.'/assets/controllers/carousel_controller.js');

        $root = $this->read('templates/components/ui/Carousel.html.twig');
        self::assertStringContainsString("'data-controller': 'ui--carousel'", $root);
        self::assertStringContainsString("'data-action': 'keydown->ui--carousel#handleKeydown mouseenter->ui--carousel#hold mouseleave->ui--carousel#release focusin->ui--carousel#hold focusout->ui--carousel#release'", $root);
        self::assertStringContainsString('data-ui--carousel-orientation-value="{{ orientation }}"', $root);
        self::assertStringContainsString('data-ui--carousel-target="toggle" data-action="click->ui--carousel#toggleAutoplay"', $root);
        self::assertStringNotContainsString('data-carousel-', $root);

        $previous = $this->read('templates/components/ui/Carousel/Previous.html.twig');
        self::assertStringContainsString('data-ui--carousel-target="previous"', $previous);
        self::assertStringContainsString('click->ui--carousel#scrollPrev', $previous);

        self::assertStringContainsString('data-ui--carousel-target="viewport"', $this->read('templates/components/ui/Carousel/Content.html.twig'));
    }

    public function testTheControllerOfABundleKeepsItsIdentifier(): void
    {
        $this->install('autosave-status');

        $status = $this->read('templates/components/ui/AutosaveStatus.html.twig');
        self::assertStringContainsString('data-phil--forms-autosave--autosave-target="status"', $status);
        self::assertStringContainsString('data-action="phil--forms-autosave--autosave#discard"', $status);
        self::assertStringNotContainsString('ui--phil', $status);
    }

    public function testAnOwnControllerReachedThroughAHashIsPrefixed(): void
    {
        $this->install('animated-headline');

        $headline = $this->read('templates/components/ui/AnimatedHeadline.html.twig');
        self::assertStringContainsString("'data-controller': 'ui--animated-headline'", $headline);
        self::assertStringContainsString('data-ui--animated-headline-pause-value="{{ pause }}"', $headline);
        self::assertFileExists($this->destination.'/assets/controllers/ui/animated_headline_controller.js');
    }

    private function install(string $recipe): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $tester = new CommandTester($application->find('ux:install'));

        $tester->execute(['recipe' => $recipe, '--kit' => InstallKernel::KIT, '--destination' => $this->destination], ['interactive' => false]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('The recipe has been installed.', $tester->getDisplay());
    }

    private function read(string $path): string
    {
        self::assertFileExists($this->destination.'/'.$path);

        return (string) file_get_contents($this->destination.'/'.$path);
    }
}
