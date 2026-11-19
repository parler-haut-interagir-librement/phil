<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit\Application\Visitor;

use Phil\ShortLinkBundle\Application\Visitor\KnownVisitorClassifier;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(KnownVisitorClassifier::class)]
#[CoversClass(VisitorClass::class)]
final class KnownVisitorClassifierTest extends TestCase
{
    private const string FIREFOX = 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0';

    /**
     * @return iterable<string, array{?string, ?string, VisitorClass}>
     */
    public static function visits(): iterable
    {
        yield 'Firefox' => [self::FIREFOX, null, VisitorClass::Human];
        yield 'Chrome on Android' => ['Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36', null, VisitorClass::Human];
        yield 'Safari on iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1', null, VisitorClass::Human];
        yield 'the in-app browser of a social network' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/22B83 [FBAN/FBIOS;FBAV/490.0.0.0]', null, VisitorClass::Human];
        yield 'a prefetch' => [self::FIREFOX, 'prefetch', VisitorClass::Prefetch];
        yield 'a prerender, among other tokens' => [self::FIREFOX, 'prefetch;anonymous-client-ip, prerender', VisitorClass::Prefetch];
        yield 'a preview, in capitals' => [self::FIREFOX, 'Preview', VisitorClass::Prefetch];
        yield 'a prefetch by a robot' => ['curl/8.7.1', ' prefetch ', VisitorClass::Prefetch];
        yield 'another purpose' => [self::FIREFOX, 'navigate,prefetching', VisitorClass::Human];
        yield 'an empty purpose' => [self::FIREFOX, '', VisitorClass::Human];
        yield 'Slack' => ['Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)', null, VisitorClass::LinkPreview];
        yield 'Facebook and iMessage' => ['facebookexternalhit/1.1 Facebot Twitterbot/1.0', null, VisitorClass::LinkPreview];
        yield 'Discord' => ['Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)', null, VisitorClass::LinkPreview];
        yield 'Telegram' => ['TelegramBot (like TwitterBot)', null, VisitorClass::LinkPreview];
        yield 'WhatsApp' => ['WhatsApp/2.23.20.0 A', null, VisitorClass::LinkPreview];
        yield 'Teams' => ['Mozilla/5.0 (Windows NT 6.1; WOW64) SkypeUriPreview Preview/0.5 skype-url-preview@microsoft.com', null, VisitorClass::LinkPreview];
        yield 'Mastodon' => ['http.rb/5.1.1 (Mastodon/4.3.0; +https://mastodon.example/)', null, VisitorClass::LinkPreview];
        yield 'a search engine' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', null, VisitorClass::Robot];
        yield 'a script' => ['python-requests/2.32.3', null, VisitorClass::Robot];
        yield 'curl' => ['curl/8.7.1', null, VisitorClass::Robot];
        yield 'a headless browser' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/131.0.0.0 Safari/537.36', null, VisitorClass::Robot];
        yield 'a monitor' => ['Mozilla/5.0 (compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)', null, VisitorClass::Robot];
        yield 'no User-Agent' => [null, null, VisitorClass::Robot];
        yield 'an empty User-Agent' => ['', null, VisitorClass::Robot];
        yield 'a User-Agent of spaces' => ['   ', null, VisitorClass::Robot];
    }

    #[DataProvider('visits')]
    public function testAVisitIsClassifiedFromItsHeaders(?string $userAgent, ?string $purpose, VisitorClass $class): void
    {
        self::assertSame($class, new KnownVisitorClassifier()->classify($userAgent, $purpose));
    }

    public function testTheApplicationAddsItsOwnPatterns(): void
    {
        $classifier = new KnownVisitorClassifier([' ChatApp ', ''], ['Harvester']);

        self::assertSame(VisitorClass::LinkPreview, $classifier->classify('Mozilla/5.0 ChatApp-Preview/1.0', null));
        self::assertSame(VisitorClass::Robot, $classifier->classify('MyHarvester 2.0', null));
        self::assertSame(VisitorClass::Human, $classifier->classify(self::FIREFOX, null));
        self::assertSame(VisitorClass::LinkPreview, $classifier->classify('discordbot', null), 'the known patterns stay');
    }

    public function testOnlyTheStartOfALongHeaderIsRead(): void
    {
        $classifier = new KnownVisitorClassifier();
        $padding = str_repeat('a', KnownVisitorClassifier::MAX_USER_AGENT - 3);

        self::assertSame(VisitorClass::Robot, $classifier->classify($padding.'bot', null));
        self::assertSame(VisitorClass::Human, $classifier->classify($padding.'abot', null));
        self::assertSame(VisitorClass::Prefetch, $classifier->classify(self::FIREFOX, str_repeat(' ', KnownVisitorClassifier::MAX_PURPOSE - 8).'prefetch'));
        self::assertSame(VisitorClass::Human, $classifier->classify(self::FIREFOX, str_repeat(' ', KnownVisitorClassifier::MAX_PURPOSE - 7).'prefetch'));
    }

    public function testOnlyAPersonIsHuman(): void
    {
        self::assertSame([true, false, false, false], array_map(static fn (VisitorClass $class): bool => $class->isHuman(), VisitorClass::cases()));
    }
}
