<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping\JoinColumn;
use Phil\CalendarBundle\Domain\Exception\InvalidEvent;
use Phil\CalendarBundle\Domain\Model\Attendance;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\EventTranslation;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Domain\Model\VenueTranslation;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\LocaleDimensions\Locale;
use Phil\Recurrence\RecurrenceRule;
use Phil\Url\Exception\InvalidUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute;
use ReflectionProperty;

#[CoversClass(Event::class)]
#[CoversClass(Member::class)]
#[CoversClass(Attendance::class)]
#[CoversClass(EventTranslation::class)]
#[CoversClass(VenueTranslation::class)]
#[CoversClass(Venue::class)]
#[CoversClass(InvalidEvent::class)]
final class EventFeaturesTest extends TestCase
{
    public function testAnOccurrenceHasAPageOfItsOwn(): void
    {
        $event = $this->weekly();
        $event->describe(null, null, 'https://example.com/club');

        $second = $this->at('2026-11-17 19:00');

        $event->linkDate($second, ' HTTPS://Billets.example/17 ');
        $event->linkDate($this->at('2026-11-10 19:00'), 'https://billets.example/10');

        self::assertSame(['2026-11-10 19:00' => 'https://billets.example/10', '2026-11-17 19:00' => 'https://billets.example/17'], $event->dateLinks());
        self::assertSame('https://billets.example/17', $event->urlOn($second->setTimezone(new DateTimeZone('UTC'))));
        self::assertSame('https://example.com/club', $event->urlOn($this->at('2026-11-24 19:00')));

        $event->linkDate($second, '  ');
        self::assertSame(['2026-11-10 19:00' => 'https://billets.example/10'], $event->dateLinks());
        $event->linkDate($second, null);
        self::assertCount(1, $event->dateLinks());

        // The same rule keeps them; another rule takes them away with the occurrences.
        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;COUNT=3'));
        self::assertCount(1, $event->dateLinks());
        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;COUNT=4'));
        self::assertSame([], $event->dateLinks());

        try {
            $event->linkDate($second, 'http://10.0.0.1/billets');
            self::fail('An address on an IP is refused.');
        } catch (InvalidEvent $invalidEvent) {
            self::assertSame('The page of an occurrence is an http or https address.', $invalidEvent->getMessage());
            self::assertInstanceOf(InvalidUrl::class, $invalidEvent->getPrevious());
        }

        $this->expectExceptionMessageIsOrContains('The page of an event is 2048 characters or fewer.');
        $event->linkDate($second, 'https://example.com/'.str_repeat('a', 2029));
    }

    public function testThePagesOfTheirOwnAreBounded(): void
    {
        $event = Event::schedule('Tous les jours', $this->at('2026-01-01 10:00'), $this->at('2026-01-01 11:00'));
        $event->repeat(RecurrenceRule::parse('FREQ=DAILY'));

        for ($day = 0; $day < Event::DATE_LINKS; ++$day) {
            $event->linkDate($this->at('2026-01-01 10:00')->modify(\sprintf('+%d days', $day)), 'https://example.com/'.$day);
        }

        // The last one changed again is no new one.
        $event->linkDate($this->at('2026-01-01 10:00'), 'https://example.com/again');
        self::assertCount(500, $event->dateLinks());

        $this->expectExceptionMessageIsOrContains('An event gives its own page to 500 occurrences at most.');
        $event->linkDate($this->at('2027-12-01 10:00'), 'https://example.com/more');
    }

    public function testTheCapacity(): void
    {
        $event = $this->weekly();
        self::assertNull($event->capacity());

        foreach ([1, 1_000_000, null] as $capacity) {
            $event->limitTo($capacity);
            self::assertSame($capacity, $event->capacity());
        }

        foreach ([0, -1, 1_000_001] as $capacity) {
            try {
                $event->limitTo($capacity);
                self::fail(\sprintf('%d is refused.', $capacity));
            } catch (InvalidEvent $invalid) {
                self::assertSame('The capacity of an event is 1 to 1000000 people, or none.', $invalid->getMessage());
            }
        }
    }

