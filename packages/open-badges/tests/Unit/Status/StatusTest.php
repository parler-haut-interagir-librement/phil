<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Status;

use DateTimeImmutable;
use Phil\OpenBadges\Encoding\Multibase;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Status\StatusList;
use Phil\OpenBadges\Status\StatusListEntry;
use Phil\OpenBadges\Status\StatusListIssuer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatusList::class)]
#[CoversClass(StatusListEntry::class)]
#[CoversClass(StatusListIssuer::class)]
final class StatusTest extends TestCase
{
    private const string URL = 'https://example.org/status/1';

    public function testAListSetsItsBitsFromTheLeft(): void
    {
        $list = StatusList::of([0, 9, StatusList::MIN_SIZE - 1]);
        $bits = (string) gzdecode(Multibase::decode($list->encode()));

        self::assertSame(StatusList::MIN_SIZE, $list->size());
        self::assertSame("\x80\x40", substr($bits, 0, 2));
        self::assertSame("\x01", substr($bits, -1));
        self::assertSame(StatusList::MIN_SIZE / 8, \strlen($bits));
        self::assertTrue($list->isSet(0));
        self::assertFalse($list->isSet(1));
        self::assertTrue($list->isSet(9));
        self::assertFalse($list->isSet(8));
        self::assertTrue($list->isSet(StatusList::MIN_SIZE - 1));
        self::assertFalse(StatusList::of([7])->isSet(0));
        self::assertTrue(StatusList::of([7])->isSet(7));
        self::assertStringStartsWith('uH4sI', $list->encode());
    }

    public function testAListRoundTrips(): void
    {
        $list = StatusList::decode(StatusList::of([3, 70_000], StatusList::MIN_SIZE + 8)->encode());

        self::assertSame(StatusList::MIN_SIZE + 8, $list->size());
        self::assertTrue($list->isSet(3));
        self::assertTrue($list->isSet(70_000));
        self::assertFalse($list->isSet(4));
        self::assertSame(StatusList::MAX_SIZE, StatusList::of([StatusList::MAX_SIZE - 1], StatusList::MAX_SIZE)->size());
    }

    public function testAListHasItsSize(): void
    {
        foreach ([StatusList::MIN_SIZE - 8, StatusList::MAX_SIZE + 8, StatusList::MIN_SIZE + 1] as $size) {
            try {
                StatusList::of([], $size);
                self::fail(\sprintf('A list of %d bits was expected to be refused.', $size));
            } catch (InvalidBadge $refused) {
                self::assertSame('A status list holds from 131072 to 16777216 bits, a multiple of 8.', $refused->getMessage());
            }
        }
    }

    public function testAnIndexIsInsideTheList(): void
    {
        foreach ([-1, StatusList::MIN_SIZE] as $index) {
            try {
                StatusList::of([$index]);
                self::fail('The index was expected to be refused.');
            } catch (InvalidBadge $refused) {
                self::assertSame(\sprintf('The index %d is outside the status list of 131072 bits.', $index), $refused->getMessage());
            }

            try {
                StatusList::of()->isSet($index);
                self::fail('The index was expected to be refused.');
            } catch (InvalidCredential $refused) {
                self::assertSame(\sprintf('The index %d is outside the status list of 131072 bits.', $index), $refused->getMessage());
            }
        }
    }

    public function testAnEncodedListIsChecked(): void
    {
        foreach (['z'.substr(StatusList::of()->encode(), 1), 'u'.str_repeat('A', StatusList::MAX_SIZE)] as $encoded) {
            try {
                StatusList::decode($encoded);
                self::fail('The list was expected to be refused.');
            } catch (InvalidCredential $refused) {
                self::assertSame('An encoded status list is in multibase base 64 URL, starting with "u".', $refused->getMessage());
            }
        }

        foreach ([Multibase::base64Url('not gzip'), Multibase::base64Url((string) gzencode(str_repeat("\0", StatusList::MIN_SIZE / 8 - 1))), Multibase::base64Url((string) gzencode(str_repeat("\0", StatusList::MAX_SIZE / 8 + 1)))] as $encoded) {
            try {
                StatusList::decode($encoded);
                self::fail('The list was expected to be refused.');
            } catch (InvalidCredential $refused) {
                self::assertSame('An encoded status list is GZIP-compressed and holds from 131072 to 16777216 bits.', $refused->getMessage());
            }
        }

        self::assertSame(StatusList::MAX_SIZE, StatusList::decode(Multibase::base64Url((string) gzencode(str_repeat("\0", StatusList::MAX_SIZE / 8))))->size());

        try {
            StatusList::decode('u'.str_repeat('A', StatusList::MAX_SIZE - 1));
            self::fail('The list was expected to be refused.');
        } catch (InvalidCredential $invalidCredential) {
            self::assertSame('An encoded status list is GZIP-compressed and holds from 131072 to 16777216 bits.', $invalidCredential->getMessage());
        }
    }

    public function testAnEntry(): void
    {
        $entry = new StatusListEntry(self::URL, 42, 'suspension');

        self::assertSame(['id' => self::URL.'#42', 'type' => 'BitstringStatusListEntry', 'statusPurpose' => 'suspension', 'statusListIndex' => '42', 'statusListCredential' => self::URL], $entry->toArray());
        self::assertEquals($entry, StatusListEntry::fromArray($entry->toArray()));
        self::assertSame('revocation', new StatusListEntry(self::URL, 0)->purpose);
        self::assertSame(StatusList::MAX_SIZE - 1, new StatusListEntry(self::URL, StatusList::MAX_SIZE - 1)->index);
    }

