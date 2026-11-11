<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Infrastructure\Http;

use Phil\BlockEditorBundle\Application\Exception\UploadRefused;
use Phil\BlockEditorBundle\Application\Upload\ImageUpload;
use Phil\BlockEditorBundle\Application\Upload\ImageUploader;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Receives the image a writer drops into an image block, in the `image` field of a multipart request, gives it to the
 * application's `ImageUploader`, and answers as the Editor.js image tool expects: `{"success": 1, "file": {"url": …}}`,
 * or `{"success": 0, "message": …}` with the status that says why. The current user must be granted the editor's
 * access attribute: the uploader is called for no one else.
 */
final readonly class ImageUploadController
{
    public const int MAX_BYTES = 10_485_760;

    public function __construct(
        private EditorRequest $editor,
        private ?ImageUploader $uploader = null,
        private int $maxBytes = self::MAX_BYTES,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->uploader instanceof ImageUploader) {
            return $this->failure('Images cannot be uploaded here.', 404);
        }

        if (!$this->editor->isGranted()) {
            return $this->failure('You may not use the editor.', 403);
        }

        if (!$this->editor->isTrusted($request)) {
            return $this->failure('The request does not come from the editor.', 403);
        }

        $file = $request->files->get('image');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->failure('No image was received.', 400);
        }

        $size = $file->getSize();

        if (!\is_int($size) || $size > $this->maxBytes) {
            return $this->failure('The image is too large.', 413);
        }

        try {
            $image = $this->uploader->upload(new ImageUpload($file->getPathname(), $file->getClientOriginalName(), $file->getClientMimeType(), $size));
        } catch (UploadRefused $uploadRefused) {
            return $this->failure($uploadRefused->getMessage(), 422);
        }

        return new JsonResponse(['success' => 1, 'file' => $image->file()]);
    }

    private function failure(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['success' => 0, 'message' => $message], $status);
    }
}
