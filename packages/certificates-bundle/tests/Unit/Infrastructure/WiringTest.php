<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Infrastructure;

use LogicException;
use Override;
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Application\CertificatePdf;
use Phil\CertificatesBundle\Application\CertificateStatusLists;
use Phil\CertificatesBundle\Application\ContinuingEducation;
use Phil\CertificatesBundle\Application\Contract\CertificateUrls;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Application\ExpiryReminders;
use Phil\CertificatesBundle\Application\Revocation;
use Phil\CertificatesBundle\Application\StatusListLayout;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Contract\CourseCompletions;
use Phil\CertificatesBundle\Domain\Contract\CourseRepository;
use Phil\CertificatesBundle\Domain\Contract\LearningActivityRepository;
use Phil\CertificatesBundle\Infrastructure\Console\AwardCommand;
use Phil\CertificatesBundle\Infrastructure\Console\CourseCommand;
use Phil\CertificatesBundle\Infrastructure\Console\CreditCommand;
use Phil\CertificatesBundle\Infrastructure\Console\CreditsCommand;
use Phil\CertificatesBundle\Infrastructure\Console\ExportCommand;
use Phil\CertificatesBundle\Infrastructure\Console\RecertifyCommand;
use Phil\CertificatesBundle\Infrastructure\Console\RemindCommand;
use Phil\CertificatesBundle\Infrastructure\Console\RevokeCommand;
use Phil\CertificatesBundle\Infrastructure\Console\StatusCommand;
use Phil\CertificatesBundle\Infrastructure\DependencyInjection\CertificatesServices;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineCertificateRepository;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineCourseRepository;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineLearningActivityRepository;
use Phil\CertificatesBundle\Infrastructure\Http\RegisterController;
use Phil\CertificatesBundle\Infrastructure\Routing\RoutedCertificateUrls;
use Phil\CertificatesBundle\PhilCertificatesBundle;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PagedMediaBundle\Application\PagedMedia;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The wiring the bundle declares, read on the `ContainerBuilder`.
 */
#[CoversClass(PhilCertificatesBundle::class)]
#[CoversClass(CertificatesServices::class)]
final class WiringTest extends TestCase
{
    public function testTheExtensionAlias(): void
    {
        self::assertSame('phil_certificates', $this->extension()->getAlias());
    }

