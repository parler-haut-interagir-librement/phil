<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Unit\Check;

use Phil\MailAuthCheck\Check\DkimCheck;
use Phil\MailAuthCheck\Dns\StaticTxtResolver;
use Phil\MailAuthCheck\Finding;
use Phil\MailAuthCheck\SenderProfile;
use Phil\MailAuthCheck\Tests\Support\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DkimCheck::class)]
final class DkimCheckTest extends TestCase
{
    public function testWithoutSelectorDmarcRestsOnSpf(): void
    {
        self::assertSame(
            [['warning', 'dkim.no-selector', 'No DKIM selector is expected: DMARC then rests on SPF alignment alone, which forwarding breaks.']],
            $this->dkim([], []),
        );
    }

    /**
     * @return iterable<string, array{list<string>, list<array{string, string, string}>}>
     */
    public static function keys(): iterable
    {
        $name = 's1._domainkey.example.org';

        yield 'a 2048-bit key' => [[Keys::dkimRsa(2048)], [['pass', 'dkim.ok', 'The DKIM key at '.$name.' is RSA of 2048 bits.']]];
        yield 'a 2047-bit key' => [[Keys::dkimRsa(2047)], [['warning', 'dkim.key-short', 'The DKIM key at '.$name.' is RSA of 2047 bits: move to 2048 bits.']]];
        yield 'a 1024-bit key' => [[Keys::dkimRsa(1024)], [['warning', 'dkim.key-short', 'The DKIM key at '.$name.' is RSA of 1024 bits: move to 2048 bits.']]];
        yield 'a 1023-bit key' => [[Keys::dkimRsa(1023)], [['fail', 'dkim.key-too-short', 'The DKIM key at '.$name.' is RSA of 1023 bits; receivers refuse keys under 1024 bits.']]];
        yield 'no key' => [[], [['fail', 'dkim.missing', 'No DKIM key is published at '.$name.'.']]];
        yield 'two records' => [[Keys::dkimRsa(2048), 'x'], [['fail', 'dkim.multiple', $name.' publishes more than one TXT record: keep the DKIM key alone.']]];
        yield 'invalid' => [['v=DKIM1; k=rsa'], [['fail', 'dkim.invalid', 'The DKIM key at '.$name.' cannot be read: the record has no public key ("p=").']]];
        yield 'revoked' => [['v=DKIM1; p='], [['fail', 'dkim.revoked', 'The DKIM key at '.$name.' is revoked (empty "p="): signatures with it no longer verify.']]];
        yield 'testing' => [[Keys::dkimRsa(2048).'; t=y'], [['warning', 'dkim.testing', 'The DKIM key at '.$name.' says it is being tested ("t=y"): receivers may treat its signatures as absent.']]];
        yield 'an Ed25519 key alone' => [[Keys::dkimEd25519()], [
            ['pass', 'dkim.ok', 'The DKIM key at '.$name.' is an Ed25519 key.'],
            ['warning', 'dkim.ed25519-only', 'Every expected DKIM key is Ed25519, which not every receiver verifies yet: sign with an RSA key as well.'],
        ]];
    }

    /**
     * @param list<string>                        $records
     * @param list<array{string, string, string}> $expected
     */
    #[DataProvider('keys')]
    public function testEachKeyGivesItsFinding(array $records, array $expected): void
    {
        self::assertSame($expected, $this->dkim(['s1'], [] === $records ? [] : ['s1._domainkey.example.org' => $records]));
    }

    public function testAnEd25519KeyBesideAnRsaKeyGivesNoWarning(): void
    {
        self::assertSame(
            [
                ['pass', 'dkim.ok', 'The DKIM key at ed._domainkey.example.org is an Ed25519 key.'],
                ['pass', 'dkim.ok', 'The DKIM key at rsa._domainkey.example.org is RSA of 4096 bits.'],
            ],
            $this->dkim(['ed', 'rsa'], [
                'ed._domainkey.example.org' => [Keys::dkimEd25519()],
                'rsa._domainkey.example.org' => [Keys::dkimRsa(4096)],
            ]),
        );
    }

    public function testTwoEd25519KeysAloneGiveTheWarning(): void
    {
        self::assertSame(
            ['dkim.ok', 'dkim.ok', 'dkim.ed25519-only'],
            array_column($this->dkim(['ed', 'ed2'], [
                'ed._domainkey.example.org' => [Keys::dkimEd25519()],
                'ed2._domainkey.example.org' => [Keys::dkimEd25519()],
            ]), 1),
        );
    }

    public function testAnRsaKeyBetweenEd25519KeysGivesNoWarning(): void
    {
        self::assertSame(
            ['dkim.ok', 'dkim.ok', 'dkim.ok'],
            array_column($this->dkim(['ed', 'rsa', 'ed2'], [
                'ed._domainkey.example.org' => [Keys::dkimEd25519()],
                'rsa._domainkey.example.org' => [Keys::dkimRsa(2048)],
                'ed2._domainkey.example.org' => [Keys::dkimEd25519()],
            ]), 1),
        );
    }

    public function testAnEd25519KeyBesideAMissingKeyGivesNoEd25519Warning(): void
    {
        self::assertSame(
            ['dkim.ok', 'dkim.missing'],
            array_column($this->dkim(['ed', 'other'], ['ed._domainkey.example.org' => [Keys::dkimEd25519()]]), 1),
        );
    }

    public function testAFailedLookupFails(): void
    {
        $findings = new DkimCheck()->run(new SenderProfile('example.org', dkimSelectors: ['s1']), new StaticTxtResolver([], ['s1._domainkey.example.org']));

        self::assertSame('dkim.lookup-failed', $findings[0]->code);
        self::assertSame('The DKIM key at s1._domainkey.example.org could not be read: the DNS lookup failed.', $findings[0]->message);
    }

    /**
     * @param list<string>                $selectors
     * @param array<string, list<string>> $records
     *
     * @return list<array{string, string, string}>
     */
    private function dkim(array $selectors, array $records): array
    {
        $findings = new DkimCheck()->run(new SenderProfile('example.org', dkimSelectors: $selectors), new StaticTxtResolver($records));

        foreach ($findings as $finding) {
            self::assertSame('dkim', $finding->check);
        }

        return array_map(static fn (Finding $f): array => [$f->status->value, $f->code, $f->message], $findings);
    }
}
