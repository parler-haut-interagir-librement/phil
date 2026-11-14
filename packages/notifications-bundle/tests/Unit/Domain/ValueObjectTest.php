<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Domain;

use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\ValueObject\NotificationData;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubjectReference::class)]
#[CoversClass(NotificationData::class)]
final class ValueObjectTest extends TestCase
{
    public function testASubjectIsAKindAndAnIdentifier(): void
    {
        $subject = new SubjectReference('comment', '981');

        self::assertSame('comment', $subject->type);
        self::assertSame('981', $subject->id);
        self::assertSame('comment:981', $subject->key());
    }

    public function testASubjectKindIsAKey(): void
    {
        $this->expectExceptionMessageIsOrContains('The subject type "Comment" is not a key');

        new SubjectReference('Comment', '1');
    }

    public function testASubjectIdentifierIsOne(): void
    {
        $this->expectExceptionMessageIsOrContains('The subject identifier "a b" is not an identifier');

        new SubjectReference('comment', 'a b');
    }

    public function testDataIsSortedByKey(): void
    {
        self::assertSame(['event' => 7, 'ticket' => 'T-7'], NotificationData::from(['ticket' => 'T-7', 'event' => 7])->values);
        self::assertSame([], NotificationData::none()->values);
    }

    public function testDataHoldsSixteenEntries(): void
    {
        $values = [];

        for ($i = 0; $i < 16; ++$i) {
            $values['k'.$i] = $i;
        }

        self::assertCount(16, NotificationData::from($values)->values);

        $values['k16'] = 16;
        $this->expectException(InvalidNotification::class);
        $this->expectExceptionMessageIsOrContains('The data of a notification holds 16 entries at most.');

        NotificationData::from($values);
    }

    public function testADataKeyIsAKey(): void
    {
        $this->expectExceptionMessageIsOrContains('The data key "0" is not a key');

        NotificationData::from([5]);
    }

    public function testADataStringIsAnIdentifier(): void
    {
        $this->expectExceptionMessageIsOrContains('The data value of "name" "Ada Lovelace" is not an identifier');

        NotificationData::from(['name' => 'Ada Lovelace']);
    }

    public function testADataValueIsAnIntegerOrAString(): void
    {
        $this->expectExceptionMessageIsOrContains('The data value of "price" is an integer or an identifier, not float.');

        NotificationData::from(['price' => 1.5]);
    }
}
