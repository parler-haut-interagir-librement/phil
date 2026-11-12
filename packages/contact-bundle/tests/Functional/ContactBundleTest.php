<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Locale;
use Override;
use Phil\Contact\ContactCard;
use Phil\Contact\Email;
use Phil\Contact\JSContactCard;
use Phil\Contact\JSContactReader;
use Phil\Contact\PersonName;
use Phil\Contact\PhoneLine;
use Phil\Contact\PhoneRole;
use Phil\ContactBundle\Infrastructure\Form\ContactCardType;
use Phil\ContactBundle\Infrastructure\Http\JSContactResponse;
use Phil\ContactBundle\Infrastructure\Http\VCardResponse;
use Phil\ContactBundle\PhilContactBundle;
use Phil\ContactBundle\Tests\Functional\App\Entity\Shop;
use Phil\ContactBundle\Tests\Functional\App\TestKernel;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\Phone\PhoneNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

#[CoversClass(PhilContactBundle::class)]
final class ContactBundleTest extends KernelTestCase
{
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

    public function testACardIsStoredInItsColumn(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $entityManager->persist(new Shop($this->card()));
        $entityManager->flush();
        $entityManager->clear();

        $card = $entityManager->getRepository(Shop::class)->findOneBy([])?->contact;
        self::assertSame(['Ada Lovelace', 'ada@example.com', '+15145550123', 'adalove', 'Lovelace, Ada Augusta'], [$card?->name, (string) $card?->emails[0], (string) $card?->phones[0]->number, $card?->handles[0]->value, $card?->structuredName?->sortKey()]);
    }

    public function testTheFormIsReadInItsThemeAndTheLocale(): void
    {
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $form = $forms->createNamed('contact', ContactCardType::class, $this->card());
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $html = $twig->createTemplate('{{ form(form) }}')->render(['form' => $form->createView()]);

        self::assertStringContainsString('data-controller="phil--contact-bundle--collection"', $html);
        self::assertStringContainsString('data-prototype="', $html);
        self::assertStringContainsString('>Ajouter</button>', $html);
        self::assertStringContainsString('>Retirer</button>', $html);
        self::assertStringContainsString('Adresses courriel', $html);
        self::assertStringContainsString('>Cellulaire</option>', $html);
        self::assertStringContainsString('value="(514) 555-0123"', $html);

        $submitted = $forms->createNamed('contact', ContactCardType::class);
        $submitted->submit(['name' => 'Ada', 'emails' => ['ada']]);
        self::assertSame('Cette valeur n’est pas une adresse courriel valide.', $submitted->get('emails')->get('0')->getErrors()->current()->getMessage());
    }

    public function testTheStructuredNameIsAskedInTheLocale(): void
    {
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $html = $twig->createTemplate('{{ form(form) }}')->render(['form' => $forms->createNamed('contact', ContactCardType::class, $this->card(), ['structured_name' => true])->createView()]);

        self::assertStringContainsString('Nom en détail', $html);
        self::assertStringContainsString('Nom de famille', $html);
        self::assertStringContainsString('>Selon la culture</option>', $html);
        self::assertStringContainsString('autocomplete="given-name"', $html);
        self::assertStringContainsString('value="Augusta"', $html);
    }

    public function testACardIsReadAndServedAsJSContact(): void
    {
        $reader = $this->service('test.jscontact_reader');
        self::assertInstanceOf(JSContactReader::class, $reader);
        self::assertSame('CA', $reader->region);

        $response = new JSContactResponse(new JSContactCard('urn:uuid:1', $this->card()));
        $read = $reader->read((string) $response->getContent());

        self::assertSame(['application/jscontact+json', 'Ada Augusta Lovelace', []], [$response->headers->get('Content-Type'), $read->card->structuredName?->display(), $read->notices]);
        self::assertSame('+15145550124', (string) $reader->read('{"@type":"Card","version":"1.0","uid":"u","phones":{"p":{"number":"514 555-0124"}}}')->card->phones[0]->number);
    }

    public function testAfterAFailedSubmissionANewEntryTakesAnIndexNoEntryHas(): void
    {
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $form = $forms->createNamed('contact', ContactCardType::class);
        $form->submit(['name' => 'Ada', 'emails' => [0 => 'ada@example.com', 2 => 'ada']]);
        self::assertFalse($form->isValid());

        $html = $twig->createTemplate('{{ form_widget(form.emails) }}')->render(['form' => $form->createView()]);

        self::assertStringContainsString('name="contact[emails][2]', $html);
        self::assertStringContainsString('data-phil--contact-bundle--collection-index-value="3"', $html);
        self::assertStringContainsString('data-phil--contact-bundle--collection-index-value="0"', $twig->createTemplate('{{ form_widget(form.phones) }}')->render(['form' => $form->createView()]));
    }

    public function testACardIsJsonLdAndAVCardFile(): void
    {
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        self::assertStringStartsWith('<script type="application/ld+json">{"@context":"https://schema.org","@type":"Person","name":"Ada Lovelace"', $twig->createTemplate('{{ contact_json_ld(card) }}')->render(['card' => $this->card()]));
        self::assertStringContainsString("\r\nTEL;VALUE=uri;TYPE=cell:tel:+1-514-555-0123\r\n", $twig->createTemplate('{{ contact_vcard(card) }}')->render(['card' => $this->card()]));
        self::assertSame('text/vcard; charset=utf-8', new VCardResponse($this->card(), 'ada.vcf')->headers->get('Content-Type'));
        self::assertSame('Lovelace, Ada Augusta · Ada Augusta Lovelace', $twig->createTemplate("{{ person_name(card, 'sort') }} · {{ person_name(card.structuredName) }}")->render(['card' => $this->card()]));
    }

    private function card(): ContactCard
    {
        return ContactCard::of(
            'Ada Lovelace',
            emails: [Email::of('ada@example.com')],
            phones: [new PhoneLine(PhoneRole::Mobile, PhoneNumber::parse('+15145550123'))],
            handles: [Handle::of(KnownMessenger::Telegram, 'adalove')],
            structuredName: PersonName::of('Ada', 'Lovelace', additional: ['Augusta'], culture: 'en-GB'),
        );
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
