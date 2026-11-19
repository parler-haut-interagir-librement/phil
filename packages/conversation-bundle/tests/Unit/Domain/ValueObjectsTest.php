<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Domain;

use Phil\ConversationBundle\Domain\Exception\InvalidConversation;
use Phil\ConversationBundle\Domain\Exception\InvalidParticipant;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\MessageId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParticipantId::class)]
#[CoversClass(ConversationId::class)]
#[CoversClass(MessageId::class)]
#[CoversClass(InvalidParticipant::class)]
#[CoversClass(InvalidConversation::class)]
final class ValueObjectsTest extends TestCase
{
    public function testAParticipantIsAStableIdentifier(): void
    {
        $member = ParticipantId::fromString('member-42');

        self::assertSame('member-42', $member->value);
        self::assertSame('member-42', (string) $member);
        self::assertTrue($member->equals(ParticipantId::fromString('member-42')));
        self::assertFalse($member->equals(ParticipantId::fromString('member-43')));
        self::assertSame(str_repeat('é', 190), ParticipantId::fromString(str_repeat('é', 190))->value);
        self::assertSame('0', ParticipantId::fromString('0')->value);
    }

    public function testAnEmailAddressIsRefusedWithAClearMessage(): void
    {
        $this->expectException(InvalidParticipant::class);
        $this->expectExceptionMessageIs('A participant is named by a stable identifier of the application, never by an email address: an identifier with "@" is refused.');

        ParticipantId::fromString('ada@example.org');
    }

    #[DataProvider('malformed')]
    public function testAMalformedIdentifierIsRefused(string $value): void
    {
        $this->expectException(InvalidParticipant::class);
        $this->expectExceptionMessageIs('A participant identifier holds 1 to 190 characters, without spaces nor control characters.');

        ParticipantId::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 191)];
        yield 'a space' => ['member 42'];
        yield 'a newline at the end' => ["member\n"];
        yield 'a control character' => ["member\x07"];
        yield 'invalid UTF-8' => ["member\xff"];
    }

    public function testConversationsAndMessagesAreNamedByUuids(): void
    {
        $generated = ConversationId::generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $generated->value);
        self::assertNotSame($generated->value, ConversationId::generate()->value);
        self::assertSame('0192f0c1-5c5e-7a3b-9e1d-0123456789ab', ConversationId::fromString('0192F0C1-5C5E-7A3B-9E1D-0123456789AB')->value);
        self::assertSame('0192f0c1-5c5e-7a3b-9e1d-0123456789ab', (string) ConversationId::fromString('0192f0c1-5c5e-7a3b-9e1d-0123456789ab'));
        self::assertTrue($generated->equals(ConversationId::fromString($generated->value)));
        self::assertFalse($generated->equals(ConversationId::generate()));

        $message = MessageId::generate();
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $message->value);
        self::assertSame('0192f0c1-5c5e-7a3b-9e1d-0123456789ab', (string) MessageId::fromString('0192F0C1-5C5E-7A3B-9E1D-0123456789AB'));
    }

    public function testAConversationIdentifierThatIsNotAUuidIsRefused(): void
    {
        $this->expectException(InvalidConversation::class);
        $this->expectExceptionMessageIs('The identifier of a conversation is a UUID.');

        ConversationId::fromString('42');
    }

    public function testAMessageIdentifierThatIsNotAUuidIsRefused(): void
    {
        $this->expectException(InvalidConversation::class);
        $this->expectExceptionMessageIs('The identifier of a message is a UUID.');

        MessageId::fromString('0192f0c15c5e7a3b9e1d0123456789ab');
    }
}
