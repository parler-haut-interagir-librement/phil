<?php

declare(strict_types=1);

/*
 * What a conferencing server answers, or what reaches the webhook, nobody chose: the first byte picks the reader —
 * the XML of a BigBlueButton answer, the attendance a BigBlueButton server posts, a Jitsi notification —, the rest is
 * the body. Whatever it holds, the reader ends in a result or in an exception of the library, never another error.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Url\Url;
use Phil\VirtualClassroom\BigBlueButton\BigBlueButton;
use Phil\VirtualClassroom\Exception\VirtualClassroomException;
use Phil\VirtualClassroom\Jitsi\Jitsi;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\Webhook;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$clock = new MockClock(new DateTimeImmutable('@1700000000'));
$secret = 'fuzz-secret-of-the-server-012345';
$body = '';
$client = new MockHttpClient(static function () use (&$body): MockResponse {
    return new MockResponse($body);
});
$bbb = new BigBlueButton($client, Url::fromString('https://bbb.example.com/bigbluebutton/api/'), $secret, $clock, signedAnalytics: false);
$jitsi = Jitsi::selfHosted(Url::fromString('https://meet.example.com'), 'app1', $secret, $clock, webhookSecret: $secret);
$room = RoomId::fromString('room1');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use (&$body, $bbb, $jitsi, $room, $secret): void {
    $mode = '' === $input ? 0 : ord($input[0]) % 4;
    $body = substr($input, 1);

    try {
        match ($mode) {
            0 => $bbb->recordings($room),
            1 => $bbb->presences($room),
            2 => $bbb->receive(new Webhook('POST', [], [], $body)),
            default => $jitsi->receive(new Webhook('POST', ['X-Jaas-Signature' => 't=1700000000,v1='.base64_encode(hash_hmac('sha256', '1700000000.'.$body, $secret, true))], [], $body)),
        };
    } catch (VirtualClassroomException) {
        // An answer or a notification refused: what the library promises.
    }
});
