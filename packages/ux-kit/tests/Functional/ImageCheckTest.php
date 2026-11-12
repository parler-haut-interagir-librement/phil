<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use Phil\UxKit\Tests\Functional\App\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The warnings of the image recipe while the site is developed: a text alternative that is the name of the file or
 * says only that it is an image, and a missing size, logged on the channel of the kit; the page itself unchanged.
 */
#[CoversNothing]
final class ImageCheckTest extends KitTestCase
{
    public function testATextAlternativeLeftWrongIsLoggedInDebug(): void
    {
        self::assertSame(
            '<img class="isImage" src="/images/hall.jpg" alt="hall" width="8" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/images/hall.jpg" alt="hall" :width="8" :height="null" />'),
        );
        self::assertSame(
            [
                ['warning', 'The text alternative of the image "/images/hall.jpg" is "hall", the name of its file: say what the image shows, or leave it empty if it is decorative.', ['src' => '/images/hall.jpg', 'alt' => 'hall', 'problem' => 'file-name']],
                ['warning', 'The image "/images/hall.jpg" has no width or no height: give both, so that its space is reserved before it loads and the page does not shift.', ['src' => '/images/hall.jpg', 'alt' => 'hall', 'problem' => 'no-size']],
            ],
            $this->warnings(),
        );
    }

    public function testAPictureAndALogoAreCheckedThroughTheirImage(): void
    {
        $this->render('<twig:Picture src="/a.jpg" :sources="[{type: \'image/webp\', srcset: \'/a.webp\'}]" alt="Photo of the hall" :width="8" :height="6" />');
        $this->render('<twig:Logo src="/logo.png" alt="Logo" :width="8" :height="6" />');

        self::assertSame(['redundant', 'file-name', 'generic'], array_column(array_column($this->warnings(), 2), 'problem'));
    }

    public function testAGoodImageAndARefusedOneSayNothing(): void
    {
        $this->render('<twig:Image src="/images/hall.jpg" alt="The main hall" :width="8" :height="6" />');
        $this->render('<twig:Image src="https://tracker.example.com/image.gif" alt="image" :width="1" :height="1" />');

        self::assertSame([], $this->warnings());
    }

    public function testTheCheckCanBeTurnedOff(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel(['environment' => 'quiet']);
        $this->render('<twig:Image src="/images/hall.jpg" alt="hall" :width="8" :height="6" />');

        self::assertSame([], $this->warnings());
    }

    public function testWithoutDebugNothingIsChecked(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel(['debug' => false]);
        $this->render('<twig:Image src="/images/hall.jpg" alt="hall" :width="8" :height="6" />');

        self::assertSame([], $this->warnings());
    }

    /**
     * The warnings the recipe logged, each level, message and context.
     *
     * @return list<array{string, string, array<mixed>}>
     */
    private function warnings(): array
    {
        $logger = self::getContainer()->get('logger');
        self::assertInstanceOf(RecordingLogger::class, $logger);

        return array_values(array_filter($logger->records, static fn (array $record): bool => isset($record[2]['problem'])));
    }
}
