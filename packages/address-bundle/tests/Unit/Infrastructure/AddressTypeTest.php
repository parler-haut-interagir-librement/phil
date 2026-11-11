<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Tests\Unit\Infrastructure;

use Locale;
use Override;
use Phil\Address\Address;
use Phil\Address\AddressField;
use Phil\AddressBundle\Infrastructure\Form\AddressTransformer;
use Phil\AddressBundle\Infrastructure\Form\AddressType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

#[CoversClass(AddressType::class)]
#[CoversClass(AddressTransformer::class)]
final class AddressTypeTest extends TestCase
{
    private string $locale;

    #[Override]
    protected function setUp(): void
    {
        $this->locale = Locale::getDefault();
        Locale::setDefault('fr');
    }

    #[Override]
    protected function tearDown(): void
    {
        Locale::setDefault($this->locale);
    }

    public function testTheFieldsFollowTheDefaultCountry(): void
    {
        $form = $this->forms('CA')->create(AddressType::class);

        self::assertSame(['country', 'addressLine1', 'addressLine2', 'addressLine3', 'dependentLocality', 'locality', 'administrativeArea', 'postalCode', 'sortingCode'], array_keys($form->all()));
        self::assertSame(['address.postal_code.postal', true, false], [$form->get('postalCode')->getConfig()->getOption('label'), $form->get('postalCode')->getConfig()->getOption('required'), $form->get('postalCode')->getConfig()->getOption('disabled')]);
        self::assertSame(['address.field.sortingCode', false, true, ['data-phil--address-bundle--address-target' => 'row', 'data-field' => 'sortingCode', 'hidden' => 'hidden']], [$form->get('sortingCode')->getConfig()->getOption('label'), $form->get('sortingCode')->getConfig()->getOption('required'), $form->get('sortingCode')->getConfig()->getOption('disabled'), $form->get('sortingCode')->getConfig()->getOption('row_attr')]);
        self::assertSame(['data-phil--address-bundle--address-target' => 'row', 'data-field' => 'postalCode'], $form->get('postalCode')->getConfig()->getOption('row_attr'));
        self::assertSame(ChoiceType::class, $form->get('administrativeArea')->getConfig()->getType()->getInnerType()::class);
        $choices = $form->get('administrativeArea')->getConfig()->getOption('choices');
        self::assertIsArray($choices);
        self::assertSame('QC', $choices['Québec (QC)'] ?? null);
        self::assertSame(['address.administrative_area.province', true, false, ['data-phil--address-bundle--address-target' => 'row', 'data-field' => 'administrativeArea']], [$form->get('administrativeArea')->getConfig()->getOption('label'), $form->get('administrativeArea')->getConfig()->getOption('required'), $form->get('administrativeArea')->getConfig()->getOption('disabled'), $form->get('administrativeArea')->getConfig()->getOption('row_attr')]);
        self::assertSame('', $form->get('administrativeArea')->getConfig()->getOption('placeholder'));
        self::assertFalse($form->get('administrativeArea')->getConfig()->getOption('choice_translation_domain'));
        self::assertSame(['phil_address', 'This address is not valid.', null, false], [$form->getConfig()->getOption('translation_domain'), $form->getConfig()->getOption('invalid_message'), $form->getConfig()->getOption('countries'), $form->getConfig()->getOption('with_recipient')]);
        self::assertSame(['address.field.country', '', ['data-phil--address-bundle--address-target' => 'country', 'data-action' => 'phil--address-bundle--address#change', 'autocomplete' => 'country']], [$form->get('country')->getConfig()->getOption('label'), $form->get('country')->getConfig()->getOption('placeholder'), $form->get('country')->getConfig()->getOption('attr')]);
    }

    public function testADefaultCountryInLowercaseIsTaken(): void
    {
        self::assertFalse($this->forms('ca')->create(AddressType::class)->get('addressLine1')->getConfig()->getOption('disabled'));
    }

    public function testNoSubmittedDataHidesEveryField(): void
    {
        $form = $this->forms('CA')->create(AddressType::class, null, ['required' => false]);
        $form->submit(null);

        self::assertNull($form->getData());
        self::assertTrue($form->get('addressLine1')->getConfig()->getOption('disabled'));
    }

    public function testWithoutCountryEveryFieldIsHidden(): void
    {
        $form = $this->forms()->create(AddressType::class);

        self::assertTrue($form->get('addressLine1')->getConfig()->getOption('disabled'));
        self::assertSame(TextType::class, $form->get('administrativeArea')->getConfig()->getType()->getInnerType()::class);
        self::assertNull($form->getConfig()->getOption('default_country'));
    }

