<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Domain;

use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\ValueObject\AgentUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AgentUrl::class)]
final class AgentUrlTest extends TestCase
{
    public function testAnAddressIsNormalisedAndGivesEachEndpoint(): void
    {
        $url = AgentUrl::fromString('HTTPS://Shop.Example.com:443/_sites-console/');

        self::assertSame('https://shop.example.com/_sites-console', $url->value);
        self::assertSame('https://shop.example.com/_sites-console/status', $url->endpoint('status'));
        self::assertSame('https://shop.example.com/update', AgentUrl::fromString('https://shop.example.com')->endpoint('update'));
    }

    public function testInsecureAddressesOnlyWhenAllowed(): void
    {
        self::assertSame('http://localhost:8000/agent', AgentUrl::fromString('http://localhost:8000/agent', true)->value);
    }

    #[DataProvider('refused')]
    public function testRefusedAddresses(string $text, string $reason): void
    {
        $this->expectExceptionObject(InvalidValue::because($reason));
        AgentUrl::fromString($text);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'http' => ['http://shop.example.com/agent', 'The address of an agent is an https address.'];
        yield 'localhost' => ['https://localhost/agent', 'The address of an agent is an absolute http or https address on a host name.'];
        yield 'relative' => ['/agent', 'The address of an agent is an absolute http or https address on a host name.'];
        yield 'query' => ['https://shop.example.com/agent?x=1', 'The address of an agent has no query and no fragment.'];
        yield 'fragment' => ['https://shop.example.com/agent#x', 'The address of an agent has no query and no fragment.'];
    }

    public function testAnUnknownEndpointIsRefused(): void
    {
        $this->expectExceptionObject(InvalidValue::because('The action of a request is one of status, configuration, update, keys.'));
        AgentUrl::fromString('https://shop.example.com')->endpoint('delete');
    }
}
