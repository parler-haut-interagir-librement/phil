<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Tests\Unit\Infrastructure;

use Phil\OpeningHours\DayOfWeek;
use Phil\OpeningHours\HoursText;
use Phil\OpeningHours\OpeningHours;
use Phil\OpeningHoursBundle\Infrastructure\Form\DayHoursTransformer;
use Phil\OpeningHoursBundle\Infrastructure\Form\OpeningHoursTransformer;
use Phil\OpeningHoursBundle\Infrastructure\Form\OpeningHoursType;
use Phil\OpeningHoursBundle\Infrastructure\Form\SpecialHoursTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

#[CoversClass(OpeningHoursType::class)]
#[CoversClass(OpeningHoursTransformer::class)]
#[CoversClass(DayHoursTransformer::class)]
#[CoversClass(SpecialHoursTransformer::class)]
final class OpeningHoursTypeTest extends TestCase
{
    public function testHoursAreSubmittedADayAField(): void
    {
        $form = $this->forms()->create(OpeningHoursType::class);
        $form->submit(['monday' => '9h-17h', 'tuesday' => '', 'saturday' => '10:00-12:00, 13:00-16:00', 'specials' => '2026-12-25 # Christmas', 'timezone' => 'America/Toronto']);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        $hours = $form->getData();
        self::assertInstanceOf(OpeningHours::class, $hours);
        self::assertSame([['09:00-17:00', [1]], ['', [2, 3, 4, 5]], ['10:00-12:00, 13:00-16:00', [6]], ['', [7]]], array_map(static fn (array $group): array => [$group['hours']->toText(), array_map(static fn (DayOfWeek $day): int => $day->value, $group['days'])], $hours->groups()));
        self::assertSame(['America/Toronto', 'Christmas'], [$hours->timezone, $hours->specials[0]->label]);
    }

    public function testDaysAreNamedInTheLocaleAndTheTimeZoneDefaultsToTheBundles(): void
    {
        $form = $this->forms('America/Toronto')->create(OpeningHoursType::class, null, ['locale' => 'fr_CA']);
        $view = $form->createView();

        self::assertSame(['Lundi', 'Dimanche', false], [$view['monday']->vars['label'], $view['sunday']->vars['label'], $view['monday']->vars['translation_domain']]);
        self::assertSame(['America/Toronto', ['placeholder' => '09:00-17:00'], false, false], [$view['timezone']->vars['value'], $view['monday']->vars['attr'], $view['monday']->vars['required'], $view['specials']->vars['required']]);
        self::assertSame(['opening_hours.specials', 'opening_hours.specials_help', 'opening_hours.timezone', 'phil_opening_hours'], [$view['specials']->vars['label'], $view['specials']->vars['help'], $view['timezone']->vars['label'], $view['specials']->vars['translation_domain']]);
        self::assertSame('Monday', $this->forms()->create(OpeningHoursType::class, null, ['locale' => 'en'])->createView()['monday']->vars['label']);
        self::assertSame('Úterý', $this->forms()->create(OpeningHoursType::class, null, ['locale' => 'cs'])->createView()['tuesday']->vars['label']);
    }

    public function testTheFormsTimeZoneWinsAndMayBeHidden(): void
    {
        $form = $this->forms('America/Toronto')->create(OpeningHoursType::class, null, ['timezone' => 'Europe/Paris', 'with_timezone' => false]);
        $form->submit(['monday' => '09:00-17:00']);

        self::assertFalse($form->has('timezone'));
        self::assertSame('Europe/Paris', $form->getData()?->timezone);
        self::assertSame('America/Toronto', $this->forms('America/Toronto')->create(OpeningHoursType::class)->getConfig()->getOption('timezone') ?? 'America/Toronto');
    }

    public function testHoursAreShownInTheirFields(): void
    {
        $hours = OpeningHours::of([1 => HoursText::day('09:00-17:00')], HoursText::specials('2026-12-25 # Christmas'), 'Europe/Paris');
        $view = $this->forms()->create(OpeningHoursType::class, $hours)->createView();

        self::assertSame(['09:00-17:00', '', '2026-12-25 # Christmas', 'Europe/Paris'], [$view['monday']->vars['value'], $view['tuesday']->vars['value'], $view['specials']->vars['value'], $view['timezone']->vars['value']]);
    }

    public function testNoHoursAreNone(): void
    {
        $form = $this->forms()->create(OpeningHoursType::class);
        $form->submit(['monday' => '', 'specials' => '']);

        self::assertTrue($form->isValid());
        self::assertNull($form->getData());

        $holiday = $this->forms()->create(OpeningHoursType::class);
        $holiday->submit(['specials' => '2026-12-25']);
        self::assertTrue($holiday->getData()?->isAlwaysClosed());
    }

    public function testUnreadableHoursMakeTheirFieldInvalid(): void
    {
        $form = $this->forms()->create(OpeningHoursType::class);
        $form->submit(['monday' => 'all day', 'specials' => 'Christmas']);

        self::assertFalse($form->isValid());
        self::assertSame('These hours cannot be read: type ranges such as 09:00-12:00, 13:00-17:00.', $form->get('monday')->getErrors()->current()->getMessage());
        self::assertSame('These special hours cannot be read: type one period a line, such as 2026-12-25 # Christmas.', $form->get('specials')->getErrors()->current()->getMessage());
    }

    public function testTheTransformers(): void
    {
        $day = new DayHoursTransformer();
        $specials = new SpecialHoursTransformer();
        $hours = new OpeningHoursTransformer('Europe/Paris');

        self::assertSame(['', 'Europe/Paris'], [$day->transform(null), $hours->transform(null)['timezone']]);
        self::assertTrue($day->reverseTransform(null)->isClosed());
        self::assertSame(['', []], [$specials->transform(null), $specials->reverseTransform(null)]);
        self::assertSame('Europe/Paris', $hours->reverseTransform(['monday' => HoursText::day('09:00-10:00'), 'specials' => null, 'timezone' => null])?->timezone);

        try {
            $specials->reverseTransform('Christmas');
            self::fail('A bad line was read.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertSame('"Christmas" is not a line of special hours.', $transformationFailedException->getMessage());
            self::assertNotNull($transformationFailedException->getPrevious());
        }

        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessageIsOrContains('"all day" is not a range of hours.');

        $day->reverseTransform('all day');
    }

    private function forms(string $timezone = 'UTC'): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addType(new OpeningHoursType($timezone))->getFormFactory();
    }
}
