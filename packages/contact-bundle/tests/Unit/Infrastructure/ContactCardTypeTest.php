<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Tests\Unit\Infrastructure;

use Phil\Contact\ContactCard;
use Phil\Contact\Email;
use Phil\Contact\PersonName;
use Phil\Contact\PhoneLine;
use Phil\Contact\PhoneRole;
use Phil\ContactBundle\Infrastructure\Form\ContactCardTransformer;
use Phil\ContactBundle\Infrastructure\Form\ContactCardType;
use Phil\ContactBundle\Infrastructure\Form\EmailAddressType;
use Phil\ContactBundle\Infrastructure\Form\EmailTransformer;
use Phil\ContactBundle\Infrastructure\Form\PersonNameTransformer;
use Phil\ContactBundle\Infrastructure\Form\PersonNameType;
use Phil\ContactBundle\Infrastructure\Form\PhoneLineTransformer;
use Phil\ContactBundle\Infrastructure\Form\PhoneLineType;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\Phone\PhoneNumber;
use Phil\PhoneBundle\Infrastructure\Form\PhoneType;
use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;

#[CoversClass(ContactCardType::class)]
#[CoversClass(ContactCardTransformer::class)]
#[CoversClass(EmailAddressType::class)]
#[CoversClass(EmailTransformer::class)]
#[CoversClass(PhoneLineType::class)]
#[CoversClass(PhoneLineTransformer::class)]
#[CoversClass(PersonNameType::class)]
#[CoversClass(PersonNameTransformer::class)]
final class ContactCardTypeTest extends TestCase
{
    public function testACardIsSubmittedWithItsCollections(): void
    {
        $form = $this->forms()->create(ContactCardType::class, null, ['region' => 'CA', 'url_types' => [KnownUrlType::Website], 'messenger_types' => [KnownMessenger::Signal, KnownMessenger::Telegram]]);
        $form->submit([
            'name' => ' Ada Lovelace ',
            'organization' => 'Analytical Engines',
            'title' => '',
            'emails' => ['ada@example.com', '', 'ADA@Example.org'],
            'phones' => [['role' => 'mobile', 'number' => '(514) 555-0123'], ['role' => 'fax', 'number' => '']],
            'urls' => ['example.com', ''],
            'handles' => [['type' => 'Phil.InstantMessenger.KnownMessenger.Telegram', 'handle' => '@AdaLove'], ['type' => 'Phil.InstantMessenger.KnownMessenger.Signal', 'handle' => '']],
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertCount(2, (array) $form->get('emails')->getData());
        $card = $form->getData();
        self::assertInstanceOf(ContactCard::class, $card);
        self::assertSame(['Ada Lovelace', 'Analytical Engines', null], [$card->name, $card->organization, $card->title]);
        self::assertSame(['ada@example.com', 'ADA@example.org'], array_map(strval(...), $card->emails));
        self::assertCount(1, $card->phones);
        self::assertSame([PhoneRole::Mobile, '+15145550123'], [$card->phones[0]->role, (string) $card->phones[0]->number]);
        self::assertSame(['https://example.com/'], array_map(static fn (TypedUrl $url): string => $url->url->toString(), $card->urls));
        self::assertSame(['adalove'], array_map(static fn (Handle $handle): string => $handle->value, $card->handles));
    }

    public function testAnEmptyCardIsNone(): void
    {
        $form = $this->forms()->create(ContactCardType::class);
        $form->submit(['name' => '', 'emails' => [''], 'phones' => [['role' => 'voice', 'number' => '']]]);

        self::assertTrue($form->isValid());
        self::assertNull($form->getData());
        self::assertSame(['phil_contact', 'This contact card is not valid.', null, null, null, false, null], [$form->getConfig()->getOption('translation_domain'), $form->getConfig()->getOption('invalid_message'), $form->getConfig()->getOption('region'), $form->getConfig()->getOption('url_types'), $form->getConfig()->getOption('messenger_types'), $form->getConfig()->getOption('structured_name'), $form->getConfig()->getOption('culture')]);
        self::assertFalse($form->has('structuredName'));
    }

    public function testACardIsShownInItsFields(): void
    {
        $card = ContactCard::of(
            'Ada',
            emails: [Email::of('ada@example.com')],
            phones: [new PhoneLine(PhoneRole::Fax, PhoneNumber::parse('+15145550123'))],
            urls: [TypedUrl::of(KnownUrlType::Website, 'https://example.com')],
            handles: [Handle::of(KnownMessenger::Telegram, 'adalove')],
        );
        $view = $this->forms('CA')->create(ContactCardType::class, $card, ['messenger_types' => [KnownMessenger::Signal, KnownMessenger::Telegram]])->createView();

        self::assertSame('Ada', $view['name']->vars['value']);
        self::assertSame('ada@example.com', $view['emails'][0]->vars['value']);
        self::assertSame('fax', $view['phones'][0]['role']->vars['value']);
        self::assertSame('(514) 555-0123', $view['phones'][0]['number']->vars['value']);
        self::assertSame('https://example.com/', $view['urls'][0]->vars['value']);
        self::assertSame('@adalove', $view['handles'][0]['handle']->vars['value']);
        self::assertCount(2, $this->vars($view['handles'][0]['type'], 'choices'));
        self::assertContains('phil_contact_collection', $this->vars($view['emails'], 'block_prefixes'));
        self::assertContains('phil_contact_entry', $this->vars($view['emails'][0], 'block_prefixes'));
        self::assertSame(['contact.emails', 'contact.title', false, false, false], [$view['emails']->vars['label'], $view['title']->vars['label'], $view['emails'][0]->vars['label'], $view['phones'][0]['role']->vars['label'], $view['phones'][0]['number']->vars['label']]);
        self::assertSame([true, true, false, false, false], [$view['emails']->vars['allow_add'], $view['emails']->vars['allow_delete'], $view['title']->vars['required'], $view['emails']->vars['required'], $view['emails'][0]->vars['required']]);
        self::assertArrayHasKey('prototype', $view['emails']->vars);

        $roles = $this->vars($view['phones'][0]['role'], 'choices');
        self::assertInstanceOf(ChoiceView::class, $roles[1]);
        self::assertSame('phone_role.mobile', $roles[1]->label);
    }

    public function testAStructuredNameGivesTheCardItsName(): void
    {
        $form = $this->forms()->create(ContactCardType::class, null, ['structured_name' => true, 'culture' => 'fr']);
        $form->submit(['name' => '', 'structuredName' => ['given' => 'Charles', 'particle' => 'de', 'family' => 'Gaulle']]);

        $card = $form->getData();
        self::assertInstanceOf(ContactCard::class, $card);
        self::assertSame(['Charles de Gaulle', 'Gaulle, Charles de', 'fr'], [$card->name, $card->structuredName?->sortKey(), $card->structuredName?->culture]);
        self::assertSame('contact.structured_name', $form->get('structuredName')->getConfig()->getOption('label'));
        self::assertFalse($form->get('structuredName')->has('culture'));
        self::assertFalse($form->get('structuredName')->isRequired());
    }

    public function testANameTheStructuredNameGivesFollowsIt(): void
    {
        $name = PersonName::of('Charles', 'Gaulle', 'de', culture: 'fr');
        $view = $this->forms()->create(ContactCardType::class, ContactCard::of(structuredName: $name), ['structured_name' => true])->createView();
        $named = $this->forms()->create(ContactCardType::class, ContactCard::of('Le Général', structuredName: $name), ['structured_name' => true])->createView();

        self::assertSame(['', 'Gaulle', 'fr'], [$view['name']->vars['value'], $view['structuredName']['family']->vars['value'], $view['structuredName']['culture']->vars['value']]);
        self::assertSame('Le Général', $named['name']->vars['value']);
        self::assertSame('Ada', new ContactCardTransformer()->transform(ContactCard::of('Ada'))['name']);
    }

    public function testOnlyTheAcceptedKindsOfAddressAreTaken(): void
    {
        $form = $this->forms()->create(ContactCardType::class, null, ['url_types' => [KnownUrlType::Facebook]]);
        $form->submit(['urls' => ['example.com']]);

        self::assertFalse($form->isValid());
    }

    /**
     * @return array<array-key, mixed>
     */
    private function vars(FormView $view, string $name): array
    {
        $value = $view->vars[$name];
        self::assertIsArray($value);

        return $value;
    }

    public function testABadEntryMakesItsFieldInvalid(): void
    {
        $form = $this->forms()->create(ContactCardType::class);
        $form->submit(['emails' => ['ada'], 'phones' => [['role' => 'voice', 'number' => '+1 514 5551']]]);

        self::assertFalse($form->isValid());
        self::assertSame('This value is not a valid email address.', $form->get('emails')->get('0')->getErrors()->current()->getMessage());
        self::assertSame(EmailType::class, $form->get('emails')->get('0')->getConfig()->getType()->getParent()?->getInnerType()::class);
        self::assertSame(['autocomplete' => 'email'], $form->get('emails')->get('0')->getConfig()->getOption('attr'));
        self::assertFalse($form->get('phones')->get('0')->isValid());
        self::assertCount(0, $form->getErrors());
    }

    public function testThePhoneRegionDefaultsToThePhoneField(): void
    {
        $form = $this->forms('FR')->create(ContactCardType::class);
        $form->submit(['phones' => [['role' => 'voice', 'number' => '01 23 45 67 89']]]);

        self::assertSame('+33123456789', (string) $form->getData()?->phones[0]->number);
    }

    public function testTheTransformers(): void
    {
        $card = new ContactCardTransformer();
        $email = new EmailTransformer();
        $line = new PhoneLineTransformer();

        self::assertSame(['name' => null, 'organization' => null, 'title' => null, 'emails' => [], 'phones' => [], 'urls' => [], 'handles' => [], 'structuredName' => null], $card->transform(null));
        self::assertNull($card->reverseTransform(['name' => null, 'organization' => null, 'title' => null, 'emails' => [], 'phones' => [], 'urls' => [], 'handles' => []]));
        self::assertSame('', $email->transform(null));
        self::assertNull($email->reverseTransform(null));
        self::assertSame(['role' => PhoneRole::Voice, 'number' => null], $line->transform(null));
        self::assertSame(PhoneRole::Voice, $line->reverseTransform(['role' => null, 'number' => PhoneNumber::parse('+15145550123')])?->role);
        self::assertNull($line->reverseTransform(['role' => PhoneRole::Fax, 'number' => null]));

        try {
            $email->reverseTransform('ada');
            self::fail('A bad email was read.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertSame('"ada" is not an email address.', $transformationFailedException->getMessage());
            self::assertNotNull($transformationFailedException->getPrevious());
        }

        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessageIsOrContains('The name of a contact card is 255 characters or fewer.');

        $card->reverseTransform(['name' => str_repeat('a', 256), 'organization' => null, 'title' => null, 'emails' => [], 'phones' => [], 'urls' => [], 'handles' => []]);
    }

    private function forms(?string $region = null): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addType(new PhoneType($region))
            ->getFormFactory();
    }
}
