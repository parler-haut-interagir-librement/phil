<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping\Column;
use Phil\CalendarBundle\Application\Attendees;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\CalendarChoiceDraft;
use Phil\CalendarBundle\Application\CalendarChoices;
use Phil\CalendarBundle\Application\EventDraft;
use Phil\CalendarBundle\Application\MemberChoices;
use Phil\CalendarBundle\Application\PersonalCalendar;
use Phil\CalendarBundle\Application\PersonalFeeds;
use Phil\CalendarBundle\Domain\Choice\ChoiceSource;
use Phil\CalendarBundle\Domain\Exception\InvalidChoice;
use Phil\CalendarBundle\Domain\Model\CalendarChoice;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\PersonalFeedToken;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryAttendances;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryCalendarChoices;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryFeedTokens;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Validation;

#[CoversClass(ChoiceSource::class)]
#[CoversClass(InvalidChoice::class)]
#[CoversClass(CalendarChoice::class)]
#[CoversClass(CalendarChoices::class)]
#[CoversClass(CalendarChoiceDraft::class)]
#[CoversClass(MemberChoices::class)]
#[CoversClass(PersonalCalendar::class)]
#[CoversClass(PersonalFeeds::class)]
#[CoversClass(PersonalFeedToken::class)]
final class PersonalChoicesTest extends TestCase
{
    private const string UUID = '0196f2a0-0000-7000-8000-000000000001';

    private InMemoryEvents $events;

    private InMemoryAttendances $attendances;

    private InMemoryCalendarChoices $stored;

    private Calendar $calendar;

    private CalendarChoices $choices;

    private Member $ana;

    protected function setUp(): void
    {
        $this->events = new InMemoryEvents();
        $this->attendances = new InMemoryAttendances();
        $this->stored = new InMemoryCalendarChoices();
        $this->calendar = new Calendar($this->events);
        $this->choices = new CalendarChoices($this->stored, new MockClock('2026-11-12 15:00:00', 'America/Toronto'));
        $this->ana = Member::of('member', 'ana');
    }

    /**
     * @return iterable<string, array{ChoiceSource, string, bool}>
     */
    public static function values(): iterable
    {
        yield 'a host' => [ChoiceSource::Host, 'association:42', true];
        yield 'a host with a colon in its reference' => [ChoiceSource::Host, 'group:a:b', true];
        yield 'a host without reference' => [ChoiceSource::Host, 'association', false];
        yield 'a host with a space' => [ChoiceSource::Host, 'association:4 2', false];
        yield 'a host too long' => [ChoiceSource::Host, str_repeat('a', 64).':'.str_repeat('b', 65), false];
        yield 'a host of 64 and 64' => [ChoiceSource::Host, str_repeat('a', 64).':'.str_repeat('b', 64), true];
        yield 'a host with a line after it' => [ChoiceSource::Host, "association:42\n", false];
        yield 'a tag' => [ChoiceSource::Tag, 'Jeux de rôle', true];
        yield 'a tag of 100 characters' => [ChoiceSource::Tag, str_repeat('é', 100), true];
        yield 'a tag of 101 characters' => [ChoiceSource::Tag, str_repeat('é', 101), false];
        yield 'a blank tag' => [ChoiceSource::Tag, '   ', false];
        yield 'a tag with a control character' => [ChoiceSource::Tag, "jeux\x07", false];
        yield 'a tag with DEL' => [ChoiceSource::Tag, "jeux\x7F", false];
        yield 'a venue' => [ChoiceSource::Venue, self::UUID, true];
        yield 'a venue in capitals' => [ChoiceSource::Venue, strtoupper(self::UUID), false];
        yield 'a host with something before it' => [ChoiceSource::Host, 'a b:c', false];
        yield 'a venue with something before it' => [ChoiceSource::Venue, 'zzz'.self::UUID, false];
        yield 'a venue with a line after it' => [ChoiceSource::Venue, self::UUID."\n", false];
        yield 'an event' => [ChoiceSource::Event, self::UUID, true];
        yield 'an event with more after it' => [ChoiceSource::Event, self::UUID.'0', false];
        yield 'an event with less before it' => [ChoiceSource::Event, substr(self::UUID, 1), false];
    }

