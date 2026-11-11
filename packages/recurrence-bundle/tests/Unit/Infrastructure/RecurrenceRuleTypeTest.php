<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle\Tests\Unit\Infrastructure;

use Locale;
use Override;
use Phil\Recurrence\Frequency;
use Phil\Recurrence\RecurrenceRule;
use Phil\Recurrence\Weekday;
use Phil\RecurrenceBundle\Infrastructure\Form\RecurrenceRuleTransformer;
use Phil\RecurrenceBundle\Infrastructure\Form\RecurrenceRuleType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

#[CoversClass(RecurrenceRuleType::class)]
#[CoversClass(RecurrenceRuleTransformer::class)]
final class RecurrenceRuleTypeTest extends TestCase
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

    /**
     * @return iterable<string, array{array<string, mixed>, ?string}>
     */
    public static function submissions(): iterable
    {
        yield 'nothing' => [['frequency' => ''], null];
        yield 'weekly on two days' => [['frequency' => 'WEEKLY', 'interval' => '2', 'weekdays' => ['MO', 'WE'], 'end' => 'never', 'month_day' => '5', 'months' => ['6']], 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE'];
        yield 'every weekday' => [['frequency' => 'DAILY', 'weekdays' => ['MO', 'TU', 'WE', 'TH', 'FR'], 'end' => 'count', 'count' => '10'], 'FREQ=DAILY;COUNT=10;BYDAY=MO,TU,WE,TH,FR'];
        yield 'monthly on a day until a date' => [['frequency' => 'MONTHLY', 'month_day' => '-1', 'weekdays' => ['MO'], 'end' => 'until', 'until' => '2026-12-31'], 'FREQ=MONTHLY;UNTIL=20261231;BYMONTHDAY=-1'];
        yield 'monthly on the last Friday' => [['frequency' => 'MONTHLY', 'month_day' => '5', 'month_weekday' => '-1', 'weekday' => 'FR', 'end' => 'never'], 'FREQ=MONTHLY;BYDAY=-1FR'];
        yield 'monthly on a weekday without place' => [['frequency' => 'MONTHLY', 'weekday' => 'FR', 'end' => 'never'], 'FREQ=MONTHLY'];
        yield 'monthly on a place without weekday' => [['frequency' => 'MONTHLY', 'month_weekday' => '2', 'month_day' => '9', 'end' => 'never'], 'FREQ=MONTHLY;BYMONTHDAY=9'];
        yield 'yearly in months' => [['frequency' => 'YEARLY', 'months' => ['12', '6'], 'month_weekday' => '1', 'weekday' => 'SU', 'end' => 'never'], 'FREQ=YEARLY;BYDAY=1SU;BYMONTH=6,12'];
        yield 'daily without interval' => [['frequency' => 'DAILY', 'month_weekday' => '1', 'weekday' => 'SU', 'end' => 'never'], 'FREQ=DAILY'];
    }

    /**
     * @param array<string, mixed> $submitted
     */
    #[DataProvider('submissions')]
    public function testARuleIsSubmitted(array $submitted, ?string $rule): void
    {
        $form = $this->forms()->create(RecurrenceRuleType::class, null, ['required' => false]);
        $form->submit($submitted);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($rule, null === $form->getData() ? null : (string) $form->getData());
    }

    public function testAnImpossibleRuleIsInvalid(): void
    {
        foreach ([['frequency' => 'DAILY', 'interval' => '0', 'end' => 'never'], ['frequency' => 'MONTHLY', 'interval' => '1000000000000000', 'end' => 'never'], ['frequency' => 'DAILY', 'end' => 'count', 'count' => '10000'], ['frequency' => 'DAILY', 'end' => 'count'], ['frequency' => 'DAILY', 'end' => 'until']] as $submitted) {
            $form = $this->forms()->create(RecurrenceRuleType::class);
            $form->submit($submitted);
            self::assertFalse($form->isValid());
        }

        $transformer = new RecurrenceRuleTransformer();

        try {
            $transformer->reverseTransform(['frequency' => Frequency::Daily, 'interval' => 0, 'weekdays' => [], 'month_day' => null, 'month_weekday' => null, 'weekday' => null, 'months' => [], 'end' => 'never', 'count' => null, 'until' => null]);
            self::fail('An interval of zero was taken.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertSame('The interval of a rule is 1 to 9999.', $transformationFailedException->getMessage());
            self::assertNotNull($transformationFailedException->getPrevious());
        }

        foreach (['count' => 'A rule that ends after a number of times has its number.', 'until' => 'A rule that ends on a date has its date.'] as $end => $message) {
            try {
                $transformer->reverseTransform(['frequency' => Frequency::Daily, 'interval' => 1, 'weekdays' => [], 'month_day' => null, 'month_weekday' => null, 'weekday' => null, 'months' => [], 'end' => $end, 'count' => null, 'until' => null]);
                self::fail('An end without its value was taken.');
            } catch (TransformationFailedException $transformationFailedException) {
                self::assertSame($message, $transformationFailedException->getMessage());
            }
        }
    }

    public function testARuleIsShownInItsFields(): void
    {
        $transformer = new RecurrenceRuleTransformer();

        self::assertSame(['frequency' => Frequency::Yearly, 'interval' => 2, 'weekdays' => [Weekday::Monday], 'month_day' => 15, 'month_weekday' => -1, 'weekday' => Weekday::Friday, 'months' => [6], 'end' => 'count', 'count' => 3, 'until' => null], $transformer->transform(RecurrenceRule::parse('FREQ=YEARLY;INTERVAL=2;BYDAY=-1FR,MO;BYMONTHDAY=15,20;BYMONTH=6;COUNT=3')));
        self::assertSame(['frequency' => null, 'interval' => 1, 'weekdays' => [], 'month_day' => null, 'month_weekday' => null, 'weekday' => null, 'months' => [], 'end' => 'never', 'count' => null, 'until' => null], $transformer->transform(null));
        self::assertSame(['until', '2026-12-31', [Weekday::Tuesday]], [$transformer->transform(RecurrenceRule::parse('FREQ=WEEKLY;BYDAY=TU;UNTIL=20261231'))['end'], $transformer->transform(RecurrenceRule::parse('FREQ=WEEKLY;BYDAY=TU;UNTIL=20261231'))['until']?->format('Y-m-d'), $transformer->transform(RecurrenceRule::parse('FREQ=WEEKLY;BYDAY=TU;UNTIL=20261231'))['weekdays']]);
        self::assertNull($transformer->reverseTransform(null));

        $view = $this->forms()->create(RecurrenceRuleType::class, RecurrenceRule::parse('FREQ=WEEKLY;BYDAY=TU,TH;COUNT=4'))->createView();
        self::assertSame(['WEEKLY', ['TU', 'TH'], 'count', '4'], [$view['frequency']->vars['value'], $view['weekdays']->vars['value'], $view['end']->vars['value'], $view['count']->vars['value']]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function richerRules(): iterable
    {
        yield 'Friday the 13th' => ['FREQ=MONTHLY;BYDAY=FR;BYMONTHDAY=13'];
        yield 'the last weekday' => ['FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1'];
        yield 'several month days' => ['FREQ=MONTHLY;BYMONTHDAY=1,15'];
        yield 'a week start' => ['FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,SU;WKST=SU'];
        yield 'an end time' => ['FREQ=WEEKLY;BYDAY=TU;UNTIL=20261231T120000Z'];
    }

    #[DataProvider('richerRules')]
    public function testARicherRuleSubmittedUntouchedIsKept(string $rule): void
    {
        $given = RecurrenceRule::parse($rule);
        $form = $this->forms()->create(RecurrenceRuleType::class, $given);
        $view = $form->createView();
        $submitted = [];

        foreach ($view->children as $name => $child) {
            $submitted[$name] = $child->vars['value'];
        }

        $form->submit($submitted);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($given, $form->getData());
    }

    public function testARicherRuleWhoseFieldsChangeIsTheRuleTheySay(): void
    {
        $form = $this->forms()->create(RecurrenceRuleType::class, RecurrenceRule::parse('FREQ=MONTHLY;BYDAY=FR;BYMONTHDAY=13'));
        $form->submit(['frequency' => 'MONTHLY', 'interval' => '2', 'month_day' => '13', 'end' => 'never']);

        self::assertSame('FREQ=MONTHLY;INTERVAL=2;BYMONTHDAY=13', (string) $form->getData());

        $transformer = new RecurrenceRuleTransformer();
        $transformer->transform(null);
        self::assertSame('FREQ=DAILY', (string) $transformer->reverseTransform(['frequency' => Frequency::Daily, 'interval' => 1, 'weekdays' => [], 'month_day' => null, 'month_weekday' => null, 'weekday' => null, 'months' => [], 'end' => 'never', 'count' => null, 'until' => null]));
    }

    public function testTheFieldsSayForWhatTheyShow(): void
    {
        $form = $this->forms()->create(RecurrenceRuleType::class);
        $config = static fn (string $field, string $option): mixed => $form->get($field)->getConfig()->getOption($option);
        $monthDay = $config('month_day', 'row_attr');
        $label = $config('frequency', 'choice_label');
        self::assertIsArray($monthDay);
        self::assertIsCallable($label);

        self::assertSame(['data-controller' => 'phil--recurrence-bundle--recurrence'], $form->getConfig()->getOption('attr'));
        self::assertSame(['phil_recurrence', 'This recurrence is not valid.'], [$form->getConfig()->getOption('translation_domain'), $form->getConfig()->getOption('invalid_message')]);
        self::assertSame(['data-phil--recurrence-bundle--recurrence-target' => 'frequency', 'data-action' => 'phil--recurrence-bundle--recurrence#show'], $config('frequency', 'attr'));
        self::assertSame(['data-phil--recurrence-bundle--recurrence-target' => 'end', 'data-action' => 'phil--recurrence-bundle--recurrence#show'], $config('end', 'attr'));
        self::assertSame(['data-phil--recurrence-bundle--recurrence-target' => 'row', 'data-show' => 'DAILY WEEKLY'], $config('weekdays', 'row_attr'));
        self::assertSame(['data-phil--recurrence-bundle--recurrence-target' => 'row', 'data-show' => 'YEARLY'], $config('months', 'row_attr'));
        self::assertSame(['data-phil--recurrence-bundle--recurrence-target' => 'row', 'data-show' => 'count'], $config('count', 'row_attr'));
        self::assertSame(['data-phil--recurrence-bundle--recurrence-target' => 'row', 'data-show' => 'until'], $config('until', 'row_attr'));
        self::assertSame('MONTHLY YEARLY', $monthDay['data-show'] ?? null);
        self::assertSame(['recurrence.field.frequency', '', 'recurrence.choice.weekly'], [$config('frequency', 'label'), $config('frequency', 'placeholder'), $label(Frequency::Weekly)]);
        self::assertSame(['min' => -31, 'max' => 31], $config('month_day', 'attr'));
        self::assertSame(['min' => 1, 'max' => 9999], $config('interval', 'attr'));
        self::assertSame(['min' => 1, 'max' => 9999], $config('count', 'attr'));
        self::assertSame([false, false, false], [$form->get('interval')->isRequired(), $form->get('until')->isRequired(), $form->get('months')->isRequired()]);
        self::assertTrue($form->get('frequency')->isRequired());
        self::assertSame(['recurrence.choice.never' => 'never', 'recurrence.choice.count' => 'count', 'recurrence.choice.until' => 'until'], $config('end', 'choices'));
        self::assertSame(
            ['recurrence.field.interval', 'recurrence.field.weekdays', 'recurrence.field.month_day', 'recurrence.field.month_weekday', 'recurrence.field.weekday', 'recurrence.field.months', 'recurrence.field.end', 'recurrence.field.count', 'recurrence.field.until'],
            array_map(static fn (string $field): mixed => $config($field, 'label'), ['interval', 'weekdays', 'month_day', 'month_weekday', 'weekday', 'months', 'end', 'count', 'until']),
        );
        self::assertSame([false, false, false], [$config('weekdays', 'choice_translation_domain'), $config('weekday', 'choice_translation_domain'), $config('months', 'choice_translation_domain')]);
        self::assertSame([1, 2, 3, 4, 5, -1], array_values((array) $config('month_weekday', 'choices')));
    }

    public function testDaysMonthsAndPlacesAreNamedInTheLanguage(): void
    {
        self::assertSame(['mardi', 'décembre'], [RecurrenceRuleType::weekday(Weekday::Tuesday), RecurrenceRuleType::month(12)]);

        $view = $this->forms()->create(RecurrenceRuleType::class)->createView();
        self::assertSame('lundi', $view['weekdays']->children[0]->vars['label']);
        self::assertSame(['janvier'], \array_slice($this->labels($view['months']->vars['choices']), 0, 1));
        self::assertSame(['recurrence.position.1', 'recurrence.position.2', 'recurrence.position.3', 'recurrence.position.4', 'recurrence.position.5', 'recurrence.position.-1'], $this->labels($view['month_weekday']->vars['choices']));
    }

    /**
     * @return list<string>
     */
    private function labels(mixed $choices): array
    {
        self::assertIsArray($choices);
        $labels = [];

        foreach ($choices as $choice) {
            self::assertInstanceOf(ChoiceView::class, $choice);
            self::assertIsString($choice->label);
            $labels[] = $choice->label;
        }

        return $labels;
    }

    private function forms(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->getFormFactory();
    }
}
