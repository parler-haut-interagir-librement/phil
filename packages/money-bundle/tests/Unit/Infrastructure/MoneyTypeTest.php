<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Unit\Infrastructure;

use Locale;
use Override;
use Phil\Money\Currency;
use Phil\Money\Exception\InvalidAmount;
use Phil\Money\Exception\UnknownCurrency;
use Phil\Money\Money;
use Phil\MoneyBundle\Application\AcceptedCurrencies;
use Phil\MoneyBundle\Infrastructure\Form\MoneyTransformer;
use Phil\MoneyBundle\Infrastructure\Form\MoneyType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

#[CoversClass(MoneyType::class)]
#[CoversClass(MoneyTransformer::class)]
final class MoneyTypeTest extends TestCase
{
    private string $locale;

    #[Override]
    protected function setUp(): void
    {
        $this->locale = Locale::getDefault();
        Locale::setDefault('fr_CA');
    }

    #[Override]
    protected function tearDown(): void
    {
        Locale::setDefault($this->locale);
    }

    public function testTheAcceptedCurrenciesAreOfferedByName(): void
    {
        $form = $this->form(AcceptedCurrencies::of(['USD', 'CAD'], 'CAD'));
        $view = $form->createView();

        self::assertSame(['USD' => 'USD', 'CAD' => 'CAD'], $form->get('currency')->getConfig()->getOption('choices'));
        $choices = $view['currency']->vars['choices'];
        self::assertIsArray($choices);
        self::assertSame(['dollar des États-Unis', 'dollar canadien'], array_map(static fn (mixed $choice): mixed => $choice instanceof ChoiceView ? $choice->label : null, array_values($choices)));
        self::assertFalse($form->get('currency')->getConfig()->getOption('label'));
        self::assertFalse($form->get('currency')->getConfig()->getOption('choice_translation_domain'));
        self::assertSame('CAD', $view['currency']->vars['value']);
        self::assertSame('', $view['amount']->vars['value']);
        self::assertSame(['inputmode' => 'decimal'], $view['amount']->vars['attr']);
        self::assertFalse($form->get('amount')->getConfig()->getOption('label'));
        self::assertTrue($form->get('amount')->getConfig()->getOption('required'));
        self::assertIsArray($view->vars['block_prefixes']);
        self::assertSame('phil_money', $view->vars['block_prefixes'][1]);
        self::assertSame('This value is not a valid money amount.', $form->getConfig()->getOption('invalid_message'));
        self::assertFalse($form->getConfig()->getOption('error_bubbling'));
        self::assertNull($form->getConfig()->getDataClass());
    }

    public function testAnEmptyFieldStartsOnTheFirstOfferedCurrencyWhenTheDefaultIsNotOffered(): void
    {
        $view = $this->form(AcceptedCurrencies::of(['CAD', 'USD', 'EUR']), ['currencies' => ['USD', 'EUR']])->createView();

        self::assertSame('USD', $view['currency']->vars['value']);
    }

    public function testAnOptionalFieldHasAnOptionalAmount(): void
    {
        self::assertFalse($this->form(AcceptedCurrencies::of(['CAD', 'USD']), ['required' => false])->get('amount')->getConfig()->getOption('required'));
    }

    public function testAnOpenListOffersEveryCurrency(): void
    {
        $form = $this->form(AcceptedCurrencies::of([]));

        $choices = $form->get('currency')->getConfig()->getOption('choices');
        self::assertIsArray($choices);
        self::assertContains('EUR', $choices);
    }

    public function testAMoneyIsShownInTheLocale(): void
    {
        $view = $this->form(AcceptedCurrencies::of(['CAD', 'USD']), [], Money::of(-123_450, Currency::of('USD')))->createView();

        self::assertSame('-1234,50', $view['amount']->vars['value']);
        self::assertNull($view->vars['data_class'] ?? null);
        self::assertSame('USD', $view['currency']->vars['value']);
    }

    public function testASubmissionIsReadInTheLocale(): void
    {
        $form = $this->form(AcceptedCurrencies::of(['CAD', 'USD']));
        $form->submit(['amount' => '1 234,5', 'currency' => 'USD']);

        self::assertTrue($form->isSynchronized());
        self::assertEquals(Money::of(123_450, Currency::of('USD')), $form->getData());
    }

    public function testAnEmptyAmountIsNoMoney(): void
    {
        $form = $this->form(AcceptedCurrencies::of(['CAD', 'USD']), [], Money::of(100, Currency::of('CAD')));
        $form->submit(['amount' => '  ', 'currency' => 'USD']);

        self::assertTrue($form->isSynchronized());
        self::assertNull($form->getData());
    }

