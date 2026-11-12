<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\Dimension;

use Phil\ContentBundle\Domain\Dimension\DimensionLocales;
use Phil\ContentBundle\Domain\Exception\InvalidDimension;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DimensionLocales::class)]
#[CoversClass(InvalidDimension::class)]
final class DimensionLocalesTest extends TestCase
{
    public function testALocaleIsParsed(): void
    {
        self::assertSame('fr_CA', DimensionLocales::parse('fr_CA')->value);
    }

    public function testAMalformedLocaleIsADomainError(): void
    {
        try {
            DimensionLocales::parse('fr-CA');
            self::fail('A malformed locale is refused.');
        } catch (InvalidDimension $invalidDimension) {
            self::assertInstanceOf(DomainError::class, $invalidDimension);
            self::assertInstanceOf(InvalidLocaleDimension::class, $invalidDimension->getPrevious());
            self::assertSame('"fr-CA" is not a locale of the form "fr" or "fr_CA".', $invalidDimension->getMessage());
        }
    }

    public function testTheChainOfAServedLocale(): void
    {
        $locales = DeclaredLocales::fromArray(['fr_CA' => ['fr'], 'fr' => []]);

        self::assertSame('fr_CA → fr', DimensionLocales::chain($locales, Locale::fromString('fr_CA'))->toString());
    }

    public function testALocaleThatIsNotServedIsADomainError(): void
    {
        $this->expectException(InvalidDimension::class);
        $this->expectExceptionMessageIsOrContains('The locale "en" is not served.');

        DimensionLocales::chain(DeclaredLocales::fromArray(['fr' => []]), Locale::fromString('en'));
    }
}
