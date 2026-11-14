<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Phil\NotificationsBundle\Application\WebPushMessage;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebPushMessage::class)]
final class WebPushMessageTest extends TestCase
{
    public function testTheMessageCarriesTheToastAndThePushServiceHeaders(): void
    {
        $message = WebPushMessage::of($this->rendered('Café ouvert', '/a/b'), new NotificationType('comment.replied'));

        self::assertSame('{"id":"n1","type":"comment.replied","title":"Hello","body":"Café ouvert","url":"/a/b","count":1}', $message->payload);
        self::assertSame('normal', $message->urgency);
        self::assertNull($message->topic);
        self::assertSame(WebPushMessage::TTL, $message->ttl);
        self::assertSame(86400, WebPushMessage::TTL);
    }

    public function testTheUrgencyFollowsTheImportance(): void
    {
        foreach (['urgent' => 'high', 'high' => 'high', 'medium' => 'normal', 'low' => 'low'] as $importance => $urgency) {
            self::assertSame($urgency, WebPushMessage::of($this->rendered('B'), new NotificationType('welcome', importance: $importance))->urgency, $importance);
        }
    }

    public function testACollapsingTypeGivesATopicOfItsGroup(): void
    {
        $type = new NotificationType('comment.replied', collapse: true);
        $topic = WebPushMessage::of($this->rendered('B'), $type)->topic;
        $other = WebPushMessage::of($this->rendered('B', subject: '10'), $type)->topic;

        self::assertNotNull($topic);
        self::assertSame(32, \strlen($topic));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{32}$/D', $topic);
        self::assertSame(substr(sodium_bin2base64(hash('sha256', 'comment.replied|comment:9', true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), 0, 32), $topic);
        self::assertNotSame($topic, $other);
        self::assertSame($topic, WebPushMessage::of($this->rendered('Other'), $type)->topic);
    }

    public function testTheTimeToLiveIsBounded(): void
    {
        $type = new NotificationType('welcome');

        self::assertSame(0, WebPushMessage::of($this->rendered('B'), $type, 0)->ttl);
        self::assertSame(WebPushMessage::MAX_TTL, WebPushMessage::of($this->rendered('B'), $type, 2_419_200)->ttl);

        foreach ([-1, WebPushMessage::MAX_TTL + 1] as $ttl) {
            try {
                WebPushMessage::of($this->rendered('B'), $type, $ttl);
                self::fail('A time to live out of its bounds is refused.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(\sprintf('The time to live of a Web Push message is between 0 and 2419200 seconds, not %d.', $ttl), $invalidArgumentException->getMessage());
            }
        }
    }

    public function testALongBodyIsShortenedOnACharacterBoundary(): void
    {
        $message = WebPushMessage::of($this->rendered(str_repeat('é', 3000), '/a'), new NotificationType('welcome'));
        $fields = json_decode($message->payload, true, 4, \JSON_THROW_ON_ERROR);

        self::assertIsArray($fields);
        self::assertLessThanOrEqual(WebPushMessage::MAX_PAYLOAD, \strlen($message->payload));
        self::assertGreaterThan(WebPushMessage::MAX_PAYLOAD - 8, \strlen($message->payload));
        self::assertIsString($fields['body']);
        self::assertTrue(mb_check_encoding($fields['body'], 'UTF-8'));
        self::assertStringEndsWith('é…', $fields['body']);
        self::assertSame('Hello', $fields['title']);
        self::assertSame('/a', $fields['url']);
    }

    public function testABodyThatFitsExactlyIsKept(): void
    {
        $empty = \strlen(WebPushMessage::of($this->rendered(''), new NotificationType('welcome'))->payload);
        $body = str_repeat('a', WebPushMessage::MAX_PAYLOAD - $empty);

        self::assertStringContainsString('"body":"'.$body.'"', WebPushMessage::of($this->rendered($body), new NotificationType('welcome'))->payload);
        self::assertStringContainsString('…', WebPushMessage::of($this->rendered($body.'a'), new NotificationType('welcome'))->payload);
    }

    public function testEscapedCharactersAreCutUntilTheyFit(): void
    {
        $message = WebPushMessage::of($this->rendered(str_repeat("\x01", 2000)), new NotificationType('welcome'));

        self::assertLessThanOrEqual(WebPushMessage::MAX_PAYLOAD, \strlen($message->payload));
        self::assertStringEndsWith('\u0001…","url":null,"count":1}', $message->payload);
        self::assertGreaterThan(WebPushMessage::MAX_PAYLOAD - 6, \strlen($message->payload));
    }

    /**
     * @return iterable<string, array{string, string|null, string}>
     */
    public static function longTexts(): iterable
    {
        yield 'two-byte' => [str_repeat('é', 3000), '/a', 'Hello'];
        yield 'three-byte' => [str_repeat('€', 1500), '/a', 'Hello'];
        yield 'four-byte' => [str_repeat("\u{1F600}", 1100), '/a', 'Hello'];
        yield 'escaped' => [str_repeat("\x01", 2000), '/a', 'Hello'];
        yield 'quotes' => [str_repeat('"', 3000), '/a', 'Hello'];
        yield 'ascii' => [str_repeat('a', 4000), '/a', 'Hello'];
        yield 'mixed' => [str_repeat("a\u{1F600}b€é", 700), null, 'Hello'];
        yield 'title' => ['body', '/a', str_repeat('é', 4000)];
        yield 'title escaped' => ['', '/a', str_repeat("\x02", 2000)];
        yield 'short body' => ['x', '/a', str_repeat('€', 1500)];
    }

    #[DataProvider('longTexts')]
    public function testTheLongestBeginningThatFitsIsKept(string $body, ?string $url, string $title): void
    {
        $type = new NotificationType('welcome');
        $payload = WebPushMessage::of($this->rendered($body, $url, $title), $type)->payload;
        $fields = json_decode($payload, true, 4, \JSON_THROW_ON_ERROR);
        self::assertIsArray($fields);
        $field = \strlen($body) > 100 ? 'body' : 'title';
        $text = 'body' === $field ? $body : $title;
        $encode = static fn (array $f): string => json_encode($f, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $cut = static function (string $t, int $bytes): string {
            $c = mb_strcut($t, 0, $bytes, 'UTF-8');

            return '' === $c ? '' : $c.'…';
        };
        $expected = $fields;
        $expected[$field] = '';

        for ($bytes = \strlen($text) - 1; $bytes >= 0; --$bytes) {
            $expected[$field] = $cut($text, $bytes);

            if (\strlen($encode($expected)) <= WebPushMessage::MAX_PAYLOAD) {
                break;
            }
        }

        self::assertSame($expected[$field], $fields[$field]);
        self::assertLessThanOrEqual(WebPushMessage::MAX_PAYLOAD, \strlen($payload));
    }

    public function testACutThatDropsAWholeCharacterCanStillFit(): void
    {
        $type = new NotificationType('welcome');
        $empty = \strlen(WebPushMessage::of($this->rendered(''), $type)->payload);
        $room = WebPushMessage::MAX_PAYLOAD - $empty;
        // The body without its last, four-byte character plus the ellipsis fits exactly; the whole body does not.
        $body = str_repeat('a', $room - 3).'😀';
        $payload = WebPushMessage::of($this->rendered($body), $type)->payload;

        self::assertStringContainsString('"body":"'.str_repeat('a', $room - 3).'…"', $payload);
        self::assertSame(WebPushMessage::MAX_PAYLOAD, \strlen($payload));
    }

    public function testTheTitleThenTheUrlGiveWayWhenTheBodyIsNotEnough(): void
    {
        $title = WebPushMessage::of($this->rendered('body', '/a', str_repeat('t', 5000)), new NotificationType('welcome'));
        $fields = json_decode($title->payload, true, 4, \JSON_THROW_ON_ERROR);

        self::assertIsArray($fields);
        self::assertSame('', $fields['body']);
        self::assertIsString($fields['title']);
        self::assertStringEndsWith('t…', $fields['title']);
        self::assertSame('/a', $fields['url']);
        self::assertLessThanOrEqual(WebPushMessage::MAX_PAYLOAD, \strlen($title->payload));

        $url = WebPushMessage::of($this->rendered('body', '/'.str_repeat('u', 5000), 'title'), new NotificationType('welcome'));

        self::assertSame('{"id":"n1","type":"comment.replied","title":"","body":"","url":null,"count":1}', $url->payload);
    }

    private function rendered(string $body, ?string $url = null, string $title = 'Hello', string $subject = '9'): RenderedNotification
    {
        return new RenderedNotification(new Notification('n1', 'comment.replied', '42', new SubjectReference('comment', $subject), new DateTimeImmutable('2026-11-08 10:00 UTC')), $title, $body, $url);
    }
}
