<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Functional\App;

use Override;
use Phil\BlockEditorBundle\Application\Exception\UploadRefused;
use Phil\BlockEditorBundle\Application\Upload\ImageUpload;
use Phil\BlockEditorBundle\Application\Upload\ImageUploader;
use Phil\BlockEditorBundle\Application\Upload\UploadedImage;

/**
 * Keeps nothing: an image named `refused.png` is refused, any other gets an address from its name.
 */
final class FakeUploader implements ImageUploader
{
    #[Override]
    public function upload(ImageUpload $image): UploadedImage
    {
        return 'refused.png' === $image->clientName ? throw new UploadRefused('Not this one.') : new UploadedImage('/media/'.$image->clientName, 2, 1);
    }
}
