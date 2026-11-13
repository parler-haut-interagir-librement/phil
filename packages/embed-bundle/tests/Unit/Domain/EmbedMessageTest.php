<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Unit\Domain;

use Phil\EmbedBundle\Domain\Enum\MessageType;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbedMessage;
use Phil\EmbedBundle\Domain\ValueObject\EmbedMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmbedMessage::class)]
#[CoversClass(InvalidEmbedMessage::class)]
final class EmbedMessageTest extends TestCase
{
    /**
     * The fixtures the JavaScript's tests read too.
     *
     * @return iterable<string, array{string, ?array<string, mixed>}>
     */
    public static function fixtures(): iterable
    {
        // Read as objects, so that `{}` and `[]` stay apart, as they do for JavaScript.
        $fixtures = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/messages.json'), false, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($fixtures);

        foreach ($fixtures as $fixture) {
            self::assertIsObject($fixture);
            $fields = get_object_vars($fixture);
            $json = json_encode($fields['data'] ?? null, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
            $expected = $fields['expected'] ?? null;
            self::assertTrue(null === $expected || \is_object($expected));

            /** @var ?array<string, mixed> $values */
            $values = null === $expected ? null : get_object_vars($expected);

            yield $json => [$json, $values];
        }
    }

    public function testAMessageFromItsValues(): void
    {
        self::assertSame(MessageType::Resize, EmbedMessage::fromArray(['protocol' => EmbedMessage::PROTOCOL, 'version' => EmbedMessage::VERSION, 'type' => 'resize', 'height' => 300])->type);
    }

    /**
     * @param ?array<string, mixed> $expected
     */
    #[DataProvider('fixtures')]
    public function testTheFixturesOfTheProtocol(string $json, ?array $expected): void
    {
        try {
            $message = EmbedMessage::fromJson($json);
        } catch (InvalidEmbedMessage) {
            self::assertNull($expected, 'refused: '.$json);

            return;
        }

        self::assertNotNull($expected, 'accepted: '.$json);
        self::assertSame($expected, array_filter(['type' => $message->type->value, 'height' => $message->height, 'theme' => $message->theme], static fn (mixed $value): bool => null !== $value));
    }

    public function testTheMessagesToPost(): void
    {
        self::assertSame(['protocol' => 'phil-embed', 'version' => 1, 'type' => 'ready'], EmbedMessage::ready()->toArray());
        self::assertSame(['protocol' => 'phil-embed', 'version' => 1, 'type' => 'resize', 'height' => 0], EmbedMessage::resize(0)->toArray());
        self::assertSame(['protocol' => 'phil-embed', 'version' => 1, 'type' => 'theme', 'theme' => 'dark'], EmbedMessage::theme('dark')->toArray());
        self::assertSame(MessageType::Resize, EmbedMessage::fromJson((string) json_encode(EmbedMessage::resize(100_000)->toArray()))->type);
    }

    public function testTheReasons(): void
    {
        $reasons = [];

        foreach ([
            str_repeat(' ', 1025) => 'A message is 1024 bytes at most.',
            '{"a":' => 'A message is a JSON object of scalar values.',
            '{"a":{"b":1}}' => 'A message is a JSON object of scalar values.',
            '[1]' => 'A message is an object.',
            '"ready"' => 'A message is an object.',
            '{"protocol":"phil-embed","version":2,"type":"ready"}' => 'A message is of the protocol "phil-embed", version 1.',
            '{"protocol":"phil-embed","version":1,"type":"x"}' => 'A message is of the type "ready", "resize" or "theme".',
            '{"protocol":"phil-embed","version":1,"type":"ready","a":1}' => 'A "ready" message holds protocol, type, version and nothing else.',
            '{"protocol":"phil-embed","version":1,"type":"resize","height":1.5}' => 'A height is a whole number of pixels.',
            '{"protocol":"phil-embed","version":1,"type":"resize","height":1e300}' => 'A height is a whole number of pixels.',
            '{"protocol":"phil-embed","version":1,"type":"resize","height":-2}' => 'A height is 0 to 100000 pixels, not -2.',
            '{"protocol":"phil-embed","version":1,"type":"theme","theme":1}' => 'A theme is a string.',
            '{"protocol":"phil-embed","version":1,"type":"theme","theme":"A"}' => 'A theme is a lowercase letter, then up to 31 lowercase letters, digits or "-".',
        ] as $json => $reason) {
            try {
                EmbedMessage::fromJson($json);
                $reasons[] = 'accepted';
            } catch (InvalidEmbedMessage $error) {
                $reasons[] = $error->getMessage();
            }

            self::assertSame($reason, end($reasons), $json);
        }

        self::assertSame(MessageType::Ready, EmbedMessage::fromJson(str_pad('{"protocol":"phil-embed","version":1,"type":"ready"}', 1024))->type);
    }

    public function testTheHeightsOfAResizeAreBounded(): void
    {
        self::assertSame(100_000, EmbedMessage::resize(100_000)->height);
        self::assertSame(100_000, EmbedMessage::fromJson('{"protocol":"phil-embed","version":1,"type":"resize","height":100000.0}')->height);

        $this->expectExceptionObject(InvalidEmbedMessage::because('A height is 0 to 100000 pixels, not 100001.'));
        EmbedMessage::resize(100_001);
    }

    public function testAFloatHeightBeyondTheBoundIsNotAWholeNumberOfTheProtocol(): void
    {
        $this->expectExceptionObject(InvalidEmbedMessage::because('A height is a whole number of pixels.'));

        EmbedMessage::fromJson('{"protocol":"phil-embed","version":1,"type":"resize","height":100001.0}');
    }

    public function testAThemeIsChecked(): void
    {
        $this->expectExceptionObject(InvalidEmbedMessage::because('A theme is a lowercase letter, then up to 31 lowercase letters, digits or "-".'));

        EmbedMessage::theme('');
    }
}
