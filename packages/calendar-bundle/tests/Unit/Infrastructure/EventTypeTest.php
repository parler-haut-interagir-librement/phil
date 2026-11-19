<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Infrastructure;

use Phil\CalendarBundle\Application\EventDraft;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Infrastructure\Form\EventType;
use Phil\CalendarBundle\Infrastructure\Form\TagsTransformer;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryVenues;
use Phil\RecurrenceBundle\Infrastructure\Form\RecurrenceRuleType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

#[CoversClass(EventType::class)]
#[CoversClass(TagsTransformer::class)]
final class EventTypeTest extends TestCase
{
    public function testAnEventIsSubmittedToADraft(): void
    {
        $form = $this->forms()->create(EventType::class);
        $form->submit([
            'title' => 'Soirée',
            'start' => '2026-11-10T19:00',
            'end' => '2026-11-10T22:00',
            'allDay' => '1',
            'timezone' => 'Europe/Paris',
            'rule' => ['frequency' => 'WEEKLY', 'interval' => '1', 'end' => 'count', 'count' => '3'],
            'location' => 'Café X',
            'url' => 'example.com/jeux',
            'description' => 'Bienvenue.',
            'tags' => ' jeux, , soirée ',
        ]);

        $draft = $form->getData();
        self::assertInstanceOf(EventDraft::class, $draft);
        self::assertSame(['Soirée', '2026-11-10 19:00', '2026-11-10 22:00', true, 'Europe/Paris', 'FREQ=WEEKLY;COUNT=3', 'Café X', 'https://example.com/jeux', 'Bienvenue.', ['jeux', 'soirée']], [$draft->title, $draft->start?->format('Y-m-d H:i'), $draft->end?->format('Y-m-d H:i'), $draft->allDay, $draft->timezone, (string) $draft->rule, $draft->location, $draft->url, $draft->description, $draft->tags]);
    }

    public function testANewEventIsInTheDefaultTimeZone(): void
    {
        $form = $this->forms('America/Toronto')->create(EventType::class);
        $form->submit(['title' => 'Soirée', 'start' => '2026-11-10T19:00', 'end' => '2026-11-10T22:00']);

        $draft = $form->getData();

        self::assertInstanceOf(EventDraft::class, $draft);
        self::assertSame(['America/Toronto', [], null, false], [$draft->timezone, $draft->tags, $draft->rule, $draft->allDay]);
        self::assertSame('America/Toronto', $this->forms('America/Toronto')->create(EventType::class)->createView()['timezone']->vars['value']);
        $draft = new EventDraft();
        $draft->timezone = 'Europe/Paris';
        self::assertSame('Europe/Paris', $this->forms('America/Toronto')->create(EventType::class, $draft)->createView()['timezone']->vars['value']);
        self::assertSame('UTC', $this->emptyTimezone($this->forms()));
    }

