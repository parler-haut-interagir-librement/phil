<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Tests\Unit;

use Phil\ParticipantSafetyInfo\Exception\InvalidSafetyInfo;
use Phil\ParticipantSafetyInfo\Exception\UnreadableSafetyInfo;
use Phil\ParticipantSafetyInfo\SafetyInfo;
use Phil\ParticipantSafetyInfo\SafetyInfoCodec;
use Phil\ParticipantSafetyInfo\Tests\Support\Info;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SafetyInfoCodec::class)]
#[CoversClass(UnreadableSafetyInfo::class)]
final class SafetyInfoCodecTest extends TestCase
{
    public function testItEncodesVersion1WithItsFiveKeys(): void
    {
        self::assertSame(
            '{"v":1,"contacts":[{"name":"Alex Tremblay","phone":"+1 514 555-0199","relation":"sibling"}],"allergies":"Peanuts: epinephrine pen in the left pocket.","needs":"Wheelchair access to the main hall.","notes":"Prefers to be called Sam."}',
            SafetyInfoCodec::encode(Info::sample()),
        );
        self::assertSame('{"v":1,"contacts":[],"allergies":"é/à","needs":"","notes":""}', SafetyInfoCodec::encode(new SafetyInfo([], 'é/à')));
    }

    public function testWhatItEncodesDecodesTheSame(): void
    {
        $decoded = SafetyInfoCodec::decode(SafetyInfoCodec::encode(Info::sample()));

        self::assertEquals(Info::sample(), $decoded);
        self::assertSame('sibling', $decoded->contacts[0]->relation);
    }

    public function testThreeContactsDecode(): void
    {
        $contact = '{"name":"A","phone":"123","relation":""}';
        $info = SafetyInfoCodec::decode('{"v":1,"contacts":['.$contact.','.$contact.','.$contact.'],"allergies":"","needs":"","notes":""}');

        self::assertCount(SafetyInfo::MAX_CONTACTS, $info->contacts);
    }

    public function testKeysInAnotherOrderDecode(): void
    {
        $info = SafetyInfoCodec::decode('{"notes":"","needs":"","allergies":"a","contacts":[{"relation":"","phone":"123","name":"A"}],"v":1}');

        self::assertSame('a', $info->allergies);
        self::assertSame('A', $info->contacts[0]->name);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadable(): iterable
    {
        $object = 'Safety information is not an object of version 1 with its five keys.';
        $contact = 'An emergency contact is an object of three strings: name, phone and relation.';
        $fields = 'The fields of safety information are strings.';
        $contacts = 'The contacts of safety information are a list of 3 at most.';

        yield 'not JSON' => ['{', 'Safety information is not valid JSON.'];
        yield 'too deep' => ['{"v":1,"contacts":[{"name":["x"],"phone":"123","relation":""}],"allergies":"","needs":"","notes":""}', 'Safety information is not valid JSON.'];
        yield 'a string' => ['"text"', $object];
        yield 'a list' => ['[1,2,3,4,5]', $object];
        yield 'version 2' => ['{"v":2,"contacts":[],"allergies":"a","needs":"","notes":""}', $object];
        yield 'version "1"' => ['{"v":"1","contacts":[],"allergies":"a","needs":"","notes":""}', $object];
        yield 'a key missing' => ['{"v":1,"contacts":[],"allergies":"a","needs":""}', $object];
        yield 'a key more' => ['{"v":1,"contacts":[],"allergies":"a","needs":"","notes":"","diagnosis":"x"}', $object];
        yield 'contacts as an object' => ['{"v":1,"contacts":{"a":1},"allergies":"a","needs":"","notes":""}', $contacts];
        yield 'contacts as a string' => ['{"v":1,"contacts":"x","allergies":"a","needs":"","notes":""}', $contacts];
        yield 'four contacts' => ['{"v":1,"contacts":[1,2,3,4],"allergies":"a","needs":"","notes":""}', $contacts];
        yield 'allergies as a number' => ['{"v":1,"contacts":[],"allergies":1,"needs":"","notes":""}', $fields];
        yield 'needs as null' => ['{"v":1,"contacts":[],"allergies":"a","needs":null,"notes":""}', $fields];
        yield 'notes as true' => ['{"v":1,"contacts":[],"allergies":"a","needs":"","notes":true}', $fields];
        yield 'a contact as a number' => ['{"v":1,"contacts":[1],"allergies":"","needs":"","notes":""}', $contact];
        yield 'a contact without a relation' => ['{"v":1,"contacts":[{"name":"A","phone":"123"}],"allergies":"","needs":"","notes":""}', $contact];
        yield 'a contact name as a number' => ['{"v":1,"contacts":[{"name":1,"phone":"123","relation":""}],"allergies":"","needs":"","notes":""}', $contact];
        yield 'a contact phone as a number' => ['{"v":1,"contacts":[{"name":"A","phone":123,"relation":""}],"allergies":"","needs":"","notes":""}', $contact];
        yield 'a contact relation as null' => ['{"v":1,"contacts":[{"name":"A","phone":"123","relation":null}],"allergies":"","needs":"","notes":""}', $contact];
        yield 'empty' => ['{"v":1,"contacts":[],"allergies":"","needs":"","notes":""}', 'Safety information breaks a rule of its fields.'];
        yield 'a bad phone' => ['{"v":1,"contacts":[{"name":"A","phone":"x","relation":""}],"allergies":"","needs":"","notes":""}', 'Safety information breaks a rule of its fields.'];
    }

    #[DataProvider('unreadable')]
    public function testAnythingElseIsUnreadable(string $json, string $message): void
    {
        $this->expectException(UnreadableSafetyInfo::class);
        $this->expectExceptionMessageIsOrContains($message);

        SafetyInfoCodec::decode($json);
    }

    public function testARuleBrokenKeepsItsReason(): void
    {
        try {
            SafetyInfoCodec::decode('{"v":1,"contacts":[],"allergies":"","needs":"","notes":""}');
            self::fail('The empty information decoded.');
        } catch (UnreadableSafetyInfo $unreadableSafetyInfo) {
            self::assertInstanceOf(InvalidSafetyInfo::class, $unreadableSafetyInfo->getPrevious());
            self::assertSame(0, $unreadableSafetyInfo->getCode());
        }
    }

    public function testItReads32KibAtMost(): void
    {
        $padded = '{"v":1,"contacts":[],"allergies":"a","needs":"","notes":""}';
        $exact = $padded.str_repeat(' ', SafetyInfoCodec::MAX_BYTES - \strlen($padded));

        self::assertSame('a', SafetyInfoCodec::decode($exact)->allergies);

        $this->expectException(UnreadableSafetyInfo::class);
        $this->expectExceptionMessageIsOrContains('Safety information holds 32768 bytes at most.');

        SafetyInfoCodec::decode($exact.' ');
    }
}
