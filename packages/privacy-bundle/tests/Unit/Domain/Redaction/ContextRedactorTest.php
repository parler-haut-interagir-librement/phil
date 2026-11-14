<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Redaction;

use LogicException;
use Phil\PrivacyBundle\Domain\Redaction\CommandLineRedactor;
use Phil\PrivacyBundle\Domain\Redaction\ContextRedactor;
use Phil\PrivacyBundle\Domain\Redaction\Denylist;
use Phil\PrivacyBundle\Domain\Redaction\UrlRedactor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[CoversClass(ContextRedactor::class)]
#[UsesClass(CommandLineRedactor::class)]
final class ContextRedactorTest extends TestCase
{
    public function testADeniedKeyIsRedactedWhateverItsValue(): void
    {
        self::assertSame(
            ['password' => '[redacted]', 'token' => '[redacted]', 'secret' => '[redacted]', 'user' => 42],
            $this->redactor()->redactContext(['password' => 'hunter2', 'token' => ['a', 'b'], 'secret' => null, 'user' => 42]),
        );
    }

    public function testDeniedKeysAreFoundAtAnyDepth(): void
    {
        self::assertSame(
            ['request' => ['headers' => ['Authorization' => '[redacted]', 'Accept' => 'text/html']]],
            $this->redactor()->redactContext(['request' => ['headers' => ['Authorization' => 'Bearer x', 'Accept' => 'text/html']]]),
        );
    }

    public function testStringsAreUrlRedactedAtAnyDepth(): void
    {
        self::assertSame(
            ['uri' => '/a?[redacted]', 'list' => ['/p/[redacted]']],
            $this->redactor()->redactContext(['uri' => '/a?x=1', 'list' => ['/p/abc']]),
        );
    }

    public function testIntegerKeysAreNeverDeniedButTheirValuesAreWalked(): void
    {
        self::assertSame([0 => '/a?[redacted]'], $this->redactor()->redactContext([0 => '/a?x=1']));
    }

    public function testObjectsAreLeftAsTheyAre(): void
    {
        $object = new stdClass();

        self::assertSame(['subject' => $object], $this->redactor()->redactContext(['subject' => $object]));
    }

    /**
     * PSR-3 logs an exception under `exception`; its message is where a failed request's URL lands.
     */
    public function testAThrowableIsReplacedByItsRedactedRepresentation(): void
    {
        $previous = new LogicException('Link /p/abc expired', 7);
        $exception = new RuntimeException('GET /reset?token=SECRET failed', 3, $previous);

        $redacted = $this->redactor()->redactContext(['exception' => $exception, 'nested' => ['error' => $previous]]);

        self::assertSame(
            [
                'exception' => [
                    'class' => RuntimeException::class,
                    'message' => 'GET /reset?[redacted] failed',
                    'code' => 3,
                    'file' => __FILE__,
                    'line' => $exception->getLine(),
                    'previous' => [
                        'class' => LogicException::class,
                        'message' => 'Link /p/[redacted] expired',
                        'code' => 7,
                        'file' => __FILE__,
                        'line' => $previous->getLine(),
                        'previous' => null,
                    ],
                ],
                'nested' => [
                    'error' => [
                        'class' => LogicException::class,
                        'message' => 'Link /p/[redacted] expired',
                        'code' => 7,
                        'file' => __FILE__,
                        'line' => $previous->getLine(),
                        'previous' => null,
                    ],
                ],
            ],
            $redacted,
        );
    }

    public function testTheMessageIsUrlRedacted(): void
    {
        self::assertSame('Visited /p/[redacted]', $this->redactor()->redactMessage('Visited /p/abc'));
    }

    /**
     * Symfony logs a failed command with its command line in the message and the input under `command`, a key
     * that is not denied.
     */
    public function testACommandLineIsRedactedInTheMessageAndAtAnyDepth(): void
    {
        $redactor = $this->redactor();

        self::assertSame(
            'Error thrown while running command "app:x --password=[redacted] --token [redacted]".',
            $redactor->redactMessage('Error thrown while running command "app:x --password=hunter2 --token abc".'),
        );
        self::assertSame(
            ['command' => 'app:x --secret=[redacted]', 'nested' => ['argv' => 'app:x --token=[redacted] --env=prod']],
            $redactor->redactContext(['command' => "app:x --secret='s e'", 'nested' => ['argv' => 'app:x --token=abc --env=prod']]),
        );
    }

    public function testAThrowableMessageHasItsCommandLineRedacted(): void
    {
        $redacted = $this->redactor()->redactContext(['exception' => new RuntimeException('app:x --password=hunter2 failed')]);

        self::assertIsArray($redacted['exception']);
        self::assertSame('app:x --password=[redacted] failed', $redacted['exception']['message']);
    }

    public function testAUrlAndACommandLineInOneStringAreBothRedacted(): void
    {
        self::assertSame(
            'app:x --url=/a?[redacted] --token=[redacted]',
            $this->redactor()->redactMessage('app:x --url=/a?b=1 --token=abc'),
        );
    }

    private function redactor(): ContextRedactor
    {
        return new ContextRedactor(Denylist::of('password', 'token', 'secret', 'authorization'), new UrlRedactor(['/p/']));
    }
}
