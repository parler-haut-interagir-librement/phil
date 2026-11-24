<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Infrastructure\Mailer;

use DateTimeImmutable;
use IntlDateFormatter;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Slot;
use Phil\SchedulingPollBundle\Infrastructure\Mailer\ChosenDateMail;
use Phil\SchedulingPollBundle\Tests\Unit\Fixture\RecordingTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(ChosenDateMail::class)]
final class ChosenDateMailTest extends TestCase
{
    private const string POLL = '0195a6c4-0000-7000-8000-000000000001';

    public static function translator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('php', new PhpFileLoader());

        foreach (['en', 'fr'] as $locale) {
            $translator->addResource('php', \dirname(__DIR__, 4).'/translations/phil_scheduling_poll.'.$locale.'.php', $locale, ChosenDateMail::DOMAIN);
        }

        return $translator;
    }

    public function testTheMailIsInTheRespondentsLanguageAndZoneWithTheCalendarAttached(): void
    {
        $respondent = $this->respondent('fr');
        $respondent->answer([], 'Europe/Paris', new DateTimeImmutable('2026-11-21 UTC'));

        $email = new ChosenDateMail(self::translator(), Address::create('Polls <polls@example.org>'))->build($this->poll('La salle du fond'), $this->slot(), $respondent, new Address('bob@example.org', 'Bob'), new DateTimeImmutable('2026-11-28 UTC'));

        self::assertSame('Date retenue : Soirée jeux', $email->getSubject());
        self::assertSame('polls@example.org', $email->getFrom()[0]->getAddress());
        self::assertSame('"Bob" <bob@example.org>', $email->getTo()[0]->toString());
        self::assertSame('La date de « Soirée jeux » est retenue : '.$this->written('fr', 'Europe/Paris')." (Europe/Paris).\n\nLieu : La salle du fond\n\nLe fichier joint l’ajoute à votre agenda.\n", $email->getTextBody());

        [$attachment] = $email->getAttachments();
        self::assertInstanceOf(DataPart::class, $attachment);
        self::assertSame('event.ics', $attachment->getFilename());
        self::assertSame('text/calendar; charset=utf-8', $attachment->getContentType());
        self::assertStringContainsString("DTSTART:20261202T000000Z\r\n", $attachment->getBody());
    }

    public function testWithoutAZoneOrALanguageThePollsAndTheDefaultAreUsed(): void
    {
        $email = new ChosenDateMail(self::translator(), defaultLocale: 'en')->build($this->poll(null), $this->slot(), $this->respondent(null), new Address('bob@example.org'), new DateTimeImmutable('2026-11-28 UTC'));

        self::assertSame('Date chosen: Soirée jeux', $email->getSubject());
        self::assertSame([], $email->getFrom());
        self::assertSame('The date of “Soirée jeux” is chosen: '.$this->written('en', 'America/Toronto')." (America/Toronto).\n\nThe attached file adds it to your calendar.\n", $email->getTextBody());
    }

    public function testTheTextsAreGivenTheWrittenDateAndAnEmptyPlaceWhenThereIsNone(): void
    {
        $translator = new RecordingTranslator();

        new ChosenDateMail($translator)->build($this->poll(null), $this->slot(), $this->respondent(null), new Address('bob@example.org'), new DateTimeImmutable('2026-11-28 UTC'));

        self::assertSame('', $translator->parameters['%location%']);
        self::assertSame($this->written('en', 'America/Toronto'), $translator->parameters['%date%']);
        self::assertSame('America/Toronto', $translator->parameters['%timezone%']);
    }

    /**
     * The start of the date as the ICU of this machine writes it: the words change from one version of ICU to another.
     */
    private function written(string $locale, string $zone): string
    {
        $written = IntlDateFormatter::create($locale, IntlDateFormatter::FULL, IntlDateFormatter::SHORT, $zone)?->format(new DateTimeImmutable('2026-12-02 00:00 UTC'));
        self::assertIsString($written);
        self::assertStringContainsString('2026', $written);

        return $written;
    }

    private function poll(?string $location): SchedulingPoll
    {
        return new SchedulingPoll(self::POLL, 'Soirée jeux', ResourceReference::of('user', 'ada'), 'America/Toronto', new DateTimeImmutable('2026-11-28 UTC'), new DateTimeImmutable('2026-11-21 UTC'), null, $location);
    }

    private function slot(): Slot
    {
        return new Slot('0195a6c4-0000-7000-8000-000000000002', self::POLL, 0, new DateTimeImmutable('2026-12-02 00:00 UTC'), new DateTimeImmutable('2026-12-02 02:00 UTC'));
    }

    private function respondent(?string $locale): Respondent
    {
        return new Respondent('0195a6c4-0000-7000-8000-000000000003', self::POLL, '0123456789abcdef0123456789abcdef', 'Bob', new DateTimeImmutable('2026-11-21 UTC'), 'bob@example.org', null, $locale);
    }
}
