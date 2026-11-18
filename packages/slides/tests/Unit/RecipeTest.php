<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Unit;

use Phil\Slides\Domain\AspectRatio;
use Phil\Slides\Domain\Slicer;
use Phil\Slides\Domain\SplitMode;
use Phil\Slides\Infrastructure\Http\PresenterController;
use Phil\Slides\PhilSlidesBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle, copies a configuration the bundle accepts as is, and a routes file whose
 * import of the presenter routes is left commented; the routes file of the bundle declares both presenter routes.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsConfiguration(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_slides.yaml');

        self::assertSame(['bundles' => [PhilSlidesBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        self::assertSame(['phil_slides' => ['split' => 'auto', 'heading_level' => 2, 'aspect_ratio' => '16:9', 'presenter_role' => PhilSlidesBundle::DEFAULT_PRESENTER_ROLE]], $config);
    }

    public function testTheBundleAcceptsTheCopiedConfiguration(): void
    {
        /** @var array{phil_slides: array<string, mixed>} $config */
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_slides.yaml');
        $extension = new PhilSlidesBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $extension->load([$config['phil_slides']], $container);

        self::assertEquals([SplitMode::Auto, 2, 'notes', Slicer::DEFAULT_MAX_SLIDES, AspectRatio::Wide], $container->getDefinition(Slicer::class)->getArguments());
        self::assertSame(PhilSlidesBundle::DEFAULT_PRESENTER_ROLE, $container->getDefinition(PresenterController::class)->getArgument(4));
    }

    public function testTheRoutesAreLeftForTheApplicationToImport(): void
    {
        $routes = (string) file_get_contents(self::RECIPE.'/config/routes/phil_slides.yaml');

        self::assertNull(Yaml::parse($routes));
        // The import, uncommented, after the line that holds a lone "#".
        [, $import] = explode("\n#\n", $routes, 2);
        self::assertSame(['phil_slides_presenter' => ['resource' => '@PhilSlidesBundle/config/routes.php', 'prefix' => '/presenter']], Yaml::parse(implode("\n", array_map(
            static fn (string $line): string => substr($line, 1),
            explode("\n", $import),
        ))));
    }

    public function testTheRoutesFileDeclaresThePresenterRoutes(): void
    {
        $routes = new PhpFileLoader(new FileLocator(__DIR__.'/../../config'))->load('routes.php');

        self::assertSame([PresenterController::VIEW_ROUTE, PresenterController::NOTES_PDF_ROUTE], array_keys($routes->all()));
        $view = $routes->get(PresenterController::VIEW_ROUTE);
        $pdf = $routes->get(PresenterController::NOTES_PDF_ROUTE);
        self::assertNotNull($view);
        self::assertNotNull($pdf);
        self::assertSame(['/{deck}', [PresenterController::class, 'view'], ['GET', 'HEAD']], [$view->getPath(), $view->getDefault('_controller'), $view->getMethods()]);
        self::assertSame(['/{deck}/notes.pdf', [PresenterController::class, 'notesPdf'], ['GET', 'HEAD']], [$pdf->getPath(), $pdf->getDefault('_controller'), $pdf->getMethods()]);
        self::assertSame(PresenterController::DECK, $view->getRequirement('deck'));
        self::assertSame(PresenterController::DECK, $pdf->getRequirement('deck'));
    }
}
