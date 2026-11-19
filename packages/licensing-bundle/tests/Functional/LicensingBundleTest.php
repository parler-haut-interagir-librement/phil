<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\LicensingBundle\Application\ExportGuard;
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Compliance\ViolationCode;
use Phil\LicensingBundle\Domain\Contract\LicenseDeclarationRepository;
use Phil\LicensingBundle\Domain\Finder\LicenseFinder;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Phil\LicensingBundle\Domain\ValueObject\Violation;
use Phil\LicensingBundle\Infrastructure\Form\AttributionType;
use Phil\LicensingBundle\Infrastructure\Form\Finder\LicenseFinderData;
use Phil\LicensingBundle\Infrastructure\Form\Finder\LicenseFinderType;
use Phil\LicensingBundle\Infrastructure\Security\ExportVoter;
use Phil\LicensingBundle\Infrastructure\StructuredData\LicenseStructuredData;
use Phil\LicensingBundle\Infrastructure\Translation\LicenseNames;
use Phil\LicensingBundle\Infrastructure\Twig\AttributionRenderer;
use Phil\LicensingBundle\Infrastructure\Ujo\AttributionDocument;
use Phil\LicensingBundle\PhilLicensingBundle;
use Phil\LicensingBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Flow\DataStorage\InMemoryDataStorage;
use Symfony\Component\Form\Flow\FormFlowInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Twig\Environment;

/**
 * The bundle in an application: the declarations in a table, the page through Twig, the export guard, its event and
 * its voter, the license of the application in the catalog, the attribution form and the license finder.
 */
#[CoversClass(PhilLicensingBundle::class)]
final class LicensingBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());
    }

    public function testADeclaredWorkIsShownAndExported(): void
    {
        $container = self::getContainer();
        $catalog = $container->get(LicenseCatalog::class);
        self::assertInstanceOf(LicenseCatalog::class, $catalog);
        self::assertSame('House license', $catalog->get('house')->name);

        $declarations = $container->get(LicenseDeclarationRepository::class);
        self::assertInstanceOf(LicenseDeclarationRepository::class, $declarations);
        $subject = SubjectReference::of('book', '42');
        $declaration = LicenseDeclaration::declare($subject, $catalog->get(LicenseCatalog::CC_BY), new DateTimeImmutable('2026-11-08'));
        $declaration->describe('© 2026 Example Press', null, null, null, new DateTimeImmutable('2026-11-08'));
        $declaration->attribute([Attribution::of(ResourceKind::Image, 'Cover', 'house', 'Jane')], new DateTimeImmutable('2026-11-08'));

        $declarations->add($declaration);
        $manager = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        $manager->flush();
        $manager->clear();

        $guard = $container->get(ExportGuard::class);
        self::assertInstanceOf(ExportGuard::class, $guard);
        self::assertTrue($guard->check($subject)->isCompliant());
        self::assertTrue($guard->check(SubjectReference::of('book', '43'))->has(ViolationCode::NoDeclaration));

        $decisions = $container->get(AccessDecisionManagerInterface::class);
        self::assertInstanceOf(AccessDecisionManagerInterface::class, $decisions);
        self::assertTrue($decisions->decide(new NullToken(), [ExportVoter::EXPORT], $subject));
        self::assertFalse($decisions->decide(new NullToken(), [ExportVoter::EXPORT], SubjectReference::of('book', '43')));

        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->render('book.html.twig', ['subject' => $subject]);
        self::assertStringContainsString('This work is published under the Creative Commons Attribution 4.0 International.', $html);
        self::assertStringContainsString('Cover, by Jane. License: House license.', (string) preg_replace('/\s+/', ' ', $html));

        $renderer = $container->get(AttributionRenderer::class);
        self::assertInstanceOf(AttributionRenderer::class, $renderer);
        self::assertStringContainsString('Cette œuvre est publiée sous la Licence Creative Commons Attribution 4.0 International.', $renderer->html($subject, 'fr'));

        $page = $renderer->page($subject);
        self::assertNotNull($page);
        $document = $container->get(AttributionDocument::class);
        self::assertInstanceOf(AttributionDocument::class, $document);
        self::assertSame('License', $document->of($page)->blocks[0]->data['text'] ?? null);
        $structured = $container->get(LicenseStructuredData::class);
        self::assertInstanceOf(LicenseStructuredData::class, $structured);
        self::assertSame('https://creativecommons.org/licenses/by/4.0/', $structured->node($page)->get('license'));
        $names = $container->get(LicenseNames::class);
        self::assertInstanceOf(LicenseNames::class, $names);
        self::assertSame('Licence ORC', $names->name('orc', 'fr'));
    }

    public function testListenersOfTheApplicationBlockAnExport(): void
    {
        $guard = self::getContainer()->get(ExportGuard::class);
        self::assertInstanceOf(ExportGuard::class, $guard);
        self::assertEquals(
            [new Violation(ViolationCode::NoDeclaration), new Violation(ViolationCode::MissingCredit, resourceTitle: 'Attribution page')],
            $guard->check(SubjectReference::of('book', 'draft-1'))->violations,
        );
    }

    public function testTheAttributionFormAndTheLicenseFinder(): void
    {
        $container = self::getContainer();
        $forms = $container->get('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);

        $attribution = $forms->create(AttributionType::class);
        $attribution->submit(['kind' => 'image', 'title' => 'Cover', 'license' => LicenseCatalog::CC_BY, 'author' => 'Jane', 'notice' => '© Jane', 'unchanged' => '1']);
        self::assertTrue($attribution->isValid(), (string) $attribution->getErrors(true));
        self::assertEquals(Attribution::of(ResourceKind::Image, 'Cover', LicenseCatalog::CC_BY, 'Jane', notice: '© Jane', changes: 'No changes were made.'), $attribution->getData());

        $house = $forms->create(AttributionType::class);
        $house->submit(['kind' => 'map', 'title' => 'Map', 'license' => 'house', 'author' => 'Jane']);
        self::assertTrue($house->isValid(), (string) $house->getErrors(true));

        $storage = new InMemoryDataStorage('finder');
        $flow = $forms->create(LicenseFinderType::class, null, ['data_storage' => $storage]);
        self::assertInstanceOf(FormFlowInterface::class, $flow);
        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->render('@PhilLicensing/finder/form.html.twig', ['form' => $flow->createView()]);
        self::assertStringContainsString('<h2>Find a license</h2>', $html);
        self::assertStringContainsString('<li aria-current="step">Adapted material</li>', $html);
        self::assertStringContainsString('Does the work adapt material under a share-alike license?', $html);
        self::assertStringContainsString('type="radio"', $html);
        self::assertStringContainsString('>Next</button>', $html);

        $flow->submit(['adapted_material' => ['adaptedLicense' => LicenseCatalog::CC_BY_SA], 'navigator' => ['next' => '']]);
        $flow->getStepForm();
        self::assertTrue($flow->isFinished());
        $data = $flow->getData();
        self::assertInstanceOf(LicenseFinderData::class, $data);

        $finder = $container->get(LicenseFinder::class);
        self::assertInstanceOf(LicenseFinder::class, $finder);
        $recommendation = $finder->recommend($data->choices());
        self::assertStringContainsString('The material the work adapts asks for the Creative Commons Attribution-ShareAlike 4.0 International', $twig->createTemplate('{{ phil_licensing_recommendation(recommendation) }}')->render(['recommendation' => $recommendation]));
    }
}
