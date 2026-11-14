<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application\WebPush;

use Phil\NotificationsBundle\Application\Exception\InvalidPushSubscription;
use Phil\NotificationsBundle\Application\WebPush\PushSubscription;
use Phil\NotificationsBundle\Domain\Exception\NotificationsException;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Browser;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PushSubscription::class)]
#[CoversClass(InvalidPushSubscription::class)]
final class PushSubscriptionTest extends TestCase
{
    public function testABrowsersSubscriptionIsRead(): void
    {
        $subscription = PushSubscription::fromJson(Browser::json('https://FCM.googleapis.com/fcm/send/abc:123'));

        self::assertSame('https://fcm.googleapis.com/fcm/send/abc:123', $subscription->endpoint);
        self::assertSame('fcm.googleapis.com', $subscription->host);
        self::assertSame(Browser::key(), $subscription->publicKey);
        self::assertSame(87, \strlen($subscription->publicKey));
        self::assertSame(Browser::AUTH, $subscription->authToken);
        self::assertSame('aes128gcm', $subscription->contentEncoding);
        self::assertSame(hash('sha256', 'https://fcm.googleapis.com/fcm/send/abc:123'), $subscription->id());
        self::assertSame('aesgcm', PushSubscription::fromJson(Browser::json(encoding: 'aesgcm'))->contentEncoding);
    }

    public function testTheReasonOfAnAddressThatIsNoneIsGiven(): void
    {
        try {
            Url::fromString('not a url');
            self::fail('Refused');
        } catch (InvalidUrl $invalidUrl) {
            $reason = $invalidUrl->getMessage();
        }

        self::assertNotSame('', $reason);
        $this->expectExceptionObject(InvalidPushSubscription::because('The endpoint of a push subscription is a web address: '.$reason));

        PushSubscription::of('not a url', Browser::key(), Browser::AUTH);
    }

    public function testTheEncodingOfARefusalIsCutAtFortyBytes(): void
    {
        $long = str_repeat('a', 50);

        try {
            PushSubscription::of('https://fcm.googleapis.com/x', Browser::key(), Browser::AUTH, $long);
            self::fail('Refused');
        } catch (InvalidPushSubscription $invalidPushSubscription) {
            self::assertStringEndsWith(\sprintf('not "%s".', str_repeat('a', 40)), $invalidPushSubscription->getMessage());
        }

        $this->expectExceptionObject(InvalidPushSubscription::because(\sprintf('The content encoding of a push subscription is "aes128gcm" or "aesgcm", not "%s".', str_repeat('a', 39))));

        PushSubscription::of('https://fcm.googleapis.com/x', Browser::key(), Browser::AUTH, str_repeat('a', 39).'€€');
    }

    public function testJsonAsDeepAsAnEndpointAndItsKeysCanBeIsRead(): void
    {
        self::assertSame('fcm.googleapis.com', PushSubscription::fromJson(substr(Browser::json(), 0, -1).',"x":[[1]]}')->host);
    }

    public function testPaddingIsDropped(): void
    {
        $subscription = PushSubscription::of('https://web.push.apple.com/x', Browser::key().'=', Browser::AUTH.'==');

        self::assertSame(Browser::key(), $subscription->publicKey);
        self::assertSame(Browser::AUTH, $subscription->authToken);
    }

    /**
     * @return iterable<string, array{string, string, string, string, non-empty-string}>
     */
    public static function invalid(): iterable
    {
        $key = Browser::key();
        $auth = Browser::AUTH;

        yield 'no address' => ['not a url', $key, $auth, 'aes128gcm', 'The endpoint of a push subscription is a web address: '];
        yield 'an IP address' => ['https://10.0.0.1/push', $key, $auth, 'aes128gcm', 'The endpoint of a push subscription is a web address: '];
        yield 'localhost' => ['https://localhost/push', $key, $auth, 'aes128gcm', 'The endpoint of a push subscription is a web address: '];
        yield 'plain HTTP' => ['http://fcm.googleapis.com/x', $key, $auth, 'aes128gcm', 'The endpoint of a push subscription is an HTTPS address.'];
        yield 'an unknown encoding' => ['https://fcm.googleapis.com/x', $key, $auth, 'gzip', 'The content encoding of a push subscription is "aes128gcm" or "aesgcm", not "gzip".'];
        yield 'a key that is no Base64' => ['https://fcm.googleapis.com/x', 'a+b/', $auth, 'aes128gcm', 'The public key of a push subscription is written in URL-safe Base64.'];
        yield 'a short key' => ['https://fcm.googleapis.com/x', substr($key, 0, 84), $auth, 'aes128gcm', 'The public key of a push subscription is an uncompressed P-256 point of 65 bytes.'];
        yield 'a compressed key' => ['https://fcm.googleapis.com/x', 'A'.substr($key, 1), $auth, 'aes128gcm', 'The public key of a push subscription is an uncompressed P-256 point of 65 bytes.'];
        yield 'a secret that is no Base64' => ['https://fcm.googleapis.com/x', $key, '!!', 'aes128gcm', 'The authentication secret of a push subscription is written in URL-safe Base64.'];
        yield 'a long secret' => ['https://fcm.googleapis.com/x', $key, $auth.'AA', 'aes128gcm', 'The authentication secret of a push subscription is 16 bytes.'];
        yield 'a short secret' => ['https://fcm.googleapis.com/x', $key, substr($auth, 0, 20), 'aes128gcm', 'The authentication secret of a push subscription is 16 bytes.'];
    }