    public function testAnEntryIsChecked(): void
    {
        try {
            new StatusListEntry(self::URL, StatusList::MAX_SIZE);
            self::fail('The index was expected to be refused.');
        } catch (InvalidBadge $invalidBadge) {
            self::assertSame('The index in a status list is from 0 to 16777215.', $invalidBadge->getMessage());
        }

        foreach ([[self::URL, -1, 'revocation'], [self::URL, StatusList::MAX_SIZE, 'revocation'], [self::URL, 1, 'refresh'], ['status/1', 1, 'revocation']] as [$url, $index, $purpose]) {
            try {
                new StatusListEntry($url, $index, $purpose);
                self::fail('The entry was expected to be refused.');
            } catch (InvalidBadge $refused) {
                self::assertNotSame('', $refused->getMessage());
            }
        }
    }

    public function testAnEntryIsReadStrictly(): void
    {
        $entry = new StatusListEntry(self::URL, 42)->toArray();

        foreach ([['type' => 'StatusList2021Entry'], ['statusListCredential' => 3], ['statusListIndex' => 42], ['statusListIndex' => '042'], ['statusListIndex' => '-1'], ['statusListIndex' => '4 2'], ['statusListIndex' => '1234567890'], ['statusListIndex' => "12\n"], ['statusPurpose' => null]] as $change) {
            try {
                StatusListEntry::fromArray($change + $entry);
                self::fail('The entry was expected to be refused.');
            } catch (InvalidCredential $refused) {
                self::assertSame('The status of the credential is not a BitstringStatusListEntry.', $refused->getMessage());
            }
        }

        self::assertSame(0, StatusListEntry::fromArray(['statusListIndex' => '0'] + $entry)->index);
        self::assertSame(12_345_678, StatusListEntry::fromArray(['statusListIndex' => '12345678'] + $entry)->index);

        $this->expectExceptionObject(InvalidCredential::because('The purpose of a status is "revocation" or "suspension"; "refresh" is not.'));
        StatusListEntry::fromArray(['statusPurpose' => 'refresh'] + $entry);
    }

    public function testAStatusListCredential(): void
    {
        $issuer = new StatusListIssuer(new Issuer('https://example.org/issuer', 'Example'));
        $list = StatusList::of([42]);
        $credential = $issuer->credential(self::URL, $list, 'revocation', new DateTimeImmutable('2026-11-07 UTC'), new DateTimeImmutable('2026-11-08 UTC'));

        self::assertSame([
            '@context' => ['https://www.w3.org/ns/credentials/v2'],
            'id' => self::URL,
            'type' => ['VerifiableCredential', 'BitstringStatusListCredential'],
            'issuer' => 'https://example.org/issuer',
            'validFrom' => '2026-11-07T00:00:00Z',
            'validUntil' => '2026-11-08T00:00:00Z',
            'credentialSubject' => ['id' => self::URL.'#list', 'type' => 'BitstringStatusList', 'statusPurpose' => 'revocation', 'encodedList' => $list->encode()],
        ], $credential);

        self::assertTrue($issuer->applies(new StatusListEntry(self::URL, 42), $credential));
        self::assertFalse($issuer->applies(new StatusListEntry(self::URL, 41), $credential));
        self::assertTrue($issuer->applies(new StatusListEntry(self::URL, 42), ['issuer' => ['id' => 'https://example.org/issuer']] + $credential));
    }

    public function testAStatusListCredentialIsChecked(): void
    {
        $issuer = new StatusListIssuer(new Issuer('https://example.org/issuer', 'Example'));

        try {
            $issuer->credential('status', StatusList::of(), 'revocation', new DateTimeImmutable());
            self::fail('A relative URL was expected to be refused.');
        } catch (InvalidBadge $invalidBadge) {
            self::assertSame('The identifier of the status list is an absolute HTTP URL; "status" is not.', $invalidBadge->getMessage());
        }

        $this->expectExceptionObject(InvalidBadge::because('The purpose of a status is "revocation" or "suspension"; "refresh" is not.'));
        $issuer->credential(self::URL, StatusList::of(), 'refresh', new DateTimeImmutable());
    }

    public function testAStatusIsReadOnlyFromItsOwnList(): void
    {
        $issuer = new StatusListIssuer(new Issuer('https://example.org/issuer', 'Example'));
        $credential = $issuer->credential(self::URL, StatusList::of([1]), 'revocation', new DateTimeImmutable());
        $entry = new StatusListEntry(self::URL, 1);

        foreach ([['id' => 'https://example.org/status/2'], ['issuer' => 'https://evil.example/'], ['issuer' => ['name' => 'x']]] as $change) {
            try {
                $issuer->applies($entry, $change + $credential);
                self::fail('The list was expected to be refused.');
            } catch (InvalidCredential $refused) {
                self::assertSame("The status list is not the one of the credential, or not its issuer's.", $refused->getMessage());
            }
        }

        $subject = $credential['credentialSubject'];
        self::assertIsArray($subject);

        foreach ([['credentialSubject' => 'x'], ['credentialSubject' => ['statusPurpose' => 'suspension'] + $subject], ['credentialSubject' => ['encodedList' => 3] + $subject]] as $change) {
            try {
                $issuer->applies($entry, $change + $credential);
                self::fail('The list was expected to be refused.');
            } catch (InvalidCredential $refused) {
                self::assertSame('The status list does not hold the statuses of "revocation".', $refused->getMessage());
            }
        }
    }
}