    public function testAProposal(): void
    {
        $event = $this->weekly();
        self::assertTrue($event->isPublished());
        self::assertSame(Event::PUBLISHED, $event->getProposalState());
        self::assertNull($event->proposedBy());

        $event->proposeBy(Member::of('member', 'ana'));
        self::assertFalse($event->isPublished());
        self::assertSame(Event::PROPOSED, $event->getProposalState());
        self::assertTrue(Member::of('member', 'ana')->equals($event->proposedBy() ?? Member::of('x', 'y')));

        $event->setProposalState(Event::REFUSED);
        self::assertSame(Event::REFUSED, $event->getProposalState());
        $event->setProposalState(Event::PUBLISHED);
        self::assertTrue($event->isPublished());

        try {
            $event->setProposalState('draft');
            self::fail('No such state.');
        } catch (InvalidEvent $invalidEvent) {
            self::assertSame('An event is published, proposed or refused, not "draft".', $invalidEvent->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The event is already a proposal.');
        $event->proposeBy(Member::of('member', 'ben'));
    }

    public function testAMember(): void
    {
        $member = Member::of('member', 'a.b:c-d_9');
        self::assertSame('member:a.b:c-d_9', $member->toString());
        self::assertSame('member:a.b:c-d_9', (string) $member);
        self::assertTrue($member->equals(Member::fromString('member:a.b:c-d_9')));
        self::assertFalse($member->equals(Member::of('member', 'a')));
        self::assertFalse($member->equals(Member::of('user', 'a.b:c-d_9')));
        self::assertSame(['user', 'x:y'], [Member::fromString('user:x:y')->kind, Member::fromString('user:x:y')->reference]);
        self::assertSame(64, \strlen(Member::of(str_repeat('k', 64), 'r')->kind));

        foreach ([['', 'r', 'kind'], [str_repeat('k', 65), 'r', 'kind'], ['k', 'r r', 'reference'], ['k', '', 'reference']] as [$kind, $reference, $which]) {
            try {
                Member::of($kind, $reference);
                self::fail('Refused.');
            } catch (InvalidEvent $invalid) {
                self::assertSame(\sprintf('The %s of a member is 1 to 64 letters, digits, "_", ".", ":" or "-".', $which), $invalid->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains('"nobody" is not a member, kind:reference.');
        Member::fromString('nobody');
    }

    public function testAnAttendance(): void
    {
        $event = $this->weekly();
        $at = new DateTimeImmutable('2026-11-01 12:00', new DateTimeZone('Europe/Paris'));
        $one = new Attendance($event, $this->at('2026-11-17 19:00'), Member::of('member', 'ana'), $at);
        $every = new Attendance($event, null, Member::of('member', 'ben'), $at);

        self::assertSame(36, \strlen($one->id));
        self::assertSame('2026-11-17 19:00 America/Toronto', $one->occurrence()?->format('Y-m-d H:i e'));
        self::assertNull($every->occurrence());
        self::assertSame('member:ana', $one->member()->toString());
        self::assertSame('2026-11-01 11:00 UTC', $one->createdAt()->format('Y-m-d H:i e'));
        self::assertSame('2026-11-18T00:00:00', Attendance::key($this->at('2026-11-17 19:00')));
        self::assertSame('', Attendance::key(null));
    }

    /**
     * The translations hold the record's limits, trimmed, and belong to their record and locale.
     */
    public function testTheTranslations(): void
    {
        $event = $this->weekly();
        $translation = $event->newTranslation(Locale::fromString('en'));
        self::assertSame([EventTranslation::class, ['title', 'description']], [Event::translationClass(), Event::translatedFields()]);
        self::assertSame([$event, 'en', '', null], [$translation->translatable(), $translation->locale()->value, $translation->title(), $translation->description()]);

        $translation->write(' Game night ', '  ');
        self::assertSame(['Game night', null], [$translation->title(), $translation->description()]);
        $translation->write(str_repeat('a', 200), ' '.str_repeat('b', 65535).' ');
        self::assertSame([200, 65535], [mb_strlen($translation->title()), mb_strlen((string) $translation->description())]);

        foreach ([['', null, 'The title of an event is 1 to 200 characters.'], [str_repeat('a', 201), null, 'The title of an event is 1 to 200 characters.'], ['T', str_repeat('b', 65536), 'The description of an event is 65535 characters or fewer.']] as [$title, $description, $message]) {
            try {
                $translation->write($title, $description);
                self::fail('Refused.');
            } catch (InvalidEvent $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        $venue = new Venue('Grande salle');
        $name = $venue->newTranslation(Locale::fromString('en_CA'));
        self::assertSame([VenueTranslation::class, ['name']], [Venue::translationClass(), Venue::translatedFields()]);
        self::assertSame([$venue, 'en_CA', ''], [$name->translatable(), $name->locale()->value, $name->name()]);
        $name->rename(' Main hall ');
        self::assertSame('Main hall', $name->name());
        $name->rename(str_repeat('n', 200));

        $this->expectExceptionMessageIsOrContains('The name of a venue is 1 to 200 characters.');
        $name->rename(str_repeat('n', 201));
    }

    /**
     * Lengths are in characters, not bytes: a title of 200 accented letters is a title.
     */
    public function testLengthsAreInCharacters(): void
    {
        $event = $this->weekly();
        $translation = $event->newTranslation(Locale::fromString('fr'));
        $translation->write(str_repeat('é', 200), str_repeat('é', 65535));
        self::assertSame([200, 65535], [mb_strlen($translation->title()), mb_strlen((string) $translation->description())]);

        $name = new Venue('Grande salle')->newTranslation(Locale::fromString('fr'));
        $name->rename(str_repeat('é', 200));
        self::assertSame(200, mb_strlen($name->name()));

        $this->expectExceptionMessageIsOrContains('"'.str_repeat('é', 100).'" is not a member, kind:reference.');
        Member::fromString(str_repeat('é', 150));
    }

    public function testARefusalHasNoCode(): void
    {
        self::assertSame(0, InvalidEvent::because('No.')->getCode());
        self::assertNull(InvalidEvent::because('No.')->getPrevious());
    }

    /**
     * What belongs to an event, a venue or a translation goes with it: its row cannot be left without it.
     */
    public function testTheRowsOfARecordCannotBeLeftWithoutIt(): void
    {
        foreach ([[Attendance::class, 'event'], [EventTranslation::class, 'translatable'], [VenueTranslation::class, 'translatable']] as [$class, $property]) {
            $columns = array_map(static fn (ReflectionAttribute $attribute): JoinColumn => $attribute->newInstance(), new ReflectionProperty($class, $property)->getAttributes(JoinColumn::class));

            self::assertCount(1, $columns, $class);
            self::assertFalse($columns[0]->nullable, $class);
            self::assertSame('CASCADE', $columns[0]->onDelete, $class);
        }
    }

    private function weekly(): Event
    {
        $event = Event::schedule('Club', $this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00'));
        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;COUNT=3'));

        return $event;
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('America/Toronto'));
    }
}
