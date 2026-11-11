<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use LogicException;
use Phil\EnumsBundle\Infrastructure\Http\ParsedEnumValueResolver;
use Phil\EnumsBundle\Infrastructure\Http\ParseEnum;
use Phil\EnumsBundle\Tests\Unit\Fixture\Delivery;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[CoversClass(ParseEnum::class)]
#[CoversClass(ParsedEnumValueResolver::class)]
final class ParsedEnumValueResolverTest extends TestCase
{
    public function testTheAttributeTargetsTheResolver(): void
    {
        $attribute = new ParseEnum('state', ignoreCase: true);

        self::assertSame(ParsedEnumValueResolver::class, $attribute->resolver);
        self::assertSame('state', $attribute->attribute);
        self::assertTrue($attribute->ignoreCase);
        self::assertNull(new ParseEnum()->attribute);
        self::assertFalse(new ParseEnum()->ignoreCase);
    }

    public function testAnArgumentWithoutTheAttributeIsLeftToOtherResolvers(): void
    {
        self::assertSame([], $this->resolve(['status' => 'shipped'], Delivery::class, null));
    }

    public function testTheRouteAttributeIsReadByValueNameOrAlias(): void
    {
        self::assertSame([Delivery::Shipped], $this->resolve(['status' => 'shipped']));
        self::assertSame([Delivery::Shipped], $this->resolve(['status' => 'Shipped']));
        self::assertSame([Delivery::Ordered], $this->resolve(['status' => 'new']));
        self::assertSame([Shape::Circle], $this->resolve(['status' => 'Circle'], Shape::class));
    }

    public function testLetterCaseIsIgnoredOnlyWhenAsked(): void
    {
        self::assertSame([Delivery::Shipped], $this->resolve(['status' => 'SHIPPED'], attribute: new ParseEnum(ignoreCase: true)));
        self::assertSame([Delivery::Ordered], $this->resolve(['status' => 'NEW'], attribute: new ParseEnum(ignoreCase: true)));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('"SHIPPED" is no case of '.Delivery::class.'.');

        $this->resolve(['status' => 'SHIPPED']);
    }

    public function testAnotherAttributeNameCanBeRead(): void
    {
        self::assertSame([Delivery::Delivered], $this->resolve(['state' => 'delivered'], attribute: new ParseEnum('state')));
    }

    public function testAMissingValueIsNullForANullableArgumentAnd404Otherwise(): void
    {
        self::assertSame([null], $this->resolve([], nullable: true));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('No value for the argument "$status".');

        $this->resolve([]);
    }

    public function testAnArrayValueIsNoCase(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->resolve(['status' => ['shipped']], nullable: true);
    }

    public function testAnArgumentThatIsNotAnEnumIsAMistake(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('#[ParseEnum] reads enums; the argument "$status" is typed string.');

        $this->resolve(['status' => 'x'], 'string');
    }

    public function testAnUntypedArgumentIsAMistake(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('is typed nothing.');

        $this->resolve(['status' => 'x'], null);
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return list<mixed>
     */
    private function resolve(array $attributes, ?string $type = Delivery::class, ?ParseEnum $attribute = new ParseEnum(), bool $nullable = false): array
    {
        $argument = new ArgumentMetadata('status', $type, false, false, null, $nullable, $attribute instanceof ParseEnum ? [$attribute] : []);

        return [...new ParsedEnumValueResolver()->resolve(new Request(attributes: $attributes), $argument)];
    }
}