    /**
     * @param non-empty-string $message
     */
    #[DataProvider('invalid')]
    public function testWhatAPushServiceWouldNotTakeIsRefused(string $endpoint, string $key, string $auth, string $encoding, string $message): void
    {
        try {
            PushSubscription::of($endpoint, $key, $auth, $encoding);
            self::fail('Refused');
        } catch (InvalidPushSubscription $invalidPushSubscription) {
            self::assertInstanceOf(NotificationsException::class, $invalidPushSubscription);
            self::assertStringStartsWith($message, $invalidPushSubscription->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notJson(): iterable
    {
        yield 'no JSON' => ['nope'];
        yield 'a string' => ['"x"'];
        yield 'no endpoint' => ['{"keys":{"p256dh":"a","auth":"b"}}'];
        yield 'an endpoint that is no string' => ['{"endpoint":5,"keys":{"p256dh":"a","auth":"b"}}'];
        yield 'no keys' => ['{"endpoint":"https://fcm.googleapis.com/x"}'];
        yield 'keys that are no object' => ['{"endpoint":"https://fcm.googleapis.com/x","keys":"k"}'];
        yield 'no public key' => ['{"endpoint":"https://fcm.googleapis.com/x","keys":{"auth":"b"}}'];
        yield 'no secret' => ['{"endpoint":"https://fcm.googleapis.com/x","keys":{"p256dh":"a"}}'];
        yield 'a secret that is no string' => ['{"endpoint":"https://fcm.googleapis.com/x","keys":{"p256dh":"a","auth":1}}'];
        yield 'too deep' => ['{"endpoint":"https://fcm.googleapis.com/x","keys":{"p256dh":"a","auth":"b"},"x":[[[1]]]}'];
    }

    #[DataProvider('notJson')]
    public function testJsonThatIsNoSubscriptionIsRefused(string $json): void
    {
        $this->expectExceptionObject(InvalidPushSubscription::because('A push subscription is a JSON object with an "endpoint" and "keys" holding "p256dh" and "auth".'));

        PushSubscription::fromJson($json);
    }

    public function testAnEncodingThatIsNoStringIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The content encoding of a push subscription is "aes128gcm" or "aesgcm", not "".');

        PushSubscription::fromJson(substr(Browser::json(), 0, -1).',"contentEncoding":5}');
    }

    public function testJsonLongerThanASubscriptionIsRefused(): void
    {
        $json = Browser::json().str_repeat(' ', PushSubscription::MAX_JSON - \strlen(Browser::json()));
        self::assertSame(4096, \strlen($json));
        self::assertSame('fcm.googleapis.com', PushSubscription::fromJson($json)->host);

        $this->expectExceptionMessageIsOrContains('A push subscription is 4096 bytes of JSON at most.');

        PushSubscription::fromJson($json.' ');
    }

    public function testAnEndpointIsOnTheServicesNamed(): void
    {
        $subscription = Browser::subscription('https://wns2-par02p.notify.windows.com/w/?token=x');

        self::assertTrue($subscription->isOn([]));
        self::assertTrue($subscription->isOn(['fcm.googleapis.com', 'Notify.Windows.com']));
        self::assertTrue(Browser::subscription('https://notify.windows.com/w')->isOn(['notify.windows.com']));
        self::assertFalse($subscription->isOn(['fcm.googleapis.com']));
        self::assertFalse(Browser::subscription('https://evilnotify.windows.com.example/w')->isOn(['notify.windows.com']));
        self::assertFalse(Browser::subscription('https://xnotify.windows.com/w')->isOn(['notify.windows.com']));
    }

    public function testAnEndpointIsWrittenInItsCanonicalForm(): void
    {
        self::assertSame('https://fcm.googleapis.com/a%22b', PushSubscription::canonical(' https://FCM.googleapis.com:443/a"b'));
        self::assertNull(PushSubscription::canonical('https://127.0.0.1/'));
        self::assertSame(hash('sha256', 'x'), PushSubscription::idOf('x'));
    }
}
