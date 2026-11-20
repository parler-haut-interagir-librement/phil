<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Infrastructure;

use InvalidArgumentException;
use Phil\CommerceLogisticsBundle\Infrastructure\CanadaPost\CanadaPostCredentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionParameter;
use SensitiveParameter;

#[CoversClass(CanadaPostCredentials::class)]
final class CanadaPostCredentialsTest extends TestCase
{
    public function testTheCredentialsOfAShop(): void
    {
        $credentials = new CanadaPostCredentials('key', 'secret', '1234567', 'https://api.test.example:8443/rs', 'https://auth.test.example/token', '0042708517', 'fr-CA');

        self::assertSame(['key', 'secret', '1234567', 'https://api.test.example:8443/rs', 'https://auth.test.example/token', '0042708517', 'fr-CA'], [
            $credentials->apiKey, $credentials->apiSecret, $credentials->customerNumber, $credentials->baseUri, $credentials->tokenUri, $credentials->contractId, $credentials->language,
        ]);
        self::assertSame([null, 'en-CA'], [new CanadaPostCredentials('k', 's', '1', 'https://a.example', 'https://a.example')->contractId, new CanadaPostCredentials('k', 's', '1', 'https://a.example', 'https://a.example')->language]);
        self::assertSame('HTTPS://API.TEST.EXAMPLE/rs', new CanadaPostCredentials('k', 's', '1', 'HTTPS://API.TEST.EXAMPLE/rs', 'https://a.example')->baseUri);
        self::assertCount(1, new ReflectionParameter([CanadaPostCredentials::class, '__construct'], 'apiSecret')->getAttributes(SensitiveParameter::class));
    }

    /**
     * @return iterable<string, array{string, string, string, string, string, ?string, string, string}>
     */
    public static function refused(): iterable
    {
        $keys = 'Canada Post is asked with the key and the secret of a developer application.';
        $numbers = 'A Canada Post customer number, and a contract when given, are 1 to 10 digits.';
        $https = 'Canada Post is reached over HTTPS only: the API and its tokens have https:// addresses.';

        yield 'no key' => ['', 's', '1', 'https://a.example', 'https://a.example', null, 'en-CA', $keys];
        yield 'no secret' => ['k', '', '1', 'https://a.example', 'https://a.example', null, 'en-CA', $keys];
        yield 'a customer number with letters' => ['k', 's', '12a', 'https://a.example', 'https://a.example', null, 'en-CA', $numbers];
        yield 'a customer number too long' => ['k', 's', '12345678901', 'https://a.example', 'https://a.example', null, 'en-CA', $numbers];
        yield 'a customer number ending in a line break' => ['k', 's', "1\n", 'https://a.example', 'https://a.example', null, 'en-CA', $numbers];
        yield 'an empty contract' => ['k', 's', '1', 'https://a.example', 'https://a.example', '', 'en-CA', $numbers];
        yield 'a contract starting with a letter' => ['k', 's', '1', 'https://a.example', 'https://a.example', 'a1', 'en-CA', $numbers];
        yield 'a contract ending with a letter' => ['k', 's', '1', 'https://a.example', 'https://a.example', '1a', 'en-CA', $numbers];
        yield 'a contract ending in a line break' => ['k', 's', '1', 'https://a.example', 'https://a.example', "1\n", 'en-CA', $numbers];
        yield 'an API over HTTP' => ['k', 's', '1', 'http://a.example', 'https://a.example', null, 'en-CA', $https];
        yield 'tokens over HTTP' => ['k', 's', '1', 'https://a.example', 'http://a.example', null, 'en-CA', $https];
        yield 'an address with a space' => ['k', 's', '1', 'https://a.example/a b', 'https://a.example', null, 'en-CA', $https];
        yield 'an address after another word' => ['k', 's', '1', 'see https://a.example', 'https://a.example', null, 'en-CA', $https];
        yield 'an address ending in a line break' => ['k', 's', '1', "https://a.example/rs\n", 'https://a.example', null, 'en-CA', $https];
        yield 'another language' => ['k', 's', '1', 'https://a.example', 'https://a.example', null, 'de-DE', 'Canada Post answers in en-CA or fr-CA.'];
    }

    #[DataProvider('refused')]
    public function testItRefusesWhatCannotSignIn(string $key, string $secret, string $customer, string $base, string $token, ?string $contract, string $language, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new CanadaPostCredentials($key, $secret, $customer, $base, $token, $contract, $language);
    }
}
