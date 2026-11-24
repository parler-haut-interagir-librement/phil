<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\TermsBundle\Application\AcceptanceChecker;
use Phil\TermsBundle\Application\Contract\AddressMasker;
use Phil\TermsBundle\Application\Event\TermsAccepted;
use Phil\TermsBundle\Application\Event\VersionPublished;
use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\TextFormat;
use Phil\TermsBundle\Domain\ValueObject\Acceptance;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use Phil\TermsBundle\Tests\Unit\Fixture\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Terms::class)]
#[CoversClass(AcceptanceChecker::class)]
#[CoversClass(VersionPublished::class)]
#[CoversClass(TermsAccepted::class)]
final class TermsTest extends TestCase
{
    public function testAVersionIsNumberedAfterTheLatestAndAnnounced(): void
    {
        $kit = new Kit();
        $first = $kit->terms->publish('terms', ['en' => 'First'], material: false);

        self::assertSame(1, $first->number);
        self::assertTrue($first->material);
        self::assertEquals($kit->clock->now(), $first->publishedAt);
        self::assertEquals($kit->clock->now(), $first->effectiveAt);
        self::assertSame(TextFormat::Text, $first->format);

        $kit->later('+1 hour');
        $minor = $kit->terms->publish('terms', ['en' => 'Second'], effectiveAt: new DateTimeImmutable('2026-06-01'), material: false);
        $material = $kit->terms->publish('terms', ['en' => 'Third']);

        self::assertSame(2, $minor->number);
        self::assertFalse($minor->material);
        self::assertEquals(new DateTimeImmutable('2026-06-01'), $minor->effectiveAt);
        self::assertSame(3, $material->number);
        self::assertTrue($material->material);
        self::assertSame(['terms 1', 'terms 3'], $kit->notifier->published);
        self::assertEquals([new VersionPublished('terms', 1, true), new VersionPublished('terms', 2, false), new VersionPublished('terms', 3, true)], $kit->events->events);
        self::assertSame($material, $kit->catalog->latest('terms'));
    }

    public function testAMomentAlreadyPastIsTheMomentOfPublication(): void
    {
        $kit = new Kit();
        $version = $kit->terms->publish('terms', ['en' => 'x'], effectiveAt: new DateTimeImmutable('2026-04-30'));

        self::assertEquals($kit->clock->now(), $version->effectiveAt);
    }

    public function testATextIsCheckedInItsFormat(): void
    {
        $kit = new Kit();

        try {
            $kit->terms->publish('terms', ['en' => 'not a document'], TextFormat::Ujo);
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame('invalid_document', $invalidTerms->reason);
        }

        self::assertSame([], $kit->catalog->versions('terms'));
        self::assertSame([], $kit->notifier->published);
    }

    public function testAnUndeclaredPolicyIsRefused(): void
    {
        $this->expectExceptionObject(InvalidTerms::because('unknown_policy', 'No policy "%key%" is declared.', ['%key%' => 'cookies']));

        new Kit()->terms->publish('cookies', ['en' => 'x']);
    }

