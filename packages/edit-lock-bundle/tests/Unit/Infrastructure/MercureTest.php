<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use Phil\EditLockBundle\Infrastructure\Mercure\MercureBroadcaster;
use Phil\EditLockBundle\Infrastructure\Mercure\TurboStreams;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Mercure\Exception\RuntimeException;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

#[CoversClass(TurboStreams::class)]
#[CoversClass(MercureBroadcaster::class)]
final class MercureTest extends TestCase
{
    public function testTheStreams(): void
    {
        $now = new DateTimeImmutable('2026-11-06 15:00:00 UTC');
        $streams = new TurboStreams();
        $target = TurboStreams::presenceTarget('article:42');

        self::assertSame('phil-edit-lock-presence-'.hash('xxh128', 'article:42'), $target);
        self::assertSame(
            '<turbo-stream action="update" target="'.$target.'"><template><ul class="phil-edit-lock-presence">'
            .'<li data-holder="7" data-part="" data-expires-in="30">Ada &amp; co</li>'
            .'<li data-holder="&quot;8&quot;" data-part="b1" data-expires-in="0">&lt;Bob&gt;</li>'
            .'</ul></template></turbo-stream>',
            $streams->presence('article:42', [
                Lease::grant(LeaseKey::of('article:42'), Holder::of('7', 'Ada & co'), $now, 30),
                Lease::grant(LeaseKey::of('article:42', 'b1'), Holder::of('"8"', '<Bob>'), $now->modify('-1 minute'), 30),
            ], $now),
        );
        self::assertSame('<turbo-stream action="update" target="'.$target.'"><template><ul class="phil-edit-lock-presence"></ul></template></turbo-stream>', $streams->presence('article:42', [], $now));

        $block = new Ujo()->decode('{"blocks":[{"id":"b1","type":"paragraph","data":{"text":"a \"b\" <i>c</i> é"}}]}');
        self::assertSame(
            '<turbo-stream action="replace" target="phil-edit-lock-block-b1" data-holder="7"><template>'
            .'<div id="phil-edit-lock-block-b1" data-list="t1:0" data-after="b0" data-ujo="{&quot;blocks&quot;:[{&quot;id&quot;:&quot;b1&quot;,&quot;type&quot;:&quot;paragraph&quot;,&quot;data&quot;:{&quot;text&quot;:&quot;a &amp;quot;b&amp;quot; &lt;i&gt;c&lt;/i&gt; é&quot;}}]}"></div>'
            .'</template></turbo-stream>',
            $streams->saved(Holder::of('7', 'Ada'), $block, 't1:0', 'b0'),
        );
        self::assertStringContainsString('<div id="phil-edit-lock-block-b1" data-list="root" data-ujo=', $streams->saved(Holder::of('7', 'Ada'), $block, 'root', null));
        self::assertSame('<turbo-stream action="remove" target="phil-edit-lock-block-b1" data-holder="a&amp;b&apos;c"></turbo-stream>', $streams->removed(Holder::of("a&b'c", 'Ada'), 'b1'));
    }

    public function testTheBroadcasterPublishesOnTheTopicOfTheResource(): void
    {
        /** @var list<Update> $updates */
        $updates = [];
        $hub = new MockHub('https://hub.test/.well-known/mercure', new StaticTokenProvider('x.y.z'), static function (Update $update) use (&$updates): string {
            $updates[] = $update;

            return 'id';
        });
        $now = new DateTimeImmutable('2026-11-06 15:00:00 UTC');
        $ada = Holder::of('7', 'Ada');
        $block = new Ujo()->decode('{"blocks":[{"id":"b1","type":"paragraph","data":{"text":"x"}}]}');
        $broadcaster = new MercureBroadcaster($hub);

        $broadcaster->presence('article:42', [Lease::grant(LeaseKey::of('article:42'), $ada, $now, 30)], $now);
        $broadcaster->saved('article:42', $ada, $block, 'root', null);
        $broadcaster->removed('article:42', $ada, 'b1');
        new MercureBroadcaster($hub, new TurboStreams(), 'app:', false)->removed('page', $ada, 'b2');

        $streams = new TurboStreams();
        self::assertSame('urn:phil:edit-lock:article:42', $broadcaster->topic('article:42'));
        self::assertSame([
            [['urn:phil:edit-lock:article:42'], $streams->presence('article:42', [Lease::grant(LeaseKey::of('article:42'), $ada, $now, 30)], $now), true],
            [['urn:phil:edit-lock:article:42'], $streams->saved($ada, $block, 'root', null), true],
            [['urn:phil:edit-lock:article:42'], $streams->removed($ada, 'b1'), true],
            [['app:page'], $streams->removed($ada, 'b2'), false],
        ], array_map(static fn (Update $update): array => [$update->getTopics(), $update->getData(), $update->isPrivate()], $updates));
    }

    public function testAHubThatCannotBeReachedIsLogged(): void
    {
        $hub = new MockHub('https://hub.test/.well-known/mercure', new StaticTokenProvider('x.y.z'), static fn (): never => throw new RuntimeException('Failed to send an update.'));
        $logger = new class extends AbstractLogger {
            /** @var list<array{mixed, string, array<array-key, mixed>}> */
            public array $logs = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->logs[] = [$level, (string) $message, $context];
            }
        };

        new MercureBroadcaster($hub, new TurboStreams(), 'app:', true, $logger)->removed('page', Holder::of('7', 'Ada'), 'b1');
        new MercureBroadcaster($hub)->removed('page', Holder::of('7', 'Ada'), 'b1');

        self::assertCount(1, $logger->logs);
        self::assertSame(['warning', 'The edit lock update of "{resource}" could not be pushed to Mercure: {message}', 'page', 'Failed to send an update.'], [$logger->logs[0][0], $logger->logs[0][1], $logger->logs[0][2]['resource'] ?? null, $logger->logs[0][2]['message'] ?? null]);
        self::assertInstanceOf(RuntimeException::class, $logger->logs[0][2]['exception'] ?? null);
    }
}
