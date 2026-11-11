<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Unit\Fixture;

use Override;
use Phil\BlockEditorBundle\Application\Exception\UploadRefused;
use Phil\BlockEditorBundle\Application\Upload\ImageUpload;
use Phil\BlockEditorBundle\Application\Upload\ImageUploader;
use Phil\BlockEditorBundle\Application\Upload\UploadedImage;

/**
 * Keeps what it receives, and answers with a fixed image or the refusal it was given.
 */
final class RecordingUploader implements ImageUploader
{
    /** @var list<ImageUpload> */
    public array $received = [];

    public function __construct(
        private readonly ?UploadRefused $refusal = null,
    ) {
    }

    #[Override]
    public function upload(ImageUpload $image): UploadedImage
    {
        $this->received[] = $image;

        return $this->refusal instanceof UploadRefused ? throw $this->refusal : new UploadedImage('/media/cat.png', 4, 3, ['id' => 9]);
    }
}
