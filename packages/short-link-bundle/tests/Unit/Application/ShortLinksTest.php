<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\RedirectsBundle\Application\TargetPolicy;
use Phil\RedirectsBundle\Domain\Enum\QueryPolicy;
use Phil\RedirectsBundle\Domain\Enum\RedirectStatus;
use Phil\ShortLinkBundle\Application\Code\CodeGenerator;
use Phil\ShortLinkBundle\Application\Code\SqidCodec;
use Phil\ShortLinkBundle\Application\Contract\CreationThrottle;
use Phil\ShortLinkBundle\Application\Contract\TargetCheck;
use Phil\ShortLinkBundle\Application\Exception\ShortLinkNotFound;
use Phil\ShortLinkBundle\Application\Exception\ShortLinkRefused;
use Phil\ShortLinkBundle\Application\ShortLinkDraft;
use Phil\ShortLinkBundle\Application\ShortLinks;
use Phil\ShortLinkBundle\Domain\Exception\InvalidShortLink;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use Phil\ShortLinkBundle\Domain\ValueObject\LinkTerms;
use Phil\ShortLinkBundle\Tests\Unit\Fixture\Links;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine;
use Random\Randomizer;

#[CoversClass(ShortLinks::class)]
#[CoversClass(ShortLinkDraft::class)]
#[CoversClass(ShortLinkRefused::class)]
#[CoversClass(ShortLinkNotFound::class)]
final class ShortLinksTest extends TestCase
{
    private Links $fixture;

    protected function setUp(): void
    {
        $this->fixture = new Links();
    }

    public function testALinkIsCreatedWithADrawnCodeAndItsTargetInCanonicalForm(): void
    {
        $draft = $this->draft('HTTPS://Example.COM/page?x=1#top');
        $draft->label = '  Affiche  ';
        $draft->locale = 'fr';
        $draft->status = RedirectStatus::TemporaryRedirect;
        $draft->keepQuery = true;
        $draft->validUntil = new DateTimeImmutable('2026-12-01 UTC');
        $draft->maxVisits = 10;

        $link = $this->fixture->links()->create($draft, 'user:7');

        self::assertSame(1, $link->id());
        self::assertSame('https://example.com/page?x=1#top', $link->target());
        self::assertSame(['Affiche', 'fr', RedirectStatus::TemporaryRedirect, QueryPolicy::Keep, 10], [$link->label(), $link->locale(), $link->status(), $link->query(), $link->maxVisits()]);
        self::assertTrue($this->fixture->codec->isCanonical($link->code()));
        self::assertGreaterThanOrEqual(6, \strlen($link->code()));
        self::assertEquals(new DateTimeImmutable('2026-11-12 12:00 UTC'), $link->createdAt());
        self::assertSame($link, $this->fixture->repository->find(1));
        self::assertSame('https://lien.example.fr/'.$link->code(), $this->fixture->links()->url($link));
    }

    public function testADrawnCodeAlreadyTakenIsDrawnAgain(): void
    {
        $codec = new SqidCodec();
        $engine = new class implements Engine {
            private int $draw = 0;

            public function generate(): string
            {
                // The same number twice, then another.
                return pack('P', $this->draw++ < 2 ? 5_000_000 : 6_000_000);
            }
        };
        $this->fixture->codec = $codec;
        $links = $this->fixture->links(generator: new CodeGenerator($codec, randomizer: new Randomizer($engine)));
        $first = $links->create($this->draft(), 'author');
        $second = $links->create($this->draft(), 'author');

        self::assertNotSame($first->code(), $second->code());
        self::assertCount(2, $this->fixture->repository->links);
    }

    public function testACodeThatIsNeverFreeIsRefused(): void
    {
        $codec = new SqidCodec();
        $engine = new class implements Engine {
            public function generate(): string
            {
                return pack('P', 5_000_000);
            }
        };
        $this->fixture->codec = $codec;
        $links = $this->fixture->links(generator: new CodeGenerator($codec, randomizer: new Randomizer($engine)));
        $links->create($this->draft(), 'author');

        $lookups = \count($this->fixture->repository->lookups);

        self::assertSame('code_exhausted', $this->reason(fn (): ShortLink => $links->create($this->draft(), 'author')));
        self::assertSame(ShortLinks::ATTEMPTS, \count($this->fixture->repository->lookups) - $lookups);
    }

