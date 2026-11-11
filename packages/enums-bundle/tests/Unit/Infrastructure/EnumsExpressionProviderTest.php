<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use Phil\EnumsBundle\Application\Presentation\Color;
use Phil\EnumsBundle\Infrastructure\ExpressionLanguage\EnumsExpressionProvider;
use Phil\EnumsBundle\Tests\Unit\Fixture\Delivery;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

#[CoversClass(EnumsExpressionProvider::class)]
final class EnumsExpressionProviderTest extends TestCase
{
    public function testTheFunctionsAreEvaluated(): void
    {
        $language = new ExpressionLanguage(null, [new EnumsExpressionProvider()]);
        $values = ['delivery' => Delivery::Ordered, 'color' => Color::class, 'enum' => Delivery::class];

        self::assertSame('grey', $language->evaluate('enum_meta(delivery, color)', $values));
        self::assertTrue($language->evaluate("enum_in_group(delivery, 'open')", $values));
        self::assertTrue($language->evaluate("enum_in_group(delivery, 'closed', 'open')", $values));
        self::assertFalse($language->evaluate("enum_in_group(delivery, 'closed')", $values));
        self::assertSame(Delivery::Shipped, $language->evaluate("enum_from('shipped', enum)", $values));
        self::assertTrue($language->evaluate("enum_in_group(enum_from('shipped', enum), 'open')", $values));
        self::assertFalse($language->evaluate("enum_in_group(enum_from('unknown', enum), 'open')", $values));
    }

    public function testTheFunctionsCompileToPhp(): void
    {
        $language = new ExpressionLanguage(null, [new EnumsExpressionProvider()]);

        self::assertSame('\\'.EnumsExpressionProvider::class.'::inGroup($delivery, "open")', $language->compile("enum_in_group(delivery, 'open')", ['delivery']));
    }

    public function testFromReadsAValueOrAName(): void
    {
        self::assertSame(Shape::Circle, EnumsExpressionProvider::from('Circle', Shape::class));
        self::assertNull(EnumsExpressionProvider::from('circle', Shape::class));
        self::assertNull(EnumsExpressionProvider::from(['shipped'], Delivery::class));
        self::assertNull(EnumsExpressionProvider::from(null, Delivery::class));
        self::assertFalse(EnumsExpressionProvider::inGroup(null, 'open'));
        self::assertTrue(EnumsExpressionProvider::inGroup(Delivery::Shipped, 'open'));
        self::assertSame('grey', EnumsExpressionProvider::meta(Delivery::Ordered, Color::class));
    }
}
