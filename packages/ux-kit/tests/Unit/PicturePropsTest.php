<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use ArrayObject;
use InvalidArgumentException;
use Override;
use Phil\UxKit\Application\Contract\PicturePropsProvider;
use Phil\UxKit\Application\PictureProps;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(PictureProps::class)]
final class PicturePropsTest extends TestCase
{
    public function testTheFirstProviderThatSupportsTheObjectAnswers(): void
    {
        $pictures = new PictureProps([
            $this->provider(ArrayObject::class, ['src' => '/array.jpg']),
            $this->provider(stdClass::class, ['src' => '/first.jpg', 'alt' => 'First']),
            $this->provider(stdClass::class, ['src' => '/second.jpg']),
        ]);

        self::assertSame(['src' => '/first.jpg', 'alt' => 'First'], $pictures->of(new stdClass()));
        self::assertSame(['src' => '/array.jpg'], $pictures->of(new ArrayObject()));
    }

    public function testTheOptionsReachTheProvider(): void
    {
        $pictures = new PictureProps([$this->provider(stdClass::class, ['src' => '/a.jpg'])]);

        self::assertSame(['src' => '/a.jpg', 'sizes' => '50vw'], $pictures->of(new stdClass(), ['sizes' => '50vw']));
    }

    public function testOnlyThePropsThatDescribeTheImageAreKept(): void
    {
        $props = array_fill_keys(PictureProps::KEYS, 'x');
        $pictures = new PictureProps([$this->provider(stdClass::class, $props + ['priority' => true, 'loading' => 'eager', 'layout' => 'fixed', 'onclick' => 'alert(1)', 'class' => 'big'])]);

        self::assertSame($props, $pictures->of(new stdClass()));
    }

    public function testAnObjectNoProviderSupportsIsAnError(): void
    {
        $pictures = new PictureProps([$this->provider(ArrayObject::class, ['src' => '/a.jpg'])]);

        try {
            $pictures->of(new stdClass());
            self::fail('Nothing was refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString('No provider gives the props of a picture for stdClass: implement Phil\UxKit\Application\Contract\PicturePropsProvider in a service, which autoconfiguration tags "phil_ux_kit.picture_props".', $invalidArgumentException->getMessage());
        }
    }

    public function testWithoutProvidersEveryObjectIsAnError(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PictureProps()->of(new stdClass());
    }

    /**
     * A provider of the objects of one class, which gives the props given, the options added to them.
     *
     * @param class-string         $class
     * @param array<string, mixed> $props
     */
    private function provider(string $class, array $props): PicturePropsProvider
    {
        return new readonly class($class, $props) implements PicturePropsProvider {
            /**
             * @param class-string         $class
             * @param array<string, mixed> $props
             */
            public function __construct(private string $class, private array $props)
            {
            }

            #[Override]
            public function supports(object $subject): bool
            {
                return $subject instanceof $this->class;
            }

            #[Override]
            public function props(object $subject, array $options = []): array
            {
                return $this->props + $options;
            }
        };
    }
}
