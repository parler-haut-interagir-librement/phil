<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Unit\Card;

use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Card\CardFont;
use Phil\SocialCards\Card\CardGenerator;
use Phil\SocialCards\Card\CardTemplate;
use Phil\SocialCards\Card\Color;
use Phil\SocialCards\Card\DirectoryCardStore;
use Phil\SocialCards\Card\Fingerprint;
use Phil\SocialCards\Card\GeneratedCard;
use Phil\SocialCards\Card\InMemoryCardStore;
use Phil\SocialCards\Exception\CardRenderingFailed;
use Phil\SocialCards\Exception\InvalidCardTemplate;
use Phil\SocialCards\Tests\Support\CountingRenderer;
use Phil\SocialCards\Tests\Support\Directories;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CardGenerator::class)]
#[CoversClass(GeneratedCard::class)]
#[CoversClass(Fingerprint::class)]
#[CoversClass(InMemoryCardStore::class)]
#[CoversClass(DirectoryCardStore::class)]
final class CardGeneratorTest extends TestCase
{
    private CardTemplate $template;

    protected function setUp(): void
    {
        $this->template = new CardTemplate(CardFont::fromFile(__DIR__.'/../../Fixtures/card-font.ttf'));
    }

    public function testEachCardIsDrawnOnce(): void
    {
        $renderer = $this->renderer('v1');
        $store = new InMemoryCardStore();
        $generator = new CardGenerator($renderer, $store);
        $content = new CardContent('Title', 'Subtitle', 'fr');

        $card = $generator->generate($this->template, $content);
        self::assertSame(hash('xxh128', "v1\0".$this->template->fingerprint()."\0Title\0Subtitle\0fr"), $card->fingerprint);
        self::assertSame('png:Title', $card->png);
        self::assertSame('png:Title', $store->get($card->fingerprint));
        self::assertEquals($card, $generator->generate($this->template, $content));
        self::assertSame(1, $renderer->renders);
        self::assertSame($card->fingerprint, $generator->fingerprint($this->template, $content));
        self::assertSame(hash('xxh128', "v1\0".$this->template->fingerprint()."\0Title\0\0en"), $generator->fingerprint($this->template, new CardContent('Title')));
    }

    public function testEveryPartChangesTheFingerprint(): void
    {
        $generator = new CardGenerator($this->renderer('v1'), new InMemoryCardStore());
        $content = new CardContent('Title', 'Subtitle', 'fr');
        $fingerprints = [
            $generator->fingerprint($this->template, $content),
            new CardGenerator($this->renderer('v2'), new InMemoryCardStore())->fingerprint($this->template, $content),
            $generator->fingerprint(new CardTemplate($this->template->titleFont, accent: new Color(1, 2, 3)), $content),
            $generator->fingerprint($this->template, new CardContent('Title!', 'Subtitle', 'fr')),
            $generator->fingerprint($this->template, new CardContent('Title', 'Subtitle!', 'fr')),
            $generator->fingerprint($this->template, new CardContent('Title', 'Subtitle', 'en')),
            $generator->fingerprint($this->template, new CardContent('Title', null, 'fr')),
            $generator->fingerprint($this->template, new CardContent("Title\0Subtitle", null, 'fr')),
        ];

        self::assertCount(8, array_unique($fingerprints));
    }

    public function testCardsKeptAsFiles(): void
    {
        $directory = sys_get_temp_dir().'/phil-social-cards-store-'.bin2hex(random_bytes(4));
        $store = new DirectoryCardStore($directory.'/');
        $fingerprint = str_repeat('ab', 16);

        self::assertNull($store->get($fingerprint));
        $store->put($fingerprint, 'one');
        $store->put($fingerprint, 'two');
        self::assertSame('two', $store->get($fingerprint));
        self::assertSame('two', (string) file_get_contents($directory.'/ab/'.$fingerprint.'.png'));
        self::assertSame(['.', '..', $fingerprint.'.png'], scandir($directory.'/ab'), 'No temporary file is left.');
        self::assertSame('two', new DirectoryCardStore($directory)->get($fingerprint));

        mkdir($directory.'/cd/'.str_repeat('cd', 16).'.png', 0o777, true);
        self::assertNull($store->get(str_repeat('cd', 16)), 'A directory is no card.');

        try {
            $store->put(str_repeat('cd', 16), 'three');
            self::fail('A card cannot replace a directory.');
        } catch (CardRenderingFailed $cardRenderingFailed) {
            self::assertSame(\sprintf('The card "%s" cannot be written in "%s".', str_repeat('cd', 16), $directory.'/cd'), $cardRenderingFailed->getMessage());
            self::assertSame(['.', '..', str_repeat('cd', 16).'.png'], scandir($directory.'/cd'), 'The temporary file is removed.');
        }

        file_put_contents($directory.'/ef', 'a file where a directory goes');

        try {
            $store->put(str_repeat('ef', 16), 'four');
            self::fail('The directory of a card cannot be created.');
        } catch (CardRenderingFailed $cardRenderingFailed) {
            self::assertSame(\sprintf('The directory "%s" cannot be created.', $directory.'/ef'), $cardRenderingFailed->getMessage());
        }

        $nested = new DirectoryCardStore($directory.'/a/b');
        $nested->put($fingerprint, 'five');
        self::assertSame('five', $nested->get($fingerprint));

        Directories::remove($directory);
    }

    public function testAnUnreadableCardIsNone(): void
    {
        $directory = sys_get_temp_dir().'/phil-social-cards-store-'.bin2hex(random_bytes(4));
        $store = new DirectoryCardStore($directory);
        $fingerprint = str_repeat('12', 16);
        $store->put($fingerprint, 'png');
        chmod($directory.'/12/'.$fingerprint.'.png', 0o000);

        if (is_readable($directory.'/12/'.$fingerprint.'.png')) {
            Directories::remove($directory);
            self::markTestSkipped('This user reads a file whatever its mode, as root does.');
        }

        self::assertNull($store->get($fingerprint));

        chmod($directory.'/12/'.$fingerprint.'.png', 0o644);
        Directories::remove($directory);
    }

    public function testAFingerprintIsAllAStoreTakes(): void
    {
        self::assertTrue(Fingerprint::isValid(str_repeat('0f', 16)));
        self::assertSame('[0-9a-f]{32}', Fingerprint::PATTERN);

        foreach ([str_repeat('a', 31), str_repeat('a', 33), str_repeat('A', 32), '../'.str_repeat('a', 29), str_repeat('a', 32)."\n", ''] as $fingerprint) {
            self::assertFalse(Fingerprint::isValid($fingerprint));
        }

        $memory = new InMemoryCardStore();
        $memory->put(str_repeat('a', 32), 'png');
        $memory->reset();
        self::assertNull($memory->get(str_repeat('a', 32)));

        foreach ([static fn (): ?string => $memory->get('../x'), static fn () => $memory->put('x', 'png'), static fn (): ?string => new DirectoryCardStore('/tmp')->get('../../etc/passwd'), static fn () => new DirectoryCardStore('/tmp')->put('x', 'png')] as $call) {
            try {
                $call();
                self::fail('The fingerprint is refused.');
            } catch (InvalidCardTemplate $invalidCardTemplate) {
                self::assertStringContainsString('is not the fingerprint of a card: 32 lower-case hexadecimal digits.', $invalidCardTemplate->getMessage());
            }
        }
    }

    private function renderer(string $version): CountingRenderer
    {
        return new CountingRenderer($version);
    }
}
