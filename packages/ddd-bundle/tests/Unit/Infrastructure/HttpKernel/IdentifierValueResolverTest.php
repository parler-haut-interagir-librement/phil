<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Infrastructure\HttpKernel;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\DddBundle\Domain\Identifier\Identifier;
use Phil\DddBundle\Infrastructure\HttpKernel\IdentifierValueResolver;
use Phil\DddBundle\Tests\Unit\Domain\Identifier\Fixture\AbstractExampleId;
use Phil\DddBundle\Tests\Unit\Domain\Identifier\Fixture\ExampleId;
use Phil\DddBundle\Tests\Unit\Domain\Identifier\Fixture\OtherId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

#[CoversClass(IdentifierValueResolver::class)]
final class IdentifierValueResolverTest extends TestCase
{
    public function testItBuildsTheDeclaredClassFromTheRequestAttribute(): void
    {
        $identifier = ExampleId::generate();

        $resolved = new IdentifierValueResolver()->resolve($this->request(['order' => $identifier->toString()]), $this->argument('order', ExampleId::class));

        self::assertCount(1, $resolved);
        self::assertInstanceOf(ExampleId::class, $resolved[0]);
        self::assertTrue($identifier->equals($resolved[0]));
    }

    /**
     * The argument's name picks the attribute, not its position among the attributes.
     */
    public function testItReadsTheAttributeNamedLikeTheArgument(): void
    {
        $identifier = ExampleId::generate();
        $request = $this->request(['other' => OtherId::generate()->toString(), 'order' => $identifier->toString()]);

        $resolved = new IdentifierValueResolver()->resolve($request, $this->argument('order', ExampleId::class));

        self::assertSame([$identifier->toString()], array_map(static fn (Identifier $id): string => $id->toString(), $resolved));
    }

    public function testAnIdentifierAlreadyInTheAttributeIsPassedThrough(): void
    {
        $identifier = ExampleId::generate();

        $resolved = new IdentifierValueResolver()->resolve($this->request(['order' => $identifier]), $this->argument('order', ExampleId::class));

        self::assertSame([$identifier], $resolved);
    }

    /**
     * An identifier of another class carries a valid UUIDv7, but it names another aggregate: it is read as a string
     * would be, never handed over as it is.
     */
    public function testAnIdentifierOfAnotherClassIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        new IdentifierValueResolver()->resolve($this->request(['order' => OtherId::generate()]), $this->argument('order', ExampleId::class));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidValues(): iterable
    {
        yield 'not a UUID' => ['philippe'];
        yield 'a UUID, but a v4' => [Uuid::v4()->toRfc4122()];
        yield 'empty' => [''];
        yield 'an integer' => [42];
        yield 'an array' => [['0192f3c4-8a1e-7b2c-9d3e-4f5a6b7c8d9e']];
        yield 'another object' => [new stdClass()];
    }

    #[DataProvider('invalidValues')]
    public function testAValueThatIsNotAnIdentifierIsNotFound(mixed $value): void
    {
        try {
            new IdentifierValueResolver()->resolve($this->request(['order' => $value]), $this->argument('order', ExampleId::class));
            self::fail('A value that is not an identifier must be a 404.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertSame(404, $notFoundHttpException->getStatusCode());
            self::assertSame('The value of the "order" argument is not an identifier of its class.', $notFoundHttpException->getMessage());
        }
    }

    public function testTheRefusalOfTheIdentifierIsKeptAsThePreviousException(): void
    {
        try {
            new IdentifierValueResolver()->resolve($this->request(['order' => 'philippe']), $this->argument('order', ExampleId::class));
            self::fail('A value that is not a UUID must be a 404.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertInstanceOf(InvalidValue::class, $notFoundHttpException->getPrevious());
        }
    }

    public function testANonStringValueHasNoPreviousException(): void
    {
        try {
            new IdentifierValueResolver()->resolve($this->request(['order' => 42]), $this->argument('order', ExampleId::class));
            self::fail('An integer must be a 404.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertNull($notFoundHttpException->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function otherTypes(): iterable
    {
        yield 'no type' => [null];
        yield 'a scalar' => ['string'];
        yield 'the base class' => [Identifier::class];
        yield 'an abstract subclass' => [AbstractExampleId::class];
        yield 'another class' => [stdClass::class];
        yield 'a union' => [ExampleId::class.'|'.OtherId::class];
        yield 'a class that does not exist' => ['App\\Missing\\OrderId'];
    }

    #[DataProvider('otherTypes')]
    public function testItAbstainsForAnArgumentOfAnotherType(?string $type): void
    {
        $resolved = new IdentifierValueResolver()->resolve($this->request(['order' => ExampleId::generate()->toString()]), $this->argument('order', $type));

        self::assertSame([], $resolved);
    }

    public function testItAbstainsWhenTheRequestHasNoSuchAttribute(): void
    {
        $resolved = new IdentifierValueResolver()->resolve($this->request(['other' => ExampleId::generate()->toString()]), $this->argument('order', ExampleId::class));

        self::assertSame([], $resolved);
    }

    /**
     * `null` for a nullable argument is Symfony's request attribute resolver's to give; this one does not claim it.
     */
    public function testItAbstainsForANullAttribute(): void
    {
        $resolved = new IdentifierValueResolver()->resolve($this->request(['order' => null]), $this->argument('order', ExampleId::class, nullable: true));

        self::assertSame([], $resolved);
    }

    public function testItAbstainsForAVariadicArgument(): void
    {
        $argument = new ArgumentMetadata('order', ExampleId::class, true, false, null);

        $resolved = new IdentifierValueResolver()->resolve($this->request(['order' => ExampleId::generate()->toString()]), $argument);

        self::assertSame([], $resolved);
    }

    public function testItAbstainsForAnArgumentThatDisablesIt(): void
    {
        $argument = new ArgumentMetadata('order', ExampleId::class, false, false, null, attributes: [new ValueResolver(IdentifierValueResolver::class, disabled: true)]);

        $resolved = new IdentifierValueResolver()->resolve($this->request(['order' => 'philippe']), $argument);

        self::assertSame([], $resolved);
    }

    /**
     * Pinning the resolver, or disabling another one, leaves it in charge.
     *
     * @return iterable<string, array{ValueResolver}>
     */
    public static function attributesThatKeepIt(): iterable
    {
        yield 'pinned' => [new ValueResolver(IdentifierValueResolver::class)];
        yield 'another resolver disabled' => [new ValueResolver('argument_resolver.request_attribute', disabled: true)];
    }

    #[DataProvider('attributesThatKeepIt')]
    public function testItResolvesForAnArgumentThatDoesNotDisableIt(ValueResolver $attribute): void
    {
        $identifier = ExampleId::generate();
        $argument = new ArgumentMetadata('order', ExampleId::class, false, false, null, attributes: [$attribute]);

        $resolved = new IdentifierValueResolver()->resolve($this->request(['order' => $identifier->toString()]), $argument);

        self::assertCount(1, $resolved);
        self::assertTrue($identifier->equals($resolved[0]));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function request(array $attributes): Request
    {
        return new Request(attributes: $attributes);
    }

    private function argument(string $name, ?string $type, bool $nullable = false): ArgumentMetadata
    {
        return new ArgumentMetadata($name, $type, false, false, null, $nullable);
    }
}