    public function testAnAddressIsSubmittedForTheCountryChosen(): void
    {
        $form = $this->forms('CA')->create(AddressType::class);
        $form->submit(['country' => ' fr ', 'addressLine1' => '10 rue de Rivoli', 'locality' => 'Paris', 'postalCode' => '75001', 'administrativeArea' => 'QC']);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        $address = $form->getData();
        self::assertInstanceOf(Address::class, $address);
        self::assertSame(['FR', ['addressLine1' => '10 rue de Rivoli', 'locality' => 'Paris', 'postalCode' => '75001']], [$address->country, $address->fields()]);
        self::assertTrue($form->get('administrativeArea')->getConfig()->getOption('disabled'));
    }

    public function testAWrongAddressGetsAnErrorOnEachField(): void
    {
        $form = $this->forms('CA', translator: true)->create(AddressType::class);
        $form->submit(['country' => 'CA', 'addressLine1' => '1 rue', 'locality' => 'Montréal', 'administrativeArea' => 'QC', 'postalCode' => '12345']);

        self::assertFalse($form->isValid());
        self::assertSame(['translated: This postal code is not one of this country or region.'], $this->errors($form->get('postalCode')));
    }

    public function testAMissingFieldWithoutTranslatorKeepsTheMessage(): void
    {
        $form = $this->forms('US')->create(AddressType::class, null, ['with_recipient' => true]);
        $form->submit(['country' => 'US', 'givenName' => 'Ada', 'addressLine1' => '1 Main St', 'locality' => 'Austin', 'administrativeArea' => 'TX']);

        self::assertSame(['This value is required in an address of this country.'], $this->errors($form->get('postalCode')));
        self::assertSame('Ada', $form->getData()?->get(AddressField::GivenName));

        // Germany writes no middle name: one the address held while the form does not show it is dropped.
        $unwritten = $this->forms()->create(AddressType::class, Address::of('DE', ['givenName' => 'Ada', 'additionalName' => 'Augusta', 'addressLine1' => 'Unter den Linden 1', 'locality' => 'Berlin', 'postalCode' => '10117']));
        $unwritten->submit(['country' => 'DE', 'addressLine1' => 'Unter den Linden 1', 'locality' => 'Berlin', 'postalCode' => '10117']);

        self::assertTrue($unwritten->isValid(), (string) $unwritten->getErrors(true));
        self::assertSame('Ada', $unwritten->getData()?->get(AddressField::GivenName));
        self::assertNull($unwritten->getData()->get(AddressField::AdditionalName));
    }

    public function testAViolationOnAHiddenFieldGoesToTheForm(): void
    {
        $form = $this->forms('DE')->create(AddressType::class, Address::of('DE', ['addressLine1' => 'Unter den Linden 1', 'locality' => 'Berlin', 'postalCode' => '10117', 'givenName' => 'Ada']));
        $form->submit(['country' => 'DE', 'addressLine1' => 'Unter den Linden 1', 'locality' => 'Berlin', 'postalCode' => '10117']);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        $withName = $this->forms('DE')->create(AddressType::class);
        $withName->get('addressLine1');
        $withName->submit(['country' => 'DE', 'addressLine1' => 'Unter den Linden 1', 'locality' => 'Berlin', 'postalCode' => '1']);
        self::assertSame(['This postal code is not one of this country or region.'], $this->errors($withName->get('postalCode')));
    }

    public function testChangingTheCountryDropsTheFieldsOfThePreviousOne(): void
    {
        $form = $this->forms()->create(AddressType::class, Address::of('FR', ['addressLine1' => '10 rue de Rivoli', 'locality' => 'Paris', 'postalCode' => '75001', 'sortingCode' => 'CEDEX 01']));
        $form->submit(['country' => 'CA', 'addressLine1' => '1234 rue Saint-Denis', 'locality' => 'Montréal', 'administrativeArea' => 'QC', 'postalCode' => 'H2X 3K8']);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        $address = $form->getData();
        self::assertInstanceOf(Address::class, $address);
        self::assertSame(['addressLine1' => '1234 rue Saint-Denis', 'locality' => 'Montréal', 'administrativeArea' => 'QC', 'postalCode' => 'H2X 3K8'], $address->fields());
    }

    public function testARecipientTheFormDoesNotShowIsKeptOnlyWhereTheCountryWritesIt(): void
    {
        $form = $this->forms()->create(AddressType::class, Address::of('DE', ['givenName' => 'Ada', 'addressLine1' => 'Unter den Linden 1', 'locality' => 'Berlin', 'postalCode' => '10117']));
        $form->submit(['country' => 'DE', 'addressLine1' => 'Unter den Linden 1', 'locality' => 'Berlin', 'postalCode' => '10117']);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('Ada', $form->getData()?->get(AddressField::GivenName));
    }

