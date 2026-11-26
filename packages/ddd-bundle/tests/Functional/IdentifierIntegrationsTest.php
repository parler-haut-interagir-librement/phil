<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional;

use Phil\DddBundle\Infrastructure\HttpKernel\IdentifierValueResolver;
use Phil\DddBundle\Infrastructure\Serializer\IdentifierNormalizer;
use Phil\DddBundle\PhilDddBundle;
use Phil\DddBundle\Tests\Functional\App\Membership\MemberCard;
use Phil\DddBundle\Tests\Functional\App\Membership\MemberId;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The two integrations of identifiers in an application that has the router and the Serializer: a route placeholder
 * reaches the controller as the identifier class it declares, and an identifier crosses the Serializer as its string.
 */
#[CoversClass(PhilDddBundle::class)]
#[CoversClass(IdentifierValueResolver::class)]
#[CoversClass(IdentifierNormalizer::class)]
final class IdentifierIntegrationsTest extends FunctionalTestCase
{
    public function testARoutePlaceholderReachesTheControllerAsItsIdentifierClass(): void
    {
        $member = MemberId::generate();

        $response = $this->get('/members/'.$member->toString());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(MemberId::class.' '.$member->toString(), $response->getContent());
    }

    public function testAPlaceholderThatIsNotAUuidIsNotFound(): void
    {
        self::assertSame(404, $this->get('/members/philippe')->getStatusCode());
    }

    public function testAPlaceholderThatIsAUuidOfAnotherVersionIsNotFound(): void
    {
        self::assertSame(404, $this->get('/members/'.Uuid::v4()->toRfc4122())->getStatusCode());
    }

    /**
     * The resolver is registered under its class name: `#[ValueResolver(IdentifierValueResolver::class, disabled:
     * true)]` leaves the argument to its default.
     */
    public function testAnArgumentOptsOutByTheResolverClassName(): void
    {
        $response = $this->get('/opt-out/'.MemberId::generate()->toString());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('none', $response->getContent());
    }

    /**
     * Without debug mode, Symfony itself skips the resolver it recognises by its class name.
     */
    public function testAnArgumentOptsOutWithTheKernelOutOfDebugMode(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel(['debug' => false]);

        $response = $this->get('/opt-out/'.MemberId::generate()->toString());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('none', $response->getContent());
    }

    public function testAnIdentifierIsSerialisedAsItsCanonicalString(): void
    {
        $member = MemberId::fromString('0192f3c4-8a1e-7b2c-9d3e-4f5a6b7c8d9e');

        $json = $this->serializer()->serialize(new MemberCard($member, 'ada@example.org'), 'json');

        self::assertSame('{"id":"0192f3c4-8a1e-7b2c-9d3e-4f5a6b7c8d9e","email":"ada@example.org"}', $json);
    }

    public function testAnIdentifierIsBuiltBackFromItsString(): void
    {
        $member = MemberId::generate();

        $card = $this->serializer()->deserialize(\sprintf('{"id":"%s","email":"ada@example.org"}', $member), MemberCard::class, 'json');

        self::assertInstanceOf(MemberCard::class, $card);
        self::assertTrue($member->equals($card->id));
    }

    public function testAStringThatIsNotAnIdentifierIsRefusedAtItsPath(): void
    {
        try {
            $this->serializer()->deserialize('{"id":"philippe","email":"ada@example.org"}', MemberCard::class, 'json');
            self::fail('A string that is not an identifier must be refused.');
        } catch (NotNormalizableValueException $notNormalizableValueException) {
            self::assertSame('id', $notNormalizableValueException->getPath());
            self::assertTrue($notNormalizableValueException->canUseMessageForUser());
        }
    }

    private function get(string $path): Response
    {
        return self::$kernel?->handle(Request::create($path)) ?? self::fail('The kernel is not booted.');
    }

    private function serializer(): SerializerInterface
    {
        return $this->service('serializer', SerializerInterface::class);
    }
}