    public function testAnAcceptanceIsRecordedWithItsChannel(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'x']);

        $acceptance = $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Api, '192.0.2.7');

        self::assertEquals(new Acceptance('42', 'terms', 1, $kit->clock->now(), AcceptanceChannel::Api, null, 'en', hash('sha256', '<p>x</p>')), $acceptance);
        self::assertSame([$acceptance], $kit->acceptances->acceptances);
        self::assertEquals(new TermsAccepted('42', 'terms', 1, AcceptanceChannel::Api), $kit->events->events[1]);
    }

    public function testAnAcceptanceKeepsTheLocaleShownAndTheSha256OfItsHtml(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'Hello', 'fr' => "Bonjour\n\nà tous"]);

        $french = $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Form, locale: 'fr');
        self::assertSame('fr', $french->locale);
        self::assertSame(hash('sha256', "<p>Bonjour</p>\n<p>à tous</p>"), $french->textSha256);

        $german = $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Form, locale: 'de');
        self::assertSame('en', $german->locale, 'the default locale, whose text a German reader is shown');
        self::assertSame(hash('sha256', '<p>Hello</p>'), $german->textSha256);

        self::assertSame('en', $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Api)->locale);
    }

    public function testWithoutTheDefaultLocaleTheFirstTextIsShown(): void
    {
        $kit = new Kit();
        $terms = new Terms($kit->policies, $kit->catalog, $kit->versions, $kit->texts, $kit->acceptances, $kit->clock, defaultLocale: 'fr');
        $terms->publish('terms', ['de' => 'Hallo', 'fr' => 'Bonjour']);

        self::assertSame('fr', $terms->accept('42', 'terms', 1, AcceptanceChannel::Form)->locale);
        self::assertSame('fr', $terms->accept('42', 'terms', 1, AcceptanceChannel::Form, locale: 'it')->locale);
        self::assertSame('de', $terms->accept('42', 'terms', 1, AcceptanceChannel::Form, locale: 'de')->locale);
        self::assertSame('de', $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Form, locale: 'it')->locale, 'neither the locale nor the default: the first text');
    }

    public function testAnotherTextGivesAnotherSha256(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'You may post.']);
        $kit->terms->publish('privacy', ['en' => 'You may post!']);

        $first = $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Form);
        $second = $kit->terms->accept('42', 'privacy', 1, AcceptanceChannel::Form);

        self::assertNotSame($first->textSha256, $second->textSha256);
        self::assertSame(hash('sha256', '<p>You may post.</p>'), $first->textSha256);
        self::assertSame(hash('sha256', '<p>You may post!</p>'), $second->textSha256);
    }

    public function testTheTextAnAcceptanceProvesIsChecked(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'Hello', 'fr' => 'Bonjour']);

        $acceptance = $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Form, locale: 'fr');
        $at = $kit->clock->now();

        self::assertTrue($kit->terms->textMatches($acceptance));
        self::assertFalse($kit->terms->textMatches(new Acceptance('42', 'terms', 1, $at, AcceptanceChannel::Form, null, 'en', $acceptance->textSha256)), 'the text of another locale');
        self::assertFalse($kit->terms->textMatches(new Acceptance('42', 'terms', 1, $at, AcceptanceChannel::Form, null, 'fr', hash('sha256', '<p>Bonjour !</p>'))), 'another text');
        self::assertFalse($kit->terms->textMatches(new Acceptance('42', 'terms', 2, $at, AcceptanceChannel::Form, null, 'fr', $acceptance->textSha256)), 'a version not found');
        self::assertNull($kit->terms->textMatches(new Acceptance('42', 'terms', 1, $at, AcceptanceChannel::Form)));
        self::assertNull($kit->terms->textMatches(new Acceptance('42', 'terms', 1, $at, AcceptanceChannel::Form, null, 'fr')));
        self::assertNull($kit->terms->textMatches(new Acceptance('42', 'terms', 1, $at, AcceptanceChannel::Form, null, null, $acceptance->textSha256)));
    }

    public function testTheAddressIsKeptAsTheMaskerTurnsIt(): void
    {
        $kit = new Kit();
        $masker = new class implements AddressMasker {
            public function mask(string $address): string
            {
                return 'net:'.$address;
            }
        };
        $terms = new Terms($kit->policies, $kit->catalog, $kit->versions, $kit->texts, $kit->acceptances, $kit->clock, $masker);
        $terms->publish('terms', ['en' => 'x']);

        self::assertSame('net:192.0.2.7', $terms->accept('42', 'terms', 1, AcceptanceChannel::Form, '192.0.2.7')->network);
        self::assertNull($terms->accept('42', 'terms', 1, AcceptanceChannel::Form)->network);
    }

    public function testAnUpcomingVersionMayBeAcceptedButNotAReplacedOne(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'one']);
        $kit->terms->publish('terms', ['en' => 'two']);
        $kit->terms->publish('terms', ['en' => 'three'], effectiveAt: new DateTimeImmutable('2026-06-01'));

        self::assertSame(2, $kit->terms->accept('42', 'terms', 2, AcceptanceChannel::Form)->version);
        self::assertSame(3, $kit->terms->accept('42', 'terms', 3, AcceptanceChannel::Form)->version);

        try {
            $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Form);
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame('superseded_version', $invalidTerms->reason);
            self::assertSame(['%policy%' => 'terms', '%version%' => '1', '%current%' => '2'], $invalidTerms->parameters);
        }

        try {
            $kit->terms->accept('42', 'terms', 4, AcceptanceChannel::Form);
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame('unknown_version', $invalidTerms->reason);
            self::assertSame(['%policy%' => 'terms', '%version%' => '4'], $invalidTerms->parameters);
        }
    }

    public function testAVersionNotYetInForceMayBeAcceptedBeforeAnyIs(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'one'], effectiveAt: new DateTimeImmutable('2026-06-01'));

        self::assertSame(1, $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Signup)->version);
    }

    public function testABadMemberIsRefusedBeforeAnythingIsKept(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'x']);

        try {
            $kit->terms->accept('a b', 'terms', 1, AcceptanceChannel::Form);
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame('member', $invalidTerms->reason);
        }

        self::assertSame([], $kit->acceptances->acceptances);
    }

    public function testWhatAMemberHasToAccept(): void
    {
        $kit = new Kit();
        $terms = $kit->terms->publish('terms', ['en' => 'terms 1']);
        $privacy = $kit->terms->publish('privacy', ['en' => 'privacy 1']);
        $kit->terms->publish('conduct', ['en' => 'conduct 1']);
        $now = $kit->clock->now();

        self::assertSame([$terms, $privacy], $kit->checker->pending('42', $now));

        $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Form);
        self::assertSame([$privacy], $kit->checker->pending('42', $now));

        $kit->terms->accept('42', 'privacy', 1, AcceptanceChannel::Form);
        self::assertSame([], $kit->checker->pending('42', $now));
        self::assertSame([$terms, $privacy], $kit->checker->pending('43', $now));
    }

    public function testAMinorVersionAsksNothingButTheVersionInForceIsTheOneShown(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'one']);
        $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Form);

        $minor = $kit->terms->publish('terms', ['en' => 'two'], material: false);

        self::assertSame([$minor], $kit->checker->pending('43', $kit->clock->now()));
        self::assertSame([], array_filter($kit->checker->pending('42', $kit->clock->now()), static fn (PolicyVersion $version): bool => 'terms' === $version->policy));
    }

    public function testAMaterialVersionIsAskedOnceInForce(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'one']);
        $kit->terms->publish('privacy', ['en' => 'one']);
        $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Form);
        $kit->terms->accept('42', 'privacy', 1, AcceptanceChannel::Form);

        $two = $kit->terms->publish('terms', ['en' => 'two'], effectiveAt: new DateTimeImmutable('2026-05-10'));

        self::assertSame([], $kit->checker->pending('42', $kit->clock->now()));
        self::assertSame([$two], $kit->checker->pending('42', new DateTimeImmutable('2026-05-10')));

        $kit->terms->accept('42', 'terms', 2, AcceptanceChannel::Form);
        self::assertSame([], $kit->checker->pending('42', new DateTimeImmutable('2026-05-10')));
    }

    public function testARenewalAsksTheVersionAgainOnceTheDaysHavePassed(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'one']);

        $privacy = $kit->terms->publish('privacy', ['en' => 'one']);
        $kit->terms->accept('42', 'terms', 1, AcceptanceChannel::Form);
        $kit->terms->accept('42', 'privacy', 1, AcceptanceChannel::Form);

        self::assertSame([], $kit->checker->pending('42', new DateTimeImmutable('2026-05-31 10:00')));
        self::assertSame([$privacy], $kit->checker->pending('42', new DateTimeImmutable('2026-05-31 10:00:01')));
    }

    public function testAPolicyWithoutVersionAsksNothing(): void
    {
        self::assertSame([], new Kit()->checker->pending('42', new DateTimeImmutable()));
    }
}
