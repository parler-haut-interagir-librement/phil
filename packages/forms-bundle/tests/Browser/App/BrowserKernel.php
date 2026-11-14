<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Browser\App;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\FormLogic\Logic\FormLogic;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\Repository\FormRepository;
use Phil\FormsBundle\Domain\ValueObject\Field;
use ReflectionClass;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * The application the browser suite drives, with `phil/form-logic` installed as its documentation says: AssetMapper
 * maps the library's `assets/src` as `@phil/form-logic`, StimulusBundle enables its controller `logic` from
 * `assets/controllers.json`, and the page of a form, `templates/page.html.twig`, loads the import map and includes
 * the bundle's `_form.html.twig`. The project directory holds `vendor/phil/form-logic`, a link to the library, where
 * StimulusBundle looks for a package that Composer did not install there. The forms live in SQLite, in a file that
 * `install()` creates with the form `/forms/tournament`.
 */
final class BrowserKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * The tournament's registration: the captain asked of team Blue only, the armband of the captain, and a nickname
     * asked unless the team is Green — so of a visitor who has not chosen yet.
     */
    public static function tournament(): Form
    {
        return Form::create('tournament', 'Tournament', [
            Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green']),
            Field::of('captain', 'Captain', FieldType::Checkbox, shownWhen: ['==' => [['var' => 'team'], 'Blue']]),
            Field::of('armband', 'Armband size', FieldType::Text, shownWhen: ['==' => [['var' => 'captain'], true]]),
            Field::of('nickname', 'Nickname', FieldType::Text, shownWhen: ['!=' => [['var' => 'team'], 'Green']]),
        ]);
    }

    /**
     * Creates the database and its form, once.
     */
    public function install(): void
    {
        if (is_file($this->database())) {
            return;
        }

        $this->boot();
        $entityManager = $this->getContainer()->get('browser.entity_manager');
        \assert($entityManager instanceof EntityManagerInterface);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $forms = $this->getContainer()->get('browser.forms');
        \assert($forms instanceof FormRepository);
        $forms->save(self::tournament());
    }

    #[Override]
    public function getProjectDir(): string
    {
        $directory = $this->varDir().'/project';
        $link = $directory.'/vendor/phil/form-logic';

        if (!is_link($link)) {
            if (!is_dir(\dirname($link)) && !mkdir(\dirname($link), 0o777, true) && !is_dir(\dirname($link))) {
                throw new RuntimeException('Cannot make '.\dirname($link).'.');
            }

            symlink($this->library(), $link);
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

    /**
     * Its bundles, read from the directory of the class rather than from the project directory.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/config/bundles.php';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'a browser secret of sixteen bytes',
            'http_method_override' => false,
            'router' => ['utf8' => true],
            'form' => ['csrf_protection' => false],
            'csrf_protection' => false,
            'translator' => ['fallbacks' => ['en']],
            'default_locale' => 'en',
            'asset_mapper' => [
                'paths' => [
                    __DIR__.'/assets' => '',
                    $this->library().'/assets/src' => '@phil/form-logic',
                    \dirname(__DIR__).'/node_modules/@hotwired/stimulus/dist' => 'stimulus',
                ],
                'importmap_path' => __DIR__.'/importmap.php',
                'server' => true,
            ],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///'.$this->database()], 'orm' => []]);
        $container->extension('twig', ['default_path' => __DIR__.'/templates', 'strict_variables' => true]);
        $container->extension('stimulus', [
            'controller_paths' => [__DIR__.'/assets'],
            'controllers_json' => __DIR__.'/assets/controllers.json',
        ]);
        $container->extension('phil_spam_protection', ['timing' => ['minimum' => 0, 'maximum' => 600], 'proof_of_work' => 4]);
        $container->extension('phil_forms', ['template' => 'page.html.twig']);

        $services = $container->services();
        $services->alias('browser.entity_manager', 'doctrine.orm.entity_manager')->public();
        $services->alias('browser.forms', FormRepository::class)->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 3).'/config/routes.php')->prefix('/forms');
    }

    /**
     * The directory of `phil/form-logic`, wherever Composer put it.
     */
    private function library(): string
    {
        return \dirname((string) new ReflectionClass(FormLogic::class)->getFileName(), 3);
    }

    private function database(): string
    {
        return $this->varDir().'/forms.sqlite';
    }

    private function varDir(): string
    {
        return sys_get_temp_dir().'/phil-forms-bundle-browser/'.hash('xxh128', __DIR__);
    }
}
