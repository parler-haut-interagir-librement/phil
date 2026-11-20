<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Domain;

use LogicException;
use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;
use Phil\ApiKeysBundle\Domain\Exception\MalformedToken;
use Phil\ApiKeysBundle\Domain\Token\Base62;
use Phil\ApiKeysBundle\Domain\Token\PresentedToken;
use Phil\ApiKeysBundle\Domain\Token\TokenFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenFormat::class)]
#[CoversClass(Base62::class)]
#[CoversClass(PresentedToken::class)]
#[CoversClass(MalformedToken::class)]
#[CoversClass(InvalidTokenValue::class)]
final class TokenFormatTest extends TestCase
{
    public function testATokenIsThePrefixTheIdTheSecretAndTheChecksum(): void
    {
        $format = new TokenFormat('acme');
        $text = $format->format('AAAAAAAAAAAA', str_repeat('b', 32));

        self::assertSame('acme_AAAAAAAAAAAA_'.str_repeat('b', 32).Base62::fixed(crc32('acme_AAAAAAAAAAAA_'.str_repeat('b', 32)) & 0xFFFFFFFF, 6), $text);
        self::assertSame(56, \strlen($text));
        self::assertSame(56, $format->length());
        self::assertSame(60, new TokenFormat('acme1234')->length());

        $presented = $format->parse($text);
        self::assertSame('AAAAAAAAAAAA', $presented->id);
        self::assertSame(str_repeat('b', 32), $presented->secret);
    }

    public function testTheChecksumIsSixDigitsOfBase62(): void
    {
        self::assertSame('000000', Base62::fixed(0, 6));
        self::assertSame('00000z', Base62::fixed(61, 6));
        self::assertSame('000010', Base62::fixed(62, 6));
        self::assertSame('4gfFC3', Base62::fixed(4_294_967_295, 6));
        self::assertSame('0', Base62::fixed(62, 1));
    }

    public function testNewIdsAndSecretsAreRandomCharactersOfTheWholeAlphabet(): void
    {
        $format = new TokenFormat('acme');
        $seen = '';

        for ($i = 0; $i < 300; ++$i) {
            $id = $format->newId();
            $secret = $format->newSecret();
            self::assertSame(12, \strlen($id));
            self::assertSame(32, \strlen($secret));
            self::assertTrue(Base62::isEncoded($id.$secret));
            $seen .= $id.$secret;
        }

        // 13,200 characters, about 213 of each: every one of the 62 is drawn, none twice as often as another.
        $counts = count_chars($seen, 1);
        self::assertSame(Base62::ALPHABET, count_chars($seen, 3));

        foreach ($counts as $count) {
            self::assertGreaterThan(120, $count);
            self::assertLessThan(320, $count);
        }

        self::assertNotSame($format->newSecret(), $format->newSecret());
    }

    public function testEveryGeneratedTokenReadsBack(): void
    {
        $format = new TokenFormat('ab');

        for ($i = 0; $i < 50; ++$i) {
            $id = $format->newId();
            $secret = $format->newSecret();
            $presented = $format->parse($format->format($id, $secret));
            self::assertSame([$id, $secret], [$presented->id, $presented->secret]);
        }
    }

    public function testAnEncodedTextHoldsOnlyLettersAndDigits(): void
    {
        self::assertTrue(Base62::isEncoded(''));
        self::assertTrue(Base62::isEncoded('aZ09'));
        self::assertFalse(Base62::isEncoded('aZ_09'));
        self::assertFalse(Base62::isEncoded('é'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformed(): iterable
    {
        $format = new TokenFormat('acme');
        $valid = $format->format('AAAAAAAAAAAA', str_repeat('b', 32));

        yield 'empty' => ['', 'it does not have the length of a token'];
        yield 'cut short' => [substr($valid, 0, -1), 'it does not have the length of a token'];
        yield 'one more' => [$valid.'a', 'it does not have the length of a token'];
        yield 'another prefix' => ['acmf'.substr($valid, 4), 'it does not have the prefix and separators of a token'];
        yield 'no first separator' => ['acme-'.substr($valid, 5), 'it does not have the prefix and separators of a token'];
        yield 'no second separator' => [substr($valid, 0, 17).'-'.substr($valid, 18), 'it does not have the prefix and separators of a token'];
        yield 'a bad character in the id' => [substr($valid, 0, 5).'!'.substr($valid, 6), 'it holds a character a token does not'];
        yield 'a bad character in the secret' => [substr($valid, 0, 18).'!'.substr($valid, 19), 'it holds a character a token does not'];
        yield 'a bad character in the checksum' => [substr($valid, 0, -1).'!', 'it holds a character a token does not'];
        yield 'an underscore in the secret' => [substr($valid, 0, 49).'_'.substr($valid, 50), 'it holds a character a token does not'];
        yield 'a typo in the secret' => [substr($valid, 0, 18).'c'.substr($valid, 19), 'its checksum does not match'];
        yield 'a typo in the id' => ['acme_B'.substr($valid, 6), 'its checksum does not match'];
        yield 'a typo in the checksum' => [substr($valid, 0, -1).('0' === $valid[55] ? '1' : '0'), 'its checksum does not match'];
    }

    #[DataProvider('malformed')]
    public function testAMalformedTokenIsRefusedWithoutBeingRepeated(string $text, string $reason): void
    {
        try {
            new TokenFormat('acme')->parse($text);
            self::fail('The text was read as a token.');
        } catch (MalformedToken $malformedToken) {
            self::assertSame('The text is not a token: '.$reason.'.', $malformedToken->getMessage());
        }
    }

    public function testThePrefixIsTwoToSixteenLowercaseLettersAndDigits(): void
    {
        foreach (['ab', 'a1', 'abcdefghijklmnop', 'pat'] as $prefix) {
            self::assertSame($prefix, new TokenFormat($prefix)->prefix);
        }

        foreach (['a', '1ab', 'Ab', 'a_b', 'abcdefghijklmnopq', '', "ab\n"] as $prefix) {
            try {
                new TokenFormat($prefix);
                self::fail(\sprintf('The prefix "%s" was accepted.', $prefix));
            } catch (InvalidTokenValue $invalidTokenValue) {
                self::assertSame(\sprintf('A token prefix is 2 to 16 lowercase letters and digits, starting with a letter; "%s" is not.', $prefix), $invalidTokenValue->getMessage());
            }
        }
    }

    public function testAPresentedTokenHidesItsSecret(): void
    {
        $presented = new PresentedToken('AAAAAAAAAAAA', 'the-secret');

        self::assertSame('AAAAAAAAAAAA', (string) $presented);
        self::assertSame(['id' => 'AAAAAAAAAAAA', 'secret' => '[redacted]'], $presented->__debugInfo());
        self::assertStringNotContainsString('the-secret', print_r($presented, true));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('A presented token is not serialized: it holds a secret.');

        serialize($presented);
    }
}
