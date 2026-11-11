<?php

declare(strict_types=1);

namespace Phil\Migration\Tests\Unit;

use Closure;
use Phil\Migration\Channel;
use Phil\Migration\Content;
use Phil\Migration\InvalidAnnouncement;
use Phil\Migration\Messages;
use Phil\Migration\News;
use Phil\Migration\Release;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Channel::class)]
#[CoversClass(Messages::class)]
#[CoversClass(Content::class)]
final class ChannelTest extends TestCase
{
    public function testAChannelTellsTheNotesOfItsLocale(): void
    {
        $release = new Release([['fr' => '* Un.', 'en' => '* One.'], ['fr' => '* Deux.']]);
        $channel = new Channel('  alice ', 'fr', title: '# Nouvelle version{counter}');

        self::assertSame(['alice', 'fr', Content::Notes, '', '{title}', '{url}', 2000], [$channel->transport, $channel->locale, $channel->content, $channel->continued, $channel->heading, $channel->link, $channel->maxLength]);
        self::assertSame(["# Nouvelle version\n\n* Un.\n* Deux."], $channel->messages($release));
        self::assertSame(['* One.'], new Channel('bob', 'en')->messages($release));
        self::assertSame([], new Channel('carol', 'de')->messages($release));
        self::assertSame([], new Channel('alice', 'fr', Content::News)->messages($release));
    }

    public function testAChannelTellsTheNewsOfItsLocale(): void
    {
        $release = new Release([['fr' => '* Un.']], [
            ['fr' => new News('Calendrier', ['Tout.', 'Partout.'], 'https://example.com/c'), 'en' => new News('Calendar')],
            ['fr' => new News('Annuaire')],
        ]);

        self::assertSame(["# Calendrier\nTout.\nPartout.\nVoir https://example.com/c\n# Annuaire"], new Channel('news', 'fr', Content::News, heading: '# {title}', link: 'Voir {url}')->messages($release));
        self::assertSame(["Calendrier\nTout.\nPartout.\nhttps://example.com/c\nAnnuaire"], new Channel('news', 'fr', Content::News)->messages($release));
        self::assertSame(["Calendrier\nTout.\nPartout.\nAnnuaire"], new Channel('news', 'fr', Content::News, link: '')->messages($release));
        self::assertSame(['Calendar'], new Channel('news', 'en', Content::News)->messages($release));
        self::assertSame([Content::Notes, Content::News], [Content::from('notes'), Content::from('news')]);
    }

    public function testALongReleaseTakesNumberedMessages(): void
    {
        $lines = [str_repeat('a', 40), str_repeat('b', 40), str_repeat('c', 40)];
        $release = new Release(array_map(static fn (string $line): array => ['fr' => $line], $lines));

        self::assertSame([
            "# Version (1/2)\n\n".$lines[0]."\n".$lines[1],
            "## Suite (2/2)\n\n".$lines[2],
        ], new Channel('alice', 'fr', title: '# Version{counter}', continued: '## Suite{counter}', maxLength: 100)->messages($release));
        self::assertSame([$lines[0]."\n".$lines[1], $lines[2]], new Channel('alice', 'fr', maxLength: 100)->messages($release));
        self::assertSame([$lines[0]."\n".$lines[1]."\n".$lines[2]], new Channel('alice', 'fr', maxLength: 122)->messages($release));
        self::assertSame([$lines[0]."\n".$lines[1], $lines[2]], new Channel('alice', 'fr', maxLength: 121)->messages($release));
        self::assertSame(["# Version (1/2)\n\n".$lines[0]."\n".$lines[1], $lines[2]], new Channel('alice', 'fr', title: '# Version{counter}', maxLength: 100)->messages($release));
    }

    public function testTheCounterMakesRoomForItsDigits(): void
    {
        $release = new Release(array_map(static fn (int $i): array => ['fr' => str_pad((string) $i, 89, '.')], range(1, 10)));
        $messages = new Channel('alice', 'fr', title: 'V{counter}', continued: 'W{counter}', maxLength: 100)->messages($release);

        self::assertCount(10, $messages);
        self::assertSame("V (1/10)\n\n".str_pad('1', 89, '.'), $messages[0]);
        self::assertSame("W (10/10)\n\n".str_pad('10', 89, '.'), $messages[9]);
        self::assertSame(100, mb_strlen($messages[9]));
        self::assertSame([], array_filter($messages, static fn (string $message): bool => mb_strlen($message) > 100));

        $release = new Release(array_map(static fn (int $i): array => ['fr' => str_pad((string) $i, 85, '.')], range(1, 9)));
        $nine = new Channel('alice', 'fr', title: 'V{counter}', continued: 'V{counter}', maxLength: 100)->messages($release);
        self::assertSame("V (9/9)\n\n".str_pad('9', 85, '.'), $nine[8]);
        self::assertSame(94, mb_strlen($nine[8]));
    }