    public function testTheServicesOfTheDefaultConfiguration(): void
    {
        $container = $this->load([]);
        $certificates = new Reference(CertificateRepository::class);
        $courses = new Reference(CourseRepository::class);
        $urls = new Reference(CertificateUrls::class);
        $clock = new Reference('clock');
        $layout = new Reference(StatusListLayout::class);

        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineCourseRepository::class)->getArguments());
        self::assertSame(DoctrineCourseRepository::class, (string) $container->getAlias(CourseRepository::class));
        self::assertEquals([new Reference('doctrine.orm.entity_manager'), new Reference('doctrine')], $container->getDefinition(DoctrineCertificateRepository::class)->getArguments());
        self::assertSame(DoctrineCertificateRepository::class, (string) $container->getAlias(CertificateRepository::class));

        self::assertSame(['certificates', 131_072], $container->getDefinition(StatusListLayout::class)->getArguments());
        self::assertEquals([$certificates, $layout, null], $container->getDefinition(CertificateStatusLists::class)->getArguments());
        self::assertEquals([new Reference('router'), new Reference(StatusListUrls::class)], $container->getDefinition(RoutedCertificateUrls::class)->getArguments());
        self::assertSame(RoutedCertificateUrls::class, (string) $container->getAlias(CertificateUrls::class));
        self::assertEquals([$courses], $container->getDefinition(Courses::class)->getArguments());
        $events = new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        self::assertEquals([new Reference(Courses::class), $certificates, new Reference(CredentialIssuer::class), new Reference(Securing::class), $urls, $clock, $layout, new Reference('lock.factory', ContainerInterface::NULL_ON_INVALID_REFERENCE), $events, new Reference(LearningActivityRepository::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(CourseCompletions::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(Awarding::class)->getArguments());
        self::assertEquals([$certificates, $clock, new Reference('event_dispatcher'), [60, 30, 7]], $container->getDefinition(ExpiryReminders::class)->getArguments());
        self::assertEquals([new Reference(LearningActivityRepository::class), new Reference(Courses::class), $certificates, $clock, $events], $container->getDefinition(ContinuingEducation::class)->getArguments());
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineLearningActivityRepository::class)->getArguments());
        self::assertSame(DoctrineLearningActivityRepository::class, (string) $container->getAlias(LearningActivityRepository::class));
        self::assertEquals([new Reference(Awarding::class), $urls], $container->getDefinition(RecertifyCommand::class)->getArguments());
        self::assertEquals([new Reference(ExpiryReminders::class)], $container->getDefinition(RemindCommand::class)->getArguments());
        self::assertEquals([new Reference(ContinuingEducation::class)], $container->getDefinition(CreditCommand::class)->getArguments());
        self::assertEquals([new Reference(ContinuingEducation::class), $clock], $container->getDefinition(CreditsCommand::class)->getArguments());
        self::assertEquals([$certificates, $clock, $events], $container->getDefinition(Revocation::class)->getArguments());
        self::assertEquals([new Reference(Courses::class), $urls], $container->getDefinition(CourseCommand::class)->getArguments());
        self::assertEquals([new Reference(Awarding::class), $urls], $container->getDefinition(AwardCommand::class)->getArguments());
        self::assertEquals([new Reference(Revocation::class)], $container->getDefinition(RevokeCommand::class)->getArguments());
        self::assertEquals([$certificates, $clock], $container->getDefinition(StatusCommand::class)->getArguments());
        self::assertEquals([$certificates], $container->getDefinition(ExportCommand::class)->getArguments());

        foreach ([CourseCommand::class, AwardCommand::class, RevokeCommand::class, StatusCommand::class, ExportCommand::class, RecertifyCommand::class, RemindCommand::class, CreditCommand::class, CreditsCommand::class] as $command) {
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'));
        }

        self::assertEquals([
            new Reference(PagedMedia::class),
            $urls,
            new Reference('translator'),
            new Reference(Issuer::class),
            new Definition(PageSize::class)->setFactory([PageSize::class, 'letter']),
            StandardFont::Helvetica,
            StandardFont::HelveticaBold,
            true,
            false,
            false,
        ], $container->getDefinition(CertificatePdf::class)->getArguments());
        self::assertEquals([$courses, $certificates, new Reference(CertificatePdf::class), new Reference('twig'), new Reference('router'), $clock, PhilCertificatesBundle::DEFAULT_LAYOUT], $container->getDefinition(RegisterController::class)->getArguments());
        self::assertTrue($container->getDefinition(RegisterController::class)->hasTag('controller.service_arguments'));
    }

    public function testTheConfigurationIsPassedOn(): void
    {
        $container = $this->load([
            'clock' => 'app.clock',
            'lock_factory' => 'app.locks',
            'reminders' => ['days' => [90, 14]],
            'status_lists' => ['prefix' => 'awards', 'size' => 8, 'others' => 'app.lists'],
            'pdf' => ['page_size' => 'a4', 'font' => '/fonts/regular.ttf', 'bold_font' => '/fonts/bold.ttf', 'bake' => false, 'archival' => true, 'sign' => true],
            'register' => ['layout' => 'base.html.twig'],
        ]);

        self::assertSame(['awards', 8], $container->getDefinition(StatusListLayout::class)->getArguments());
        self::assertSame([90, 14], $container->getDefinition(ExpiryReminders::class)->getArgument(3));
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(ExpiryReminders::class)->getArgument(1));
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(ContinuingEducation::class)->getArgument(3));
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(CreditsCommand::class)->getArgument(1));
        self::assertEquals(new Reference('app.lists'), $container->getDefinition(CertificateStatusLists::class)->getArgument(2));
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(Awarding::class)->getArgument(5));
        self::assertEquals(new Reference('app.locks'), $container->getDefinition(Awarding::class)->getArgument(7));
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(StatusCommand::class)->getArgument(1));
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(Revocation::class)->getArgument(1));
        $pdf = $container->getDefinition(CertificatePdf::class)->getArguments();
        self::assertEquals(new Definition(PageSize::class)->setFactory([PageSize::class, 'a4']), $pdf[4]);
        self::assertEquals(new Definition(TrueTypeFont::class)->setFactory([TrueTypeFont::class, 'fromFile'])->setArguments(['/fonts/regular.ttf']), $pdf[5]);
        self::assertEquals(new Definition(TrueTypeFont::class)->setFactory([TrueTypeFont::class, 'fromFile'])->setArguments(['/fonts/bold.ttf']), $pdf[6]);
        self::assertSame([false, true, true], [$pdf[7], $pdf[8], $pdf[9]]);
        self::assertSame('base.html.twig', $container->getDefinition(RegisterController::class)->getArgument(6));
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(RegisterController::class)->getArgument(5));
    }

    public function testWithoutDoctrineNorTwigTheApplicationGivesTheRepositoriesAndHasNoRegister(): void
    {
        $container = $this->load([], []);

        self::assertFalse($container->hasDefinition(DoctrineCourseRepository::class));
        self::assertFalse($container->hasAlias(CourseRepository::class));
        self::assertFalse($container->hasDefinition(RegisterController::class));
        self::assertTrue($container->hasDefinition(Awarding::class));
        self::assertFalse($this->load(['register' => ['enabled' => false]])->hasDefinition(RegisterController::class));
    }

    public function testAwardsCanGoWithoutALock(): void
    {
        self::assertNull($this->load(['lock_factory' => null])->getDefinition(Awarding::class)->getArgument(7));
    }

    public function testWithoutTheConsoleComponentThereIsNoCommand(): void
    {
        $container = $this->container([], []);
        CertificatesServices::register($container, [
            'clock' => 'clock',
            'lock_factory' => null,
            'reminders' => ['days' => [30]],
            'status_lists' => ['prefix' => 'certificates', 'size' => 8, 'others' => null],
            'pdf' => ['page_size' => 'letter', 'font' => null, 'bold_font' => null, 'bake' => true, 'archival' => false, 'sign' => false],
            'register' => ['enabled' => false, 'layout' => PhilCertificatesBundle::DEFAULT_LAYOUT],
        ], false, false);

        self::assertTrue($container->hasDefinition(Revocation::class));

        foreach ([CourseCommand::class, AwardCommand::class, RevokeCommand::class, StatusCommand::class, ExportCommand::class, RecertifyCommand::class, RemindCommand::class, CreditCommand::class, CreditsCommand::class] as $command) {
            self::assertFalse($container->hasDefinition($command));
        }
    }

    public function testTheSmallestListAndBothPageSizesAreAccepted(): void
    {
        $container = $this->load(['status_lists' => ['size' => 1], 'pdf' => ['page_size' => 'letter']]);

        self::assertSame(['certificates', 1], $container->getDefinition(StatusListLayout::class)->getArguments());
        self::assertSame([1, 3650], $this->load(['reminders' => ['days' => [1, 3650]]])->getDefinition(ExpiryReminders::class)->getArgument(3));
        self::assertEquals(new Definition(PageSize::class)->setFactory([PageSize::class, 'letter']), $container->getDefinition(CertificatePdf::class)->getArgument(4));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'a prefix with a dot' => [['status_lists' => ['prefix' => 'a.b']]];
        yield 'a prefix not a string' => [['status_lists' => ['prefix' => 12]]];
        yield 'an empty list' => [['status_lists' => ['size' => 0]]];
        yield 'another page size' => [['pdf' => ['page_size' => 'a5']]];
        yield 'an empty clock' => [['clock' => '']];
        yield 'an empty layout' => [['register' => ['layout' => '']]];
        yield 'no reminder' => [['reminders' => ['days' => []]]];
        yield 'a reminder on the last moment' => [['reminders' => ['days' => [0]]]];
        yield 'a reminder ten years and a day before' => [['reminders' => ['days' => [3651]]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testAConfigurationThatDoesNotHoldIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load($config);
    }

    public function testThePrependNamesTheStatusListsAndMapsTheEntities(): void
    {
        $container = $this->container(['DoctrineBundle' => true], ['phil_open_badges', 'phil_paged_media', 'doctrine']);
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['status_lists' => ['service' => CertificateStatusLists::class]]], $container->getExtensionConfig('phil_open_badges'));
        self::assertSame([['orm' => ['mappings' => ['PhilCertificatesBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 3).'/src/Domain/Model',
            'prefix' => 'Phil\CertificatesBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));

        $withoutDoctrine = $this->container([], ['phil_open_badges', 'phil_paged_media', 'doctrine']);
        $extension->prepend($withoutDoctrine);
        self::assertSame([], $withoutDoctrine->getExtensionConfig('doctrine'));
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function missingBundles(): iterable
    {
        yield 'open badges' => [['phil_paged_media'], 'PhilCertificatesBundle needs PhilOpenBadgesBundle, from phil/open-badges-bundle: register it too.'];
        yield 'paged media' => [['phil_open_badges'], 'PhilCertificatesBundle needs PhilPagedMediaBundle, from phil/paged-media-bundle: register it too.'];
    }

    /**
     * @param list<string> $extensions
     */
    #[DataProvider('missingBundles')]
    public function testTheBundlesItBuildsOnAreRequired(array $extensions, string $message): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $extension->prepend($this->container([], $extensions));
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilCertificatesBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, bool>  $bundles
     */
    private function load(array $config, array $bundles = ['DoctrineBundle' => true, 'TwigBundle' => true]): ContainerBuilder
    {
        $container = $this->container($bundles, []);
        $this->extension()->load([$config], $container);

        return $container;
    }

    /**
     * @param array<string, bool> $bundles
     * @param list<string>        $extensions
     */
    private function container(array $bundles, array $extensions): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);

        foreach ($extensions as $alias) {
            $container->registerExtension(new class($alias) extends Extension {
                public function __construct(private readonly string $alias)
                {
                }

                #[Override]
                public function load(array $configs, ContainerBuilder $container): void
                {
                }

                #[Override]
                public function getAlias(): string
                {
                    return $this->alias;
                }
            });
        }

        return $container;
    }
}
