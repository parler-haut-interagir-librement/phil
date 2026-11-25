<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Unit\Infrastructure;

use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroomBundle\Infrastructure\DependencyInjection\ServerSecret;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerSecret::class)]
final class ServerSecretTest extends TestCase
{
    public function testASecretOfItsOwnIsKept(): void
    {
        self::assertSame('shared-secret-of-the-server-0123', ServerSecret::check('shared-secret-of-the-server-0123'));
        self::assertSame('x-change-me-0123456789', ServerSecret::check('x-change-me-0123456789'), 'Only a secret that begins as a placeholder is one.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function placeholders(): iterable
    {
        yield 'the example of the recipe' => ['change-me-to-the-shared-secret-of-the-server'];
        yield 'without the hyphen' => ['changeme0123456789'];
        yield 'in capitals' => ['CHANGE-ME-0123456789'];
    }

    #[DataProvider('placeholders')]
    public function testAPlaceholderIsRefused(string $secret): void
    {
        $this->expectExceptionObject(InvalidValue::because('The secret shared with the server is a placeholder: give it the secret the server shares.'));

        ServerSecret::check($secret);
    }
}
