<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use Override;
use Phil\UxKit\Tests\Functional\App\LiipImagineKernel;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * `image_filters()` in an application with LiipImagine: the filter sets of an image become the props of a `Picture`.
 */
#[CoversNothing]
final class ImageFiltersTest extends KitTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return LiipImagineKernel::class;
    }

    public function testTheFilterSetsOfAnImageBecomeThePropsOfAPicture(): void
    {
        [$html] = $this->renderInRequest('<twig:Picture {{ ...image_filters(\'uploads/hall.jpg\', [\'hall_480\', \'hall_960\', \'hall_avif_480\', \'hall_avif_960\']) }} sizes="100vw" alt="The main hall" />');

        self::assertSame(
            '<picture>'
            .'<source type="image/avif" srcset="https://www.example.org/media/cache/resolve/hall_avif_480/uploads/hall.jpg 480w, https://www.example.org/media/cache/resolve/hall_avif_960/uploads/hall.jpg 960w" sizes="auto, 100vw">'
            .'<img class="isImage" src="https://www.example.org/media/cache/resolve/hall_960/uploads/hall.jpg" srcset="https://www.example.org/media/cache/resolve/hall_480/uploads/hall.jpg 480w, https://www.example.org/media/cache/resolve/hall_960/uploads/hall.jpg 960w" sizes="auto, 100vw" alt="The main hall" width="960" height="640" loading="lazy" decoding="async">'
            .'</picture>',
            $html,
        );
    }
}
