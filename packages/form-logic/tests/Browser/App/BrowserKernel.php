<?php

declare(strict_types=1);

namespace Phil\FormLogic\Tests\Browser\App;

use Override;
use Phil\FormLogic\Logic\FormLogic;
use Phil\FormLogic\Logic\Judge;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Environment;

/**
 * The application the browser suite drives, installed as the library's documentation says: AssetMapper maps
 * `assets/src` as `@phil/form-logic`, StimulusBundle enables the controller `logic` from `assets/controllers.json`, and
 * the page loads the import map's entry point. The project directory holds `vendor/phil/form-logic`, a link to the
 * library, where StimulusBundle looks for a package that Composer did not install.
 *
 * `/` shows a form whose fields depend on each other, `/?broken=1` the same form with a rule outside the subset, and
 * `/sent` judges what the browser sent again, as a server does.
 */
final class BrowserKernel extends Kernel
{
    use MicroKernelTrait;

    /** The steps of the form, as `FormLogic::toArray()` writes them. */
    public const array STEPS = [
        ['field' => 'guest', 'when' => ['==' => [['var' => 'attending'], 'yes']]],
        ['field' => 'guest_name', 'when' => ['var' => 'guest']],
        ['field' => 'note', 'when' => ['var' => 'guest']],
        ['compute' => 'seats', 'type' => 'number', 'value' => ['if' => [['var' => 'guest'], 2, ['==' => [['var' => 'attending'], 'yes']], 1, 0]]],
    ];

    /** The same steps, the first one with an operator the subset leaves out. */
    public const array BROKEN = [
        ['field' => 'guest', 'when' => ['cat' => ['ye', 's']]],
        ['field' => 'guest_name', 'when' => ['var' => 'guest']],
    ];

    #[Override]
    public function getProjectDir(): string
    {
        $directory = $this->varDir().'/project';
        $link = $directory.'/vendor/phil/form-logic';

        if (!is_link($link)) {
            if (!is_dir(\dirname($link)) && !mkdir(\dirname($link), 0o777, true) && !is_dir(\dirname($link))) {
                throw new RuntimeException('Cannot make '.\dirname($link).'.');
            }

            symlink(\dirname(__DIR__, 3), $link);
        }

        return $directory;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->varDir().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->varDir().'/log';
    }

    public function form(Request $request): Response
    {
        $twig = $this->getContainer()->get('browser.twig');
        \assert($twig instanceof Environment);

        return new Response($twig->render('form.html.twig', [
            'steps' => $request->query->has('broken') ? self::BROKEN : FormLogic::fromArray(self::STEPS)->toArray(),
        ]));
    }

    /**
     * What the browser sent, and the server's own verdict on it.
     */
    public function sent(Request $request): JsonResponse
    {
        $entry = $request->query->all('entry');
        $answer = ['attending' => $entry['attending'] ?? null, 'guest' => isset($entry['guest']), 'guest_name' => $entry['guest_name'] ?? null, 'note' => $entry['note'] ?? null];
        $verdict = new Judge()->judge(FormLogic::fromArray(self::STEPS), $answer);

        return new JsonResponse(['sent' => array_keys($entry), 'hidden' => $verdict->hidden, 'computed' => $verdict->computed]);
    }

    /**
     * Its bundles, read from the directory of the class rather than from the project directory.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/config/bundles.php';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $library = \dirname(__DIR__, 3);
        $container->extension('framework', [
            'secret' => 'browser',
            'http_method_override' => false,
            'router' => ['utf8' => true],
            'asset_mapper' => [
                'paths' => [
                    __DIR__.'/assets' => '',
                    $library.'/assets/src' => '@phil/form-logic',
                    \dirname(__DIR__).'/node_modules/@hotwired/stimulus/dist' => 'stimulus',
                ],
                'importmap_path' => __DIR__.'/importmap.php',
                'server' => true,
            ],
        ]);
        $container->extension('twig', ['default_path' => __DIR__.'/templates']);
        $container->services()->alias('browser.twig', 'twig')->public();
        $container->extension('stimulus', [
            'controller_paths' => [__DIR__.'/assets'],
            'controllers_json' => __DIR__.'/assets/controllers.json',
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('form', '/')->controller('kernel::form');
        $routes->add('sent', '/sent')->controller('kernel::sent');
    }

    private function varDir(): string
    {
        return sys_get_temp_dir().'/phil-form-logic-browser/'.hash('xxh128', __DIR__);
    }
}
