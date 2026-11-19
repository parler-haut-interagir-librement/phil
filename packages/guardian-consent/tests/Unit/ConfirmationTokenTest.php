<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit;

use Phil\GuardianConsent\ConfirmationToken;
use Phil\GuardianConsent\Exception\InvalidToken;
use Phil\GuardianConsent\Internal\RandomId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

#[CoversClass(ConfirmationToken::class)]
#[CoversClass(RandomId::class)]
#[CoversClass(InvalidToken::class)]
final class ConfirmationTokenTest extends TestCase
{
    public function testAGeneratedTokenIsReadBack(): void
    {
        $token = ConfirmationToken::generate();

        self::assertSame(22, \strlen($token->requestId));
        self::assertSame(43, \strlen($token->verifier));
        self::assertSame(ConfirmationToken::LENGTH, \strlen($token->toString()));
        self::assertSame($token->requestId.'.'.$token->verifier, (string) $token);

        $read = ConfirmationToken::parse($token->toString());

        self::assertSame($token->requestId, $read->requestId);
        self::assertSame($token->verifier, $read->verifier);
        self::assertTrue(ConfirmationToken::isRequestId($token->requestId));
    }

    public function testTokensDiffer(): void
    {
        self::assertNotSame(ConfirmationToken::generate()->toString(), ConfirmationToken::generate()->toString());
    }

    public function testATokenComesFromTheRandomizer(): void
    {
        $first = ConfirmationToken::generate(new Randomizer(new Xoshiro256StarStar(7)));
        $second = ConfirmationToken::generate(new Randomizer(new Xoshiro256StarStar(7)));

        self::assertSame($first->toString(), $second->toString());
        self::assertNotSame($first->requestId, substr($first->verifier, 0, 22));
    }

    public function testEveryGeneratedTokenIsValid(): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar(42));

        for ($index = 0; $index < 200; ++$index) {
            $token = ConfirmationToken::generate($randomizer);
            self::assertSame($token->toString(), ConfirmationToken::parse($token->toString())->toString());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTokens(): iterable
    {
        $id = 'AAAAAAAAAAAAAAAAAAAAAA';
        $verifier = str_repeat('A', 43);

        yield 'empty' => [''];
        yield 'no dot' => [$id.$verifier];
        yield 'two dots' => [$id.'.'.$verifier.'.'];
        yield 'short identifier' => [substr($id, 1).'.'.$verifier];
        yield 'long identifier' => [$id.'A.'.$verifier];
        yield 'short verifier' => [$id.'.'.substr($verifier, 1)];
        yield 'long verifier' => [$id.'.'.$verifier.'A'];
        yield 'bits past the identifier' => [substr($id, 0, 21).'B.'.$verifier];
        yield 'bits past the verifier' => [$id.'.'.substr($verifier, 0, 42).'B'];
        yield 'standard alphabet' => ['+AAAAAAAAAAAAAAAAAAAAA.'.$verifier];
        yield 'padding' => [$id.'.'.substr($verifier, 0, 42).'='];
        yield 'trailing newline' => [$id.'.'.$verifier."\n"];
    }

    #[DataProvider('invalidTokens')]
    public function testATextThatIsNoTokenIsRejected(string $text): void
    {
        $this->expectException(InvalidToken::class);
        $this->expectExceptionMessageIsOrContains('The confirmation link is not valid.');

        ConfirmationToken::parse($text);
    }

    public function testTheLastCharacterOfEachPartCarriesOnlyItsBits(): void
    {
        foreach (['A', 'Q', 'g', 'w'] as $last) {
            self::assertTrue(RandomId::isValid(str_repeat('-', 21).$last, 16));
        }

        foreach (['A', 'E', 'I', 'M', 'Q', 'U', 'Y', 'c', 'g', 'k', 'o', 's', 'w', '0', '4', '8'] as $last) {
            self::assertTrue(RandomId::isValid(str_repeat('_', 42).$last, 32));
        }

        self::assertFalse(RandomId::isValid(str_repeat('A', 21).'E', 16));
        self::assertFalse(RandomId::isValid(str_repeat('A', 42).'B', 32));
        self::assertFalse(RandomId::isValid(str_repeat('A', 43), 16));
        self::assertFalse(RandomId::isValid(str_repeat('A', 22), 32));
    }
}
