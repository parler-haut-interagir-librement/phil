<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional\App;

use Override;
use Phil\UxKit\Application\Contract\PicturePropsProvider;

/**
 * The provider of the pictures of posters, which autoconfiguration tags: the files of a poster by its name, and a prop
 * that is not the provider's to give, which picture_props() drops.
 */
final readonly class PosterPictures implements PicturePropsProvider
{
    #[Override]
    public function supports(object $subject): bool
    {
        return $subject instanceof Poster;
    }

    #[Override]
    public function props(object $subject, array $options = []): array
    {
        $name = $subject instanceof Poster ? $subject->name : '';

        return [
            'src' => '/posters/'.$name.'.jpg',
            'sources' => [['type' => 'image/avif', 'srcset' => '/posters/'.$name.'.avif']],
            'alt' => 'The poster of '.$name,
            'width' => 600,
            'height' => 900,
            'credit' => $options['credit'] ?? null,
            'priority' => true,
        ];
    }
}
