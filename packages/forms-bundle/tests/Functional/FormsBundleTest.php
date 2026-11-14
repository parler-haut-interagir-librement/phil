<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\Repository\FormRepository;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Infrastructure\Form\EntryType;
use Phil\FormsBundle\PhilFormsBundle;
use Phil\FormsBundle\Tests\Functional\App\SentMessages;
use Phil\FormsBundle\Tests\Functional\App\TestKernel;
use Phil\FormsBundle\Tests\Functional\App\Webhooks;
use Phil\FormsBundle\Tests\Unit\Fixtures\Forms;
use Phil\FormsBundle\Tests\Unit\Fixtures\SpamProtectionOption;
use Phil\SpamProtection\Proof\ProofOfWork;
use Phil\SpamProtectionBundle\Infrastructure\Form\SpamProtectionTypeExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms as FormFactories;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;

/**
 * A form in a real kernel: its page rendered in French with its protection, an entry submitted as a person's
 * browser would — the proof of work solved —, kept, emailed and posted to the webhook, and the page redirected.
 */
#[CoversClass(PhilFormsBundle::class)]
final class FormsBundleTest extends KernelTestCase
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
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        foreach (new SchemaTool($entityManager)->getCreateSchemaSql($entityManager->getMetadataFactory()->getAllMetadata()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testAnEntryIsKeptSentAndPosted(): void
    {
        $forms = $this->service(FormRepository::class);
        self::assertInstanceOf(FormRepository::class, $forms);
        $form = Forms::registration();
        $form->notify(['jeux@example.com'], 'https://hooks.example.com/forms');

        $forms->save($form);

        $html = (string) $this->handle(Request::create('/forms/inscription'))->getContent();
        self::assertStringContainsString('<title>Inscription au tournoi</title>', $html);
        self::assertStringContainsString('name="phil_form['.SpamProtectionTypeExtension::TOKEN.']"', $html);
        self::assertStringContainsString('>Envoyer</button>', $html);

        $response = $this->handle(Request::create('/forms/inscription', 'POST', ['phil_form' => [
            ...Forms::values(),
            'consent' => '1',
            SpamProtectionTypeExtension::HONEYPOT => '',
            SpamProtectionTypeExtension::TOKEN => $this->attribute($html, 'name="phil_form['.SpamProtectionTypeExtension::TOKEN.']"', 'value'),
            SpamProtectionTypeExtension::PROOF => $this->solve($html),
        ]], server: ['REMOTE_ADDR' => '198.51.100.4']));

        self::assertSame(303, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('/forms/inscription?sent=1', $response->headers->get('Location'));
        self::assertCount(1, $forms->entries($form));

        $sent = $this->service(SentMessages::class);
        self::assertInstanceOf(SentMessages::class, $sent);
        $email = $sent->messages[0] ?? null;
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('[Site] Inscription au tournoi', $email->getSubject());
        self::assertStringContainsString('Une nouvelle réponse à « Inscription au tournoi »', (string) $email->getTextBody());
        self::assertStringContainsString("Équipe\nBleue", (string) $email->getTextBody());
        self::assertStringContainsString("J’accepte le règlement\nOui", (string) $email->getTextBody());
        self::assertStringContainsString('<dt>Message</dt>', (string) $email->getHtmlBody());
        self::assertStringContainsString('Bonjour,<br />', (string) $email->getHtmlBody());

        $webhooks = $this->service(Webhooks::class);
        self::assertInstanceOf(Webhooks::class, $webhooks);
        self::assertSame(['POST', 'https://hooks.example.com/forms'], \array_slice($webhooks->posted[0] ?? [], 0, 2));
        self::assertStringContainsString('"type":"form.entry.submitted"', $webhooks->posted[0][2]);

        self::assertStringContainsString('Merci : votre réponse a été envoyée.', (string) $this->handle(Request::create('/forms/inscription?sent=1'))->getContent());
    }

    public function testASubmissionWithoutProtectionIsHeld(): void
    {
        $forms = $this->service(FormRepository::class);
        self::assertInstanceOf(FormRepository::class, $forms);
        $form = Forms::registration();
        $forms->save($form);

        $response = $this->handle(Request::create('/forms/inscription', 'POST', ['phil_form' => Forms::values()]));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([], $forms->entries($form));
        self::assertSame(404, $this->handle(Request::create('/forms/none'))->getStatusCode());
    }

    public function testWithPhilFormLogicThePageCarriesItsControllerAndTheStepsOfItsConditions(): void
    {
        $forms = $this->service(FormRepository::class);
        self::assertInstanceOf(FormRepository::class, $forms);
        $forms->save($this->conditional());

        $html = (string) $this->handle(Request::create('/forms/tournoi'))->getContent();

        self::assertStringContainsString('data-controller="phil--form-logic--logic"', $html);
        self::assertSame(
            [['field' => 'captain', 'when' => ['==' => [['var' => 'team'], 'Bleue']]]],
            json_decode($this->attribute($html, 'data-controller="phil--form-logic--logic"', 'data-phil--form-logic--logic-steps-value'), true, 16, \JSON_THROW_ON_ERROR),
        );
        self::assertStringContainsString('<div data-form-logic-field="captain"><label for="phil_form_captain">Capitaine</label><input type="checkbox" id="phil_form_captain" name="phil_form[captain]"', $html);
        self::assertSame(1, substr_count($html, 'data-form-logic-field='), 'only the field asked under a condition');

        $plain = $this->formFactory()->createNamed('phil_form', EntryType::class, null, ['phil_form' => Forms::registration()]);
        $page = $this->handleTwig($plain->createView());
        self::assertStringNotContainsString('data-controller="phil--form-logic--logic"', $page, 'a form without conditions');
    }

    public function testWithoutPhilFormLogicThePageIsRenderedAsBefore(): void
    {
        $form = $this->formFactory()->createNamed('phil_form', EntryType::class, null, ['phil_form' => $this->conditional()]);
        $html = $this->handleTwig($form->createView());

        self::assertStringContainsString('<form name="phil_form" method="post" class="phil-form" novalidate="novalidate">', $html);
        self::assertStringNotContainsString('data-form-logic', $html);
        self::assertStringNotContainsString('phil--form-logic--logic', $html);
        self::assertStringContainsString('name="phil_form[captain]"', $html, 'the field is rendered, the server deciding');
    }

    private function conditional(): Form
    {
        return Form::create('tournoi', 'Tournoi', [
            Field::of('team', 'Équipe', FieldType::Choice, choices: ['Bleue', 'Verte']),
            Field::of('captain', 'Capitaine', FieldType::Checkbox, shownWhen: ['==' => [['var' => 'team'], 'Bleue']]),
        ]);
    }

    /**
     * The forms of an `EntryType` without the browser's conditions, as when `phil/form-logic` is not installed.
     */
    private function formFactory(): FormFactoryInterface
    {
        return FormFactories::createFormFactoryBuilder()->addTypeExtension(new SpamProtectionOption())->addType(new EntryType())->getFormFactory();
    }

    private function handleTwig(FormView $view): string
    {
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->render('@PhilForms/_form.html.twig', ['form' => $view, 'sent' => false]);
    }

    private function attribute(string $html, string $marker, string $attribute): string
    {
        self::assertSame(1, preg_match('/<[^>]*'.preg_quote($marker, '/').'[^>]*>/', $html, $tag));
        self::assertSame(1, preg_match('/'.preg_quote($attribute, '/').'="(?<value>[^"]*)"/', $tag[0], $value));

        return html_entity_decode($value['value']);
    }

    private function solve(string $html): string
    {
        $marker = 'name="phil_form['.SpamProtectionTypeExtension::PROOF.']"';
        $challenge = $this->attribute($html, $marker, 'data-'.SpamProtectionTypeExtension::CONTROLLER.'-challenge-value');
        $proof = new ProofOfWork((int) $this->attribute($html, $marker, 'data-'.SpamProtectionTypeExtension::CONTROLLER.'-difficulty-value'));
        $solution = 0;

        while (!$proof->verify($challenge, (string) $solution)) {
            ++$solution;
        }

        return (string) $solution;
    }

    private function handle(Request $request): Response
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);

        return $kernel->handle($request);
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
