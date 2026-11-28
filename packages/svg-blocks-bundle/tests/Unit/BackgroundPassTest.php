<?php

declare(strict_types=1);

namespace Phil\SvgBlocksBundle\Tests\Unit;

use InvalidArgumentException;
use Phil\SvgBlocks\BlockMarkup;
use Phil\SvgBlocks\Contract\MarkupRenderer;
use Phil\SvgBlocks\Signer;
use Phil\SvgBlocksBundle\Application\Handler\RenderDeferredMarkupHandler;
use Phil\SvgBlocksBundle\Application\Message\RenderDeferredMarkup;
use Phil\SvgBlocksBundle\Infrastructure\Messenger\MarkupScheduler;
use Phil\SvgBlocksBundle\Tests\Functional\App\InMemoryDocuments;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(RenderDeferredMarkup::class)]
#[CoversClass(RenderDeferredMarkupHandler::class)]
#[CoversClass(MarkupScheduler::class)]
final class BackgroundPassTest extends TestCase
{
    public function testDrawsThePendingDiagramsAndSaves(): void
    {
        $documents = new InMemoryDocuments();
        $documents->documents['a'] = Document::fromArray(['blocks' => [['id' => 'd', 'type' => 'mermaid', 'data' => ['code' => 'graph']]]]);
        $handler = new RenderDeferredMarkupHandler($documents, $this->markup());

        $handler(new RenderDeferredMarkup('a'));
        self::assertSame(['a'], $documents->saved);
        $stored = $documents->documents['a']->blocks[0]->data['rendered'] ?? null;
        self::assertIsArray($stored);
        self::assertSame('<svg/>', $stored['markup'] ?? null);

        $handler(new RenderDeferredMarkup('a'));
        $handler(new RenderDeferredMarkup('gone'));
        self::assertSame(['a'], $documents->saved);
    }

    public function testSendsTheSubjectAlone(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $sent = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->sent[] = $message;

                return new Envelope($message);
            }
        };
        new MarkupScheduler($bus)->schedule('article:7');

        self::assertEquals([new RenderDeferredMarkup('article:7')], $bus->sent);
    }

    public function testBoundsTheSubject(): void
    {
        self::assertSame(str_repeat('a', 255), new RenderDeferredMarkup(str_repeat('a', 255))->subject);

        foreach (['', str_repeat('a', 256)] as $subject) {
            try {
                new RenderDeferredMarkup($subject);
                self::fail('Refused.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('The subject of a document is 1 to 255 bytes long.', $exception->getMessage());
            }
        }
    }

    private function markup(): BlockMarkup
    {
        return new BlockMarkup(new Signer(str_repeat('s', 32)), ['mermaid' => new class implements MarkupRenderer {
            public function render(Block $block): string
            {
                return '<svg/>';
            }
        }]);
    }
}