    public function testAViolationOnADisabledFieldGoesToTheForm(): void
    {
        // A listener of the application's that puts back a hidden field's value after the type dropped it.
        $form = $this->forms('CA', translator: true)->createBuilder(AddressType::class)->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
            $event->setData(['country' => 'CA', 'addressLine1' => '1 rue', 'locality' => 'Montréal', 'administrativeArea' => 'QC', 'postalCode' => 'H2X 3K8', 'sortingCode' => 'CEDEX']);
        }, -10)->getForm();
        $form->submit(['country' => 'CA', 'addressLine1' => '1 rue', 'locality' => 'Montréal', 'administrativeArea' => 'QC', 'postalCode' => 'H2X 3K8']);

        self::assertFalse($form->isValid());
        self::assertSame(['translated: This value is not part of an address of this country.'], $this->errors($form));
        self::assertSame([], $this->errors($form->get('sortingCode')));
    }

    public function testAnAddressIsShownAndOnlyTheOfferedCountriesAreChoices(): void
    {
        $view = $this->forms(formatUrl: '/address-format/__COUNTRY__')->create(AddressType::class, Address::of('JP', ['addressLine1' => '千代田1-1', 'administrativeArea' => 'Tokyo', 'postalCode' => '100-0001']), ['countries' => ['CA', 'JP']])->createView();

        self::assertSame(['JP', '千代田1-1', 'Tokyo'], [$view['country']->vars['value'], $view['addressLine1']->vars['value'], $view['administrativeArea']->vars['value']]);
        $choices = $view['country']->vars['choices'];
        self::assertIsArray($choices);
        $values = [];

        foreach ($choices as $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            $values[] = $choice->value;
        }

        self::assertSame(['CA', 'JP'], $values);
        self::assertSame(['data-controller' => 'phil--address-bundle--address', 'data-phil--address-bundle--address-url-value' => '/address-format/__COUNTRY__?locale=fr'], $view->vars['attr']);
        self::assertSame(['class' => 'address', 'data-controller' => 'phil--address-bundle--address', 'data-phil--address-bundle--address-url-value' => '/f/__COUNTRY__?v=2&locale=fr&recipient=1'], $this->forms(formatUrl: '/f/__COUNTRY__?v=2')->create(AddressType::class, null, ['attr' => ['class' => 'address'], 'with_recipient' => true])->createView()->vars['attr']);
        self::assertSame([], $this->forms()->create(AddressType::class)->createView()->vars['attr']);
    }

    public function testNothingIsNoAddressAndFieldsWithoutCountryAreRefused(): void
    {
        $empty = $this->forms()->create(AddressType::class, null, ['required' => false]);
        $empty->submit(['country' => '']);
        self::assertTrue($empty->isValid());
        self::assertNull($empty->getData());

        $transformer = new AddressTransformer();
        self::assertSame([], $transformer->transform(null));
        self::assertNull($transformer->reverseTransform(null));
        self::assertNull($transformer->reverseTransform(['country' => null, 'locality' => ' ']));

        try {
            $transformer->reverseTransform(['locality' => 'Paris']);
            self::fail('Fields without country were taken.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertSame('An address is given with its country.', $transformationFailedException->getMessage());
        }

        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessageIsOrContains('"Canada" is not the ISO 3166-1 alpha-2 code of a country.');

        $transformer->reverseTransform(['country' => 'Canada']);
    }

    public function testAnUnknownCountryIsAnInvalidField(): void
    {
        $form = $this->forms()->create(AddressType::class);
        $form->submit(['country' => 'ZZ', 'addressLine1' => '1']);

        self::assertFalse($form->isValid());
        self::assertNull($form->getData());
    }

    /**
     * @template T
     *
     * @param FormInterface<T> $form
     *
     * @return list<string>
     */
    private function errors(FormInterface $form): array
    {
        $messages = [];

        foreach ($form->getErrors() as $error) {
            $messages[] = $error->getMessage();
        }

        return $messages;
    }

    private function forms(?string $country = null, ?string $formatUrl = null, bool $translator = false): FormFactoryInterface
    {
        $translate = $translator ? new class implements TranslatorInterface {
            use TranslatorTrait;

            /**
             * @param array<array-key, mixed> $parameters
             */
            #[Override]
            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return 'translated: '.$id;
            }
        } : null;

        return Forms::createFormFactoryBuilder()->addType(new AddressType($country, $formatUrl, $translate))->getFormFactory();
    }
}