    public function testOneCurrencyIsFixed(): void
    {
        $form = $this->form(AcceptedCurrencies::of(['CAD', 'USD']), ['currency' => 'USD']);

        self::assertFalse($form->has('currency'));
        self::assertSame(['USD'], $form->getConfig()->getOption('currencies'));

        $form->submit(['amount' => '12']);
        self::assertEquals(Money::of(1_200, Currency::of('USD')), $form->getData());
    }

    public function testAFixedCurrencyWinsOverTheCurrencyOfTheData(): void
    {
        $form = $this->form(AcceptedCurrencies::of(['CAD', 'USD']), ['currency' => 'CAD'], Money::of(1_000, Currency::of('USD')));
        $form->submit(['amount' => '12.50']);

        self::assertTrue($form->isSynchronized());
        self::assertEquals(Money::of(1_250, Currency::of('CAD')), $form->getData());
    }

    public function testASingleAcceptedCurrencyIsFixed(): void
    {
        $form = $this->form(AcceptedCurrencies::of(['JPY']), [], Money::of(1_200, Currency::of('JPY')));

        self::assertFalse($form->has('currency'));
        self::assertSame('1200', $form->createView()['amount']->vars['value']);
    }

    public function testOfferedCurrenciesAreCheckedAndDeduplicated(): void
    {
        self::assertSame(['USD', 'EUR'], $this->form(AcceptedCurrencies::of(['CAD']), ['currencies' => ['USD', 'USD', 'EUR']])->getConfig()->getOption('currencies'));
    }

    public function testNoOfferedCurrencyIsRefused(): void
    {
        $this->expectExceptionObject(new InvalidOptionsException('The "currencies" of a money field offer one currency or more.'));

        $this->form(AcceptedCurrencies::of(['CAD']), ['currencies' => []]);
    }

    public function testAnUnknownOfferedCurrencyIsRefused(): void
    {
        $this->expectException(UnknownCurrency::class);

        $this->form(AcceptedCurrencies::of(['CAD']), ['currencies' => ['XYZ', 'CAD']]);
    }

    public function testAnUnknownFixedCurrencyIsRefused(): void
    {
        $this->expectException(UnknownCurrency::class);

        $this->form(AcceptedCurrencies::of(['CAD']), ['currency' => 'XYZ']);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function unreadable(): iterable
    {
        yield 'not a number' => [['amount' => 'douze', 'currency' => 'CAD']];
        yield 'too precise' => [['amount' => '12,345', 'currency' => 'CAD']];
        yield 'no currency' => [['amount' => '12', 'currency' => '']];
    }

    /**
     * @param array<string, string> $submission
     */
    #[DataProvider('unreadable')]
    public function testAnUnreadableSubmissionFailsTheForm(array $submission): void
    {
        $form = $this->form(AcceptedCurrencies::of(['CAD', 'USD']));
        $form->submit($submission);

        self::assertFalse($form->isSynchronized());
        self::assertInstanceOf(TransformationFailedException::class, $form->getTransformationFailure());
        self::assertNull($form->getData());
    }

    public function testACurrencyNotOfferedFailsItsField(): void
    {
        $form = $this->form(AcceptedCurrencies::of(['CAD', 'USD']));
        $form->submit(['amount' => '12', 'currency' => 'EUR']);

        self::assertFalse($form->get('currency')->isSynchronized());
    }

    public function testTheFailureSaysWhatWasNotRead(): void
    {
        $form = $this->form(AcceptedCurrencies::of(['CAD', 'USD']));
        $form->submit(['amount' => 'douze', 'currency' => 'CAD']);
        self::assertSame('"douze" is not an amount in a currency.', $form->getTransformationFailure()?->getPrevious()?->getMessage());

        $form = $this->form(AcceptedCurrencies::of(['CAD', 'USD']));
        $form->submit(['amount' => '12,345', 'currency' => 'CAD']);

        $failure = $form->getTransformationFailure()?->getPrevious();
        self::assertInstanceOf(TransformationFailedException::class, $failure);
        self::assertSame('"12.345" has more decimals than CAD counts (2).', $failure->getMessage());
        self::assertNull($failure->getInvalidMessage());
        self::assertInstanceOf(InvalidAmount::class, $failure->getPrevious());
    }

    public function testAFormWithMoneyHasNoDataClass(): void
    {
        self::assertNull($this->form(AcceptedCurrencies::of(['CAD', 'USD']), [], Money::of(1, Currency::of('CAD')))->getConfig()->getDataClass());
    }

    public function testOnlyMoneyIsMapped(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->form(AcceptedCurrencies::of(['CAD', 'USD']), [], new stdClass());
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return FormInterface<mixed>
     */
    private function form(AcceptedCurrencies $accepted, array $options = [], mixed $data = null): FormInterface
    {
        return $this->factory($accepted)->create(MoneyType::class, $data, $options);
    }

    private function factory(AcceptedCurrencies $accepted): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addType(new MoneyType($accepted))->getFormFactory();
    }
}
