<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Locale;
use Override;
use Phil\ContentWarnings\ContentWarning;
use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\Severity;
use Phil\ContentWarnings\WarningPreferences;
use Phil\ContentWarningsBundle\Application\Contract\WarningPreferencesStore;
use Phil\ContentWarningsBundle\Infrastructure\Form\ContentWarningsType;
use Phil\ContentWarningsBundle\Infrastructure\Form\WarningPreferencesType;
use Phil\ContentWarningsBundle\PhilContentWarningsBundle;
use Phil\ContentWarningsBundle\Tests\Functional\App\Entity\Book;
use Phil\ContentWarningsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

#[CoversClass(PhilContentWarningsBundle::class)]
final class ContentWarningsBundleTest extends KernelTestCase
{
    private const string FOLD = "{% embed '@PhilContentWarnings/fold.html.twig' with {warnings: warnings, id: 'book'} %}{% block content %}<p>The story</p>{% endblock %}{% endembed %}";

    private string $locale;

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

        $this->locale = Locale::getDefault();
        Locale::setDefault('fr_CA');
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Locale::setDefault($this->locale);
    }

    public function testWarningsAreStoredInTheirColumn(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $entityManager->persist(new Book($this->warnings()));
        $entityManager->flush();
        $entityManager->clear();

        $warnings = $entityManager->getRepository(Book::class)->findOneBy([])?->warnings;
        self::assertSame(['gore', 'crowds'], $warnings?->themes());
        self::assertSame(Severity::Severe, $warnings->get('gore')?->severity);
    }

    public function testWarnedContentIsFoldedByDefault(): void
    {
        $html = $this->render(['warnings' => $this->warnings()]);

        self::assertStringContainsString('data-controller="phil--content-warnings-bundle--reveal"', $html);
        self::assertStringContainsString("<strong>Avertissement de contenu\u{a0}:</strong> Gore (intense), Foules", $html);
        self::assertStringContainsString("<li>Gore\u{a0}: La bataille finale</li>", $html);
        self::assertStringContainsString('<summary><span class="phil-content-warning__reveal">Afficher le contenu</span><span class="phil-content-warning__conceal">Masquer le contenu</span></summary>', $html);
        self::assertStringContainsString('id="book" tabindex="-1" data-phil--content-warnings-bundle--reveal-target="content"><p>The story</p></div>', $html);
    }

    public function testHiddenContentIsNotRendered(): void
    {
        $this->preferences()->save(WarningPreferences::defaults()->withTheme('crowds', Display::Hide));
        $html = $this->render(['warnings' => $this->warnings()]);

        self::assertSame("<p class=\"phil-content-warning phil-content-warning--hidden\" role=\"note\">Masqué selon vos préférences\u{a0}: Foules.</p>", trim($html));
        self::assertSame('', trim($this->render(['warnings' => $this->warnings()], "{% embed '@PhilContentWarnings/fold.html.twig' with {warnings: warnings, hidden_notice: false} %}{% block content %}<p>The story</p>{% endblock %}{% endembed %}")));
    }

    public function testShownContentFollowsItsWarnings(): void
    {
        $this->preferences()->save(WarningPreferences::defaults(Display::Show));
        $html = $this->render(['warnings' => $this->warnings()]);

        self::assertStringContainsString('phil-content-warning--shown', $html);
        self::assertStringNotContainsString('data-controller', $html);
        self::assertStringContainsString('<div class="phil-content-warning__content" id="book"><p>The story</p></div>', $html);
        self::assertSame('<p>The story</p>', trim($this->render(['warnings' => $this->warnings()], "{% embed '@PhilContentWarnings/fold.html.twig' with {warnings: warnings, notice: false} %}{% block content %}<p>The story</p>{% endblock %}{% endembed %}")));
        self::assertSame('<p>The story</p>', trim($this->render(['warnings' => null])));
        self::assertSame('Foules / Phobies', $this->render([], "{{ content_warning_label('crowds') }} / {{ content_warning_category('crowds') }}"));
    }

    public function testTheFormsAreLabelledInTheLocale(): void
    {
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $form = $forms->createNamedBuilder('book')->add('warnings', ContentWarningsType::class, ['data' => $this->warnings()])->getForm();
        $html = $twig->createTemplate('{{ form(form) }}')->render(['form' => $form->createView()]);
        self::assertStringContainsString('Avertissements de contenu', $html);
        self::assertStringContainsString('<fieldset class="phil-content-warnings__category"><legend>Phobies</legend>', $html);
        self::assertStringContainsString('<label for="book_warnings_52">Araignées</label>', $html);
        self::assertStringContainsString('<optgroup label="Phobies">', $twig->createTemplate('{{ form_widget(form) }}')->render(['form' => $forms->createNamed('warnings', ContentWarningsType::class, null, ['expanded' => false])->createView()]));
        self::assertStringContainsString('Foules', $html);
        self::assertStringContainsString('value="crowds" checked="checked"', $html);

        $form = $forms->createNamedBuilder('member')->add('preferences', WarningPreferencesType::class)->getForm();
        $html = $twig->createTemplate('{{ form(form) }}')->render(['form' => $form->createView()]);
        self::assertStringContainsString('Contenu averti', $html);
        self::assertStringContainsString('>Replier</option>', $html);
        self::assertStringContainsString('>Comme au-dessus</option>', $html);
        self::assertStringContainsString('Araignées', $html);
    }

    private function warnings(): ContentWarnings
    {
        return ContentWarnings::of(new ContentWarning('gore', Severity::Severe, 'La bataille finale'), new ContentWarning('crowds'));
    }

    private function preferences(): WarningPreferencesStore
    {
        $store = $this->service(WarningPreferencesStore::class);
        self::assertInstanceOf(WarningPreferencesStore::class, $store);

        return $store;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(array $context, string $template = self::FOLD): string
    {
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->createTemplate($template)->render($context);
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
