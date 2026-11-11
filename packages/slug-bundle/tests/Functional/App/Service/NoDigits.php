<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Tests\Functional\App\Service;

use Phil\Slug\Slug;
use Phil\Slug\Slugger;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * The application's decorator of a named slugger: it drops the digits of the slugs that slugger makes.
 */
#[AsDecorator('phil_slug.slugger.titles')]
final readonly class NoDigits implements Slugger
{
    public function __construct(
        #[AutowireDecorated]
        private Slugger $inner,
    ) {
    }

    public function slug(string $text): Slug
    {
        return $this->inner->slug((string) preg_replace('/\d+/', '', $text));
    }
}