    #[DataProvider('values')]
    public function testTheValueOfEachSource(ChoiceSource $source, string $value, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidChoice::class);
            $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a %s a member can follow or hide.', mb_substr($value, 0, 100), $source->value));
        }

        self::assertSame($value, $source->checked($value));
    }

    public function testFollowingHidingAndForgetting(): void
    {
        $choice = $this->choices->follow($this->ana, ChoiceSource::Tag, 'jeux');
        self::assertSame(['member:ana', ChoiceSource::Tag, 'jeux', false], [$choice->member()->toString(), $choice->source(), $choice->value(), $choice->isHidden()]);
        self::assertSame('2026-11-12T20:00:00+00:00', $choice->chosenAt()->format(\DATE_ATOM), 'a moment in UTC');
        self::assertSame(36, \strlen($choice->id));

        // Hiding what is followed changes the same choice.
        $hidden = $this->choices->hide($this->ana, ChoiceSource::Tag, 'jeux');
        self::assertSame($choice, $hidden);
        self::assertTrue($hidden->isHidden());
        self::assertCount(1, $this->stored->choices);
        self::assertSame(2, $this->stored->saves);

        $this->choices->follow($this->ana, ChoiceSource::Host, 'association:42');
        $this->choices->follow(Member::of('member', 'ben'), ChoiceSource::Tag, 'jeux');

        $mine = $this->choices->of($this->ana);
        self::assertSame(['association:42'], $mine->followed(ChoiceSource::Host));
        self::assertSame(['jeux'], $mine->hidden(ChoiceSource::Tag));
        self::assertSame([], $mine->followed(ChoiceSource::Tag));
        self::assertSame([], $mine->hidden(ChoiceSource::Host));
        self::assertFalse($mine->isEmpty());

        $this->choices->forget($this->ana, ChoiceSource::Tag, 'jeux');
        $this->choices->forget($this->ana, ChoiceSource::Tag, 'unknown');
        self::assertSame([], $this->choices->of($this->ana)->hidden(ChoiceSource::Tag));

        $this->choices->forgetMember($this->ana);
        self::assertTrue($this->choices->of($this->ana)->isEmpty());
        self::assertFalse($this->choices->of(Member::of('member', 'ben'))->isEmpty());
        self::assertTrue(new MemberChoices()->isEmpty());
    }

    public function testAChoiceFromADraft(): void
    {
        $draft = new CalendarChoiceDraft();
        $draft->source = 'host';
        $draft->value = 'association:42';
        $draft->hidden = true;

        $choice = $this->choices->apply($this->ana, $draft);
        self::assertSame([ChoiceSource::Host, 'association:42', true], [$choice->source(), $choice->value(), $choice->isHidden()]);

        $draft->source = 'planet';
        $this->expectException(InvalidChoice::class);
        $this->expectExceptionMessageIsOrContains('A choice follows or hides a host, a tag, a venue or an event.');
        $this->choices->apply($this->ana, $draft);
    }

    public function testAnInvalidValueIsNeverKept(): void
    {
        try {
            $this->choices->follow($this->ana, ChoiceSource::Event, 'not-an-id');
            self::fail('An event is named by its identifier.');
        } catch (InvalidChoice) {
            self::assertSame([], $this->stored->choices);
            self::assertSame(0, $this->stored->lookups, 'checked before it is looked up');
        }

        $this->expectException(InvalidChoice::class);
        new CalendarChoice($this->ana, ChoiceSource::Venue, 'nope', false, new DateTimeImmutable());
    }

    public function testAMemberKeepsTwoHundredChoicesAtMost(): void
    {
        for ($i = 1; $i <= CalendarChoices::MAX; ++$i) {
            $this->choices->follow($this->ana, ChoiceSource::Tag, 'tag'.$i);
        }

        // Changing one kept is not one more.
        $this->choices->hide($this->ana, ChoiceSource::Tag, 'tag1');
        self::assertCount(CalendarChoices::MAX, $this->choices->of($this->ana)->choices);

        $this->expectException(InvalidChoice::class);
        $this->expectExceptionMessageIsOrContains('A member follows or hides 200 sources at most.');
        $this->choices->follow($this->ana, ChoiceSource::Tag, 'one more');
    }

    public function testTheDraftIsValidated(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $draft = new CalendarChoiceDraft();
        $paths = static fn (CalendarChoiceDraft $draft): array => array_map(static fn (object $violation): string => $violation->getPropertyPath().' '.$violation->getMessage(), iterator_to_array($validator->validate($draft)));

        self::assertSame(['host', 'tag', 'venue', 'event'], CalendarChoiceDraft::sources());
        self::assertCount(2, $paths($draft));

        $draft->source = 'tag';
        $draft->value = 'jeux';
        self::assertSame([], $paths($draft));

        $draft->source = 'host';
        self::assertSame(['value calendar.choice'], $paths($draft));

        $draft->source = 'planet';
        self::assertSame(['source The value you selected is not a valid choice.'], $paths($draft));

        $draft->source = 'host';
        $draft->value = '  ';
        self::assertSame(['value This value should not be blank.'], $paths($draft));
    }

    public function testWhatAMemberFollowsAndHides(): void
    {
        $venue = new Venue('Grande salle');
        $club = $this->event('Club', 'FREQ=WEEKLY;COUNT=3');
        $club->tag(['jeux']);

        $fair = $this->event('Foire');
        $fair->placeAt($venue);

        $talk = $this->event('Conférence');
        $this->calendar->host($talk, 'association', '42');
        $hidden = $this->event('Masqué');
        $hidden->tag(['jeux']);

        $this->calendar->host($hidden, 'association', '42');
        $alone = $this->event('Seul');
        $attended = $this->event('Assisté');
        $attended->tag(['bruit']);

        $hosted = $this->event('Animé');
        $hosted->tag(['bruit']);

        $this->calendar->host($hosted, 'member', 'ana');

        new Attendees($this->attendances, new MockClock())->attend($attended, $this->ana);
        $personal = new PersonalCalendar($this->calendar, $this->attendances, $this->events, $this->choices);
        $titles = fn (bool $hosted = true): array => array_values(array_unique(array_map(static fn (Occurrence $occurrence): string => $occurrence->event->title(), $personal->occurrences($this->ana, $this->at('2026-11-01 00:00'), $this->at('2026-12-01 00:00'), $hosted))));

        self::assertSame(['Animé', 'Assisté'], $titles());
        self::assertSame(['Assisté'], $titles(false));

        $this->choices->follow($this->ana, ChoiceSource::Tag, 'jeux');
        $this->choices->follow($this->ana, ChoiceSource::Venue, $venue->id);
        $this->choices->follow($this->ana, ChoiceSource::Host, 'association:42');
        $this->choices->follow($this->ana, ChoiceSource::Event, $alone->id);
        self::assertEqualsCanonicalizing(['Animé', 'Assisté', 'Club', 'Conférence', 'Foire', 'Masqué', 'Seul'], $titles());

        // Hiding wins over following, whatever names it; what a member attends or hosts stays.
        $this->choices->hide($this->ana, ChoiceSource::Event, $hidden->id);
        $this->choices->hide($this->ana, ChoiceSource::Tag, 'bruit');
        self::assertEqualsCanonicalizing(['Animé', 'Assisté', 'Club', 'Conférence', 'Foire', 'Seul'], $titles());
        self::assertEqualsCanonicalizing(['Assisté', 'Club', 'Conférence', 'Foire', 'Seul'], $titles(false));

        $this->choices->hide($this->ana, ChoiceSource::Venue, $venue->id);
        $this->choices->hide($this->ana, ChoiceSource::Host, 'association:42');
        self::assertEqualsCanonicalizing(['Animé', 'Assisté', 'Club', 'Seul'], $titles());

        // Three occurrences of the club, in order, once each.
        $club = array_values(array_filter($personal->occurrences($this->ana, $this->at('2026-11-01 00:00'), $this->at('2026-12-01 00:00')), static fn (Occurrence $occurrence): bool => 'Club' === $occurrence->event->title()));
        self::assertSame(['11-10', '11-17', '11-24'], array_map(static fn (Occurrence $occurrence): string => $occurrence->start->format('m-d'), $club));
    }

    public function testAChoiceFollowsOrHidesOnlyWhatItNames(): void
    {
        $mine = new MemberChoices([
            new CalendarChoice($this->ana, ChoiceSource::Tag, 'jeux', false, new DateTimeImmutable()),
            new CalendarChoice($this->ana, ChoiceSource::Tag, 'bruit', true, new DateTimeImmutable()),
        ]);
        $games = $this->event('Jeux');
        $games->tag(['jeux']);

        $noise = $this->event('Bruit');
        $noise->tag(['bruit']);

        $plain = $this->event('Rien');

        self::assertSame([true, false, false], [$mine->follows($games), $mine->follows($noise), $mine->follows($plain)]);
        self::assertSame([false, true, false], [$mine->hides($games), $mine->hides($noise), $mine->hides($plain)]);
        self::assertFalse(new MemberChoices()->follows($plain));
        self::assertFalse(new MemberChoices()->hides($plain));
    }

    public function testAMemberWithNothingReadsNothing(): void
    {
        $this->event('Club');
        $this->events->hostQueries = 0;
        $personal = new PersonalCalendar($this->calendar, $this->attendances, $this->events, $this->choices);

        self::assertSame([], $personal->occurrences($this->ana, $this->at('2026-11-01 00:00'), $this->at('2026-12-01 00:00'), false));
        self::assertSame(0, $this->events->hostQueries, 'no period read');
        self::assertSame([], $personal->occurrences($this->ana, $this->at('2026-11-01 00:00'), $this->at('2026-12-01 00:00')));
        self::assertSame(1, $this->events->hostQueries, 'the hosts of the period in one query');

        // An empty period asks no host.
        $this->events->hostQueries = 0;
        self::assertSame([], $personal->occurrences($this->ana, $this->at('2027-11-01 00:00'), $this->at('2027-12-01 00:00')));
        self::assertSame(0, $this->events->hostQueries);
    }

    public function testTheSecretsOfFeeds(): void
    {
        $tokens = new InMemoryFeedTokens();
        $feeds = new PersonalFeeds($tokens, new MockClock('2026-11-12 15:00:00', 'America/Toronto'));

        self::assertNull($feeds->issuedAt($this->ana));
        $secret = $feeds->issue($this->ana);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $secret);
        self::assertSame('member:ana', $feeds->memberOf($secret)?->toString());
        self::assertSame('2026-11-12T20:00:00+00:00', $feeds->issuedAt($this->ana)?->format(\DATE_ATOM));

        $token = $tokens->ofMember($this->ana);
        self::assertInstanceOf(PersonalFeedToken::class, $token);
        self::assertSame(hash('sha256', $secret), $token->digest, 'only the digest is kept');
        $columns = new ReflectionProperty(PersonalFeedToken::class, 'digest')->getAttributes(Column::class);
        self::assertCount(1, $columns);
        $column = $columns[0]->newInstance();
        self::assertSame([64, true], [$column->length, $column->options['fixed'] ?? false], 'a SHA-256 digest in hexadecimal');
        self::assertSame('member:ana', $token->member()->toString());
        self::assertSame(36, \strlen($token->id));

        // A new secret revokes the one before.
        $again = $feeds->issue($this->ana);
        self::assertNotSame($secret, $again);
        self::assertNull($feeds->memberOf($secret));
        self::assertSame('member:ana', $feeds->memberOf($again)?->toString());

        $lookups = $tokens->lookups;

        foreach (['', 'short', $again.'x', substr($again, 1), strtoupper($again), str_repeat('g', 64), "{$again}\n"] as $malformed) {
            self::assertNull($feeds->memberOf($malformed), $malformed);
        }

        self::assertSame($lookups, $tokens->lookups, 'a malformed secret is never looked up');

        $feeds->revoke($this->ana);
        self::assertNull($feeds->memberOf($again));
        self::assertNull($feeds->issuedAt($this->ana));
    }

    private function event(string $title, ?string $rule = null): Event
    {
        $draft = new EventDraft();
        $draft->title = $title;
        $draft->start = new DateTimeImmutable('2026-11-10 19:00');
        $draft->end = new DateTimeImmutable('2026-11-10 21:00');
        $draft->timezone = 'America/Toronto';
        $draft->rule = null === $rule ? null : RecurrenceRule::parse($rule);

        return $this->calendar->schedule($draft);
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('America/Toronto'));
    }
}
