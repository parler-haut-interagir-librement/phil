<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Unit\Application;

use Phil\VirtualClassroomBundle\Application\WebhookSigner;
use Phil\VirtualClassroomBundle\Domain\Exception\InvalidRoom;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookSigner::class)]
final class WebhookSignerTest extends TestCase
{
    public function testEachChannelOfEachRoomHasItsOwnProof(): void
    {
        $signer = new WebhookSigner('a-secret-of-sixteen-bytes');
        $token = $signer->token('main', 'room1', 'ended');
        $expected = substr(sodium_bin2base64(hash_hmac('sha256', "phil_virtual_classroom\0main\0room1\0ended", 'a-secret-of-sixteen-bytes', true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), 0, 32);

        self::assertSame($expected, $token);
        self::assertSame(32, \strlen($token));
        self::assertTrue($signer->verify($token, 'main', 'room1', 'ended'));
        self::assertFalse($signer->verify($token, 'main', 'room1', 'events'));
        self::assertFalse($signer->verify($token, 'main', 'room2', 'ended'));
        self::assertFalse($signer->verify($token, 'other', 'room1', 'ended'));
        self::assertFalse($signer->verify($token, 'main', null, 'ended'));
        self::assertFalse(new WebhookSigner('another-secret-of-16')->verify($token, 'main', 'room1', 'ended'));
        self::assertSame(substr(sodium_bin2base64(hash_hmac('sha256', "phil_virtual_classroom\0main\0\0account", 'a-secret-of-sixteen-bytes', true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), 0, 32), $signer->token('main', null, 'account'));
        self::assertTrue(new WebhookSigner(str_repeat('s', 16))->verify(new WebhookSigner(str_repeat('s', 16))->token('a', 'b', 'c'), 'a', 'b', 'c'));
    }

    public function testTheSecretIsSixteenBytesOrMore(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('The secret of the webhook addresses is 16 bytes or more.');

        new WebhookSigner(str_repeat('s', 15));
    }
}