    public function testAChosenCodeIsKept(): void
    {
        $draft = $this->draft();
        $draft->code = '  Fete-2026  ';

        $this->fixture->codec = new SqidCodec(SqidCodec::DEFAULT_ALPHABET.'-');

        self::assertSame('Fete-2026', $this->fixture->links()->create($draft, 'author')->code());
        self::assertSame('abc', $this->fixture->links()->create($this->draft(code: 'abc'), 'author')->code(), 'three characters is enough');
        self::assertSame(str_repeat('b', 64), $this->fixture->links()->create($this->draft(code: str_repeat('b', 64)), 'author')->code());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedCodes(): iterable
    {
        yield 'two characters' => ['ab', 'code_invalid'];
        yield 'too long' => [str_repeat('a', 65), 'code_invalid'];
        yield 'out of the alphabet' => ['fête', 'code_invalid'];
        yield 'with a slash' => ['ab/cd', 'code_invalid'];
        yield 'blocked' => ['xxfuckxx', 'code_blocked'];
        yield 'taken' => ['taken', 'code_taken'];
    }

    #[DataProvider('refusedCodes')]
    public function testAChosenCodeIsChecked(string $code, string $reason): void
    {
        $this->fixture->repository->save(new ShortLink('taken', 'https://example.com/', new DateTimeImmutable()));

        self::assertSame($reason, $this->reason(fn (): ShortLink => $this->fixture->links()->create($this->draft(code: $code), 'author')));
    }

    public function testAChosenCodeIsRefusedWhenCodesAreDrawn(): void
    {
        $links = $this->fixture->links(customCodes: false);

        self::assertFalse($links->customCodesAllowed());
        self::assertTrue($this->fixture->links()->customCodesAllowed());
        self::assertSame('code_custom', $this->reason(fn (): ShortLink => $links->create($this->draft(code: 'abcd'), 'author')));
        self::assertNotSame('', $links->create($this->draft(code: '   '), 'author')->code());
    }

    /**
     * @return iterable<string, array{?string, string, string}>
     */
    public static function refusedTargets(): iterable
    {
        yield 'none' => [null, 'target', 'The target is an absolute http or https URL.'];
        yield 'a path' => ['/page', 'target', 'The target is an absolute http or https URL.'];
        yield 'another scheme' => ['ftp://example.com/', 'target', 'The target is an absolute http or https URL.'];
        yield 'javascript' => ['javascript:alert(1)', 'target', 'The target is an absolute http or https URL.'];
        yield 'an address' => ['http://10.0.0.1/', 'target', 'The target is an absolute http or https URL.'];
        yield 'localhost' => ['http://localhost/', 'target', 'The target is an absolute http or https URL.'];
        yield 'a short link' => ['https://GO.example.com/abc', 'target_loop', 'A short link does not send to another short link.'];
        yield 'another host' => ['https://evil.example/', 'target_host', 'The host "evil.example" is not one a short link may send to.'];
        yield 'a subdomain' => ['https://www.example.com/', 'target_host', 'The host "www.example.com" is not one a short link may send to.'];
    }

    #[DataProvider('refusedTargets')]
    public function testATargetIsChecked(?string $target, string $reason, string $message): void
    {
        try {
            $this->fixture->links()->create($this->draft($target), 'author');
            self::fail('The target was accepted.');
        } catch (ShortLinkRefused $shortLinkRefused) {
            self::assertSame([$reason, $message], [$shortLinkRefused->reason, $shortLinkRefused->getMessage()]);
        }

        self::assertSame([], $this->fixture->repository->links);
    }

    public function testAnyHostMayBeAllowedButNeverAShortLinkDomain(): void
    {
        $links = $this->fixture->links(anyHost: true);

        self::assertSame('https://evil.example/', $links->create($this->draft('https://evil.example/'), 'author')->target());
        self::assertSame('target_loop', $this->reason(fn (): ShortLink => $links->create($this->draft('https://lien.example.fr/x'), 'author')));
    }

    public function testTheCheckOfATargetCanRefuseIt(): void
    {
        $check = new class implements TargetCheck {
            /** @var list<string> */
            public array $checked = [];

            public function refusal(Url $target): ?string
            {
                $this->checked[] = $target->toString();

                return str_contains($target->path, 'down') ? 'it does not answer' : null;
            }
        };
        $links = $this->fixture->links(check: $check);

        try {
            $links->create($this->draft('https://example.com/down'), 'author');
            self::fail('The target was accepted.');
        } catch (ShortLinkRefused $shortLinkRefused) {
            self::assertSame(['target_unreachable', 'The target could not be read: it does not answer.', ['%reason%' => 'it does not answer']], [$shortLinkRefused->reason, $shortLinkRefused->getMessage(), $shortLinkRefused->parameters]);
        }

        $links->create($this->draft('https://example.com/up'), 'author');
        self::assertSame(['https://example.com/down', 'https://example.com/up'], $check->checked);
    }

    public function testALocaleWithoutADomainIsRefused(): void
    {
        $draft = $this->draft();
        $draft->locale = 'de';

        self::assertSame('locale', $this->reason(fn (): ShortLink => $this->fixture->links()->create($draft, 'author')));
        $draft->locale = '';
        self::assertNull($this->fixture->links()->create($draft, 'author')->locale());
    }

    public function testTheRulesOfTheLinkAreRefusalsOfTheForm(): void
    {
        $draft = $this->draft();
        $draft->maxVisits = 0;

        try {
            $this->fixture->links()->create($draft, 'author');
            self::fail('The link was accepted.');
        } catch (ShortLinkRefused $shortLinkRefused) {
            self::assertSame(['max_visits', 'A maximum number of visits is 1 or more.', 'A maximum number of visits is 1 or more.', []], [$shortLinkRefused->reason, $shortLinkRefused->template, $shortLinkRefused->getMessage(), $shortLinkRefused->parameters]);
        }

        $from = ShortLinkRefused::from(InvalidShortLink::because('label', 'A label has %max% characters at most.', ['%max%' => '200']));
        self::assertSame(['label', 'A label has 200 characters at most.', ['%max%' => '200']], [$from->reason, $from->getMessage(), $from->parameters]);
    }

    public function testCreatingSpendsTheAllowanceOfTheAuthorOnceTheLinkIsValid(): void
    {
        $throttle = new class implements CreationThrottle {
            /** @var list<string> */
            public array $authors = [];

            public function consume(string $author): bool
            {
                $this->authors[] = $author;

                return \count($this->authors) < 2;
            }
        };
        $links = $this->fixture->links(throttle: $throttle);
        $links->create($this->draft(), 'user:7');
        $this->reason(fn (): ShortLink => $links->create($this->draft('https://evil.example/'), 'user:7'));

        self::assertSame('rate_limited', $this->reason(fn (): ShortLink => $links->create($this->draft(), 'user:8')));
        self::assertSame(['user:7', 'user:8'], $throttle->authors, 'a refused target spends nothing');
        self::assertCount(1, $this->fixture->repository->links);
    }

    public function testALinkIsChangedButKeepsItsCode(): void
    {
        $links = $this->fixture->links();
        $link = $links->create($this->draft(), 'author');
        $code = $link->code();
        $this->fixture->clock->modify('+1 hour');
        $draft = ShortLinkDraft::of($link);
        $draft->target = 'https://example.fr/nouveau';
        $draft->code = 'ignored';
        $draft->keepQuery = true;

        $changed = $links->update(1, $draft);

        self::assertSame([$code, 'https://example.fr/nouveau', QueryPolicy::Keep], [$changed->code(), $changed->target(), $changed->query()]);
        self::assertEquals(new DateTimeImmutable('2026-11-12 13:00 UTC'), $changed->updatedAt());
        self::assertSame('target_host', $this->reason(fn (): ShortLink => $links->update(1, $this->draft('https://evil.example/'))));
        self::assertSame('locale', $this->reason(function () use ($links): void {
            $draft = $this->draft();
            $draft->locale = 'de';

            $links->update(1, $draft);
        }));
        self::assertSame('dates', $this->reason(function () use ($links): void {
            $draft = $this->draft();
            $draft->validFrom = new DateTimeImmutable('2026-12-02 UTC');
            $draft->validUntil = new DateTimeImmutable('2026-12-01 UTC');

            $links->update(1, $draft);
        }));
        $this->expectException(ShortLinkNotFound::class);
        $this->expectExceptionMessageIsOrContains('There is no short link 9.');
        $links->update(9, $this->draft());
    }

    public function testALinkIsEnabledDisabledAndRemoved(): void
    {
        $links = $this->fixture->links();
        $links->create($this->draft(), 'author');

        $this->fixture->clock->modify('+1 minute');

        self::assertFalse($links->enable(1, false)->isEnabled());
        self::assertEquals(new DateTimeImmutable('2026-11-12 12:01 UTC'), $links->get(1)->updatedAt());
        self::assertTrue($links->enable(1, true)->isEnabled());
        $links->delete(1);
        self::assertSame([], $this->fixture->repository->links);

        foreach ([static fn (ShortLinks $links): ShortLink => $links->get(1), static fn (ShortLinks $links): ShortLink => $links->enable(1, true), static function (ShortLinks $links): void {
            $links->delete(1);
        }] as $call) {
            try {
                $call($links);
                self::fail('A link that is not there was found.');
            } catch (ShortLinkNotFound $notFound) {
                self::assertSame('There is no short link 1.', $notFound->getMessage());
            }
        }
    }

    public function testADraftReadsALinkBack(): void
    {
        $link = new ShortLink('abc', 'https://example.com/', new DateTimeImmutable(), new LinkTerms(RedirectStatus::PermanentRedirect, QueryPolicy::Keep, 'Libellé', 'fr', false, new DateTimeImmutable('2026-01-01 UTC'), new DateTimeImmutable('2026-02-01 UTC'), 4));
        $draft = ShortLinkDraft::of($link);

        self::assertSame(['https://example.com/', 'abc', 'Libellé', 'fr', RedirectStatus::PermanentRedirect, true, false, 4], [$draft->target, $draft->code, $draft->label, $draft->locale, $draft->status, $draft->keepQuery, $draft->enabled, $draft->maxVisits]);
        self::assertEquals($link->terms(), $draft->terms());

        $empty = new ShortLinkDraft();
        $empty->label = '  ';
        self::assertEquals(new LinkTerms(), $empty->terms());
    }

    public function testByDefaultChosenCodesAreAllowedAndOnlyTheAllowedHostsAreTargets(): void
    {
        $links = new ShortLinks(
            $this->fixture->repository,
            $this->fixture->domains,
            $this->fixture->codec,
            new CodeGenerator($this->fixture->codec),
            new TargetPolicy(['example.com']),
            $this->fixture->clock,
        );

        self::assertTrue($links->customCodesAllowed());
        self::assertSame('target_host', $this->reason(fn (): ShortLink => $links->create($this->draft('https://evil.example/'), 'author')));
    }

    public function testATargetIsReadWithoutTheSpacesAroundIt(): void
    {
        self::assertSame('https://example.com/page', $this->fixture->links()->create($this->draft("  https://example.com/page \n"), 'author')->target());
    }

    public function testChangingEnablingAndDisablingSaveTheLink(): void
    {
        $links = $this->fixture->links();
        $links->create($this->draft(), 'author');

        $links->update(1, $this->draft('https://example.fr/'));
        self::assertSame(2, $this->fixture->repository->saves);
        $links->enable(1, false);
        self::assertSame(3, $this->fixture->repository->saves);
    }

    /**
     * @return iterable<string, array{string, ?string, array<string, string>}>
     */
    public static function refusalParameters(): iterable
    {
        yield 'a locale without a domain' => ['locale', null, ['%locale%' => 'de']];
        yield 'a code too short' => ['code_invalid', 'ab', ['%min%' => '3', '%max%' => '64']];
        yield 'a blocked code' => ['code_blocked', 'xxfuckxx', ['%code%' => 'xxfuckxx']];
        yield 'a taken code' => ['code_taken', 'taken', ['%code%' => 'taken']];
    }

    /**
     * @param array<string, string> $parameters
     */
    #[DataProvider('refusalParameters')]
    public function testARefusalNamesItsParameters(string $reason, ?string $code, array $parameters): void
    {
        $this->fixture->repository->save(new ShortLink('taken', 'https://example.com/', new DateTimeImmutable()));
        $draft = $this->draft(code: $code);
        $draft->locale = null === $code ? 'de' : null;

        try {
            $this->fixture->links()->create($draft, 'author');
            self::fail('The link was accepted.');
        } catch (ShortLinkRefused $shortLinkRefused) {
            self::assertSame([$reason, $parameters], [$shortLinkRefused->reason, $shortLinkRefused->parameters]);
        }
    }

    private function draft(?string $target = 'https://example.com/', ?string $code = null): ShortLinkDraft
    {
        $draft = new ShortLinkDraft();
        $draft->target = $target;
        $draft->code = $code;

        return $draft;
    }

    private function reason(callable $call): string
    {
        try {
            $call();
        } catch (ShortLinkRefused $shortLinkRefused) {
            return $shortLinkRefused->reason;
        }

        return '';
    }
}
