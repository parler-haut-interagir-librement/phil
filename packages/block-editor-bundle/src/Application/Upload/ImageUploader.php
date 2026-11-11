<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Application\Upload;

use Phil\BlockEditorBundle\Application\Exception\UploadRefused;

/**
 * Keeps an image a writer drops into the editor, and says where it is now. The application writes it — over its
 * media library, a storage bucket, a directory — and names it in the configuration; without one, an image block takes
 * an address only.
 */
interface ImageUploader
{
    /**
     * @throws UploadRefused when the file is not an image the application takes, with a message for the writer
     */
    public function upload(ImageUpload $image): UploadedImage;
}