    public function testALineTooLongIsCutAtASpace(): void
    {
        $words = 'Une nouvelle très longue '.str_repeat('é', 120).' fin';
        $release = new Release([['fr' => $words], ['fr' => 'court']]);

        self::assertSame([
            'Une nouvelle très longue',
            str_repeat('é', 100),
            str_repeat('é', 20).' fin'."\ncourt",
        ], new Channel('alice', 'fr', maxLength: 100)->messages($release));
        self::assertSame([str_repeat('a', 99).'b', 'b c'], new Channel('alice', 'fr', maxLength: 100)->messages(new Release([['fr' => str_repeat('a', 99).'bb c']])));
        self::assertSame([str_repeat('a', 99), 'b'], new Channel('alice', 'fr', maxLength: 100)->messages(new Release([['fr' => str_repeat('a', 99).' b']])));
        self::assertSame([str_repeat('a', 100), 'b'], new Channel('alice', 'fr', maxLength: 100)->messages(new Release([['fr' => str_repeat('a', 100).' b']])));
        self::assertSame([str_repeat('a', 100), 'b'], new Channel('alice', 'fr', maxLength: 100)->messages(new Release([['fr' => str_repeat('a', 100).'    b']])));
        self::assertSame([' '.str_repeat('b', 99), 'b'], new Channel('alice', 'fr', maxLength: 100)->messages(new Release([['fr' => ' '.str_repeat('b', 100)]])));
        self::assertSame(['a', str_repeat('b', 100)], new Channel('alice', 'fr', maxLength: 100)->messages(new Release([['fr' => 'a '.str_repeat('b', 100)]])));
    }

    public function testTheTitlesTakeTheirRoom(): void
    {
        $line = static fn (int $length): Release => new Release([['fr' => str_repeat('a', $length)]]);
        $both = new Channel('alice', 'fr', title: 'T{counter}', continued: 'T{counter}', maxLength: 100);

        self::assertSame(["T (1/2)\n\n".str_repeat('a', 91), "T (2/2)\n\n".str_repeat('a', 7)], $both->messages($line(98)));
        self::assertSame(["T\n\n".str_repeat('a', 97)], $both->messages($line(97)));
        self::assertSame(["T\n\n".str_repeat('a', 95)], $both->messages($line(95)));
        self::assertSame(["T (1/2)\n\n".str_repeat('a', 91), str_repeat('a', 7)], new Channel('alice', 'fr', title: 'T{counter}', maxLength: 100)->messages($line(98)));
        self::assertSame(["É\n\n".str_repeat('a', 97)], new Channel('alice', 'fr', title: 'É{counter}', maxLength: 100)->messages($line(97)));
    }

    public function testLengthsAreCountedInCharacters(): void
    {
        $channel = new Channel('alice', 'fr', maxLength: 100);
        $one = static fn (string $line): Release => new Release([['fr' => $line]]);

        self::assertSame([str_repeat('é', 50)."\n".str_repeat('é', 49)], $channel->messages(new Release([['fr' => str_repeat('é', 50)], ['fr' => str_repeat('é', 49)]])));
        self::assertSame([str_repeat('é', 60)], $channel->messages($one(str_repeat('é', 60))));
        self::assertSame([str_repeat('é', 60), str_repeat('a', 60)], $channel->messages($one(str_repeat('é', 60).' '.str_repeat('a', 60))));
        self::assertSame([str_repeat('é', 30), str_repeat('a', 80)], $channel->messages($one(str_repeat('é', 30).' '.str_repeat('a', 80))));
        self::assertSame([str_repeat('a', 50), str_repeat('b', 60)], $channel->messages($one(str_repeat('a', 50).'  '.str_repeat('b', 60))));
        self::assertSame([str_repeat('a', 100), 'a b'], $channel->messages($one(str_repeat('a', 101).' b')));
    }

    /**
     * @return iterable<string, array{Closure(): Channel, string}>
     */
    public static function badChannels(): iterable
    {
        yield 'no transport' => [static fn (): Channel => new Channel('  ', 'fr'), 'A channel names the chat transport it announces on.'];
        yield 'a bad locale' => [static fn (): Channel => new Channel('alice', 'fr-CA'), 'A locale is a language, and maybe a region: "fr" or "fr_CA"; "fr-CA" is not.'];
        yield 'too short' => [static fn (): Channel => new Channel('alice', 'fr', maxLength: 99), 'A message is 100 characters or more; 99 is too short.'];
        yield 'a long title' => [static fn (): Channel => new Channel('alice', 'fr', title: str_repeat('é', 51), maxLength: 101), 'A title takes half of a message at most, 50 characters; "'.str_repeat('é', 51).'" is longer.'];
        yield 'a long continued' => [static fn (): Channel => new Channel('alice', 'fr', continued: str_repeat('é', 51), maxLength: 100), 'A title takes half of a message at most, 50 characters; "'.str_repeat('é', 51).'" is longer.'];
        yield 'a heading without title' => [static fn (): Channel => new Channel('alice', 'fr', heading: '# title'), 'The heading of a piece of news holds its {title}; "# title" does not.'];
    }

    #[DataProvider('badChannels')]
    public function testABadChannelIsRefused(Closure $make, string $message): void
    {
        $this->expectException(InvalidAnnouncement::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testTheBoundsOfAChannel(): void
    {
        self::assertSame(100, new Channel('alice', 'fr', title: str_repeat('é', 50), continued: str_repeat('é', 50), maxLength: 100)->maxLength);
        self::assertSame(100, Channel::MIN_LENGTH);
    }
}