    public function testADraftIsShownAndTheFieldsAreLabelled(): void
    {
        $draft = new EventDraft();
        $draft->tags = ['jeux', 'soirée'];

        $form = $this->forms()->create(EventType::class, $draft);
        $view = $form->createView();

        self::assertSame('jeux, soirée', $view['tags']->vars['value']);
        self::assertSame(['phil_calendar', EventDraft::class], [$form->getConfig()->getOption('translation_domain'), $form->getConfig()->getOption('data_class')]);
        self::assertSame(
            ['calendar.field.title', 'calendar.field.start', 'calendar.field.end', 'calendar.field.all_day', 'calendar.field.timezone', 'calendar.field.rule', 'calendar.field.location', 'calendar.field.url', 'calendar.field.description', 'calendar.field.tags'],
            array_map(static fn (string $field): mixed => $form->get($field)->getConfig()->getOption('label'), ['title', 'start', 'end', 'allDay', 'timezone', 'rule', 'location', 'url', 'description', 'tags']),
        );
        self::assertSame([true, true, true, false, false, false, false, false, false], array_map(static fn (string $field): bool => $form->get($field)->isRequired(), ['title', 'start', 'end', 'allDay', 'rule', 'location', 'url', 'description', 'tags']));
        self::assertSame([['maxlength' => 200], ['maxlength' => 255], 'https', 'calendar.help.tags', RecurrenceRuleType::class], [$form->get('title')->getConfig()->getOption('attr'), $form->get('location')->getConfig()->getOption('attr'), $form->get('url')->getConfig()->getOption('default_protocol'), $form->get('tags')->getConfig()->getOption('help'), $form->get('rule')->getConfig()->getType()->getInnerType()::class]);
        self::assertSame(['calendar.field.image', false, ['maxlength' => 2048], 'calendar.help.image'], [$form->get('image')->getConfig()->getOption('label'), $form->get('image')->isRequired(), $form->get('image')->getConfig()->getOption('attr'), $form->get('image')->getConfig()->getOption('help')]);
        self::assertSame(['single_text', 'datetime_immutable', 'UTC', 'UTC'], [$form->get('start')->getConfig()->getOption('widget'), $form->get('end')->getConfig()->getOption('input'), $form->get('start')->getConfig()->getOption('view_timezone'), $form->get('end')->getConfig()->getOption('model_timezone')]);
    }

    /**
     * With venues, the event may have one or none, each shown by its name as written, never as a key to translate.
     */
    public function testAVenueIsChosenOrLeftOut(): void
    {
        $venues = new InMemoryVenues();
        $venue = new Venue('calendar.field.title');
        $venues->save($venue);
        $forms = Forms::createFormFactoryBuilder()->addType(new EventType('UTC', $venues))->getFormFactory();

        $form = $forms->create(EventType::class);
        $view = $form->createView();
        self::assertFalse($form->get('venue')->isRequired());
        self::assertSame([false, false, 'calendar.help.no_venue'], [$view['venue']->vars['required'], $view['venue']->vars['choice_translation_domain'], $view['venue']->vars['placeholder']]);

        $form->submit(['title' => 'Soirée', 'start' => '2026-11-10T19:00', 'end' => '2026-11-10T22:00', 'venue' => $venue->id]);
        self::assertInstanceOf(EventDraft::class, $form->getData());
        self::assertSame($venue, $form->getData()->venue);
    }

    public function testTheCapacityAndTheVenueAreOptionalAndLabelled(): void
    {
        $venues = new InMemoryVenues();
        $form = Forms::createFormFactoryBuilder()->addType(new EventType('UTC', $venues))->getFormFactory()->create(EventType::class);
        $capacity = $form->get('capacity')->getConfig();

        self::assertSame(['calendar.field.capacity', false, 'calendar.help.capacity', ['min' => 1]], [$capacity->getOption('label'), $capacity->getOption('required'), $capacity->getOption('help'), $capacity->getOption('attr')]);
        self::assertSame('calendar.field.venue', $form->get('venue')->getConfig()->getOption('label'));
    }

    public function testTagsAreTextSeparatedByCommas(): void
    {
        $transformer = new TagsTransformer();

        self::assertSame(['', [], 'a, b', ['a', 'b']], [$transformer->transform(null), $transformer->reverseTransform(null), $transformer->transform(['a', 'b']), $transformer->reverseTransform('a,b')]);
        self::assertSame(['larp', 'jeux'], $transformer->reverseTransform(' larp ,, jeux , larp '));
    }

    private function emptyTimezone(FormFactoryInterface $forms): ?string
    {
        $form = $forms->create(EventType::class);
        $form->submit(['title' => 'x', 'start' => '2026-11-10T19:00', 'end' => '2026-11-10T22:00', 'timezone' => '']);

        $draft = $form->getData();
        self::assertInstanceOf(EventDraft::class, $draft);

        return $draft->timezone;
    }

    private function forms(string $timezone = 'UTC'): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addType(new EventType($timezone))->getFormFactory();
    }
}
