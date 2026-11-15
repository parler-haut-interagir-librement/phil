<?php

declare(strict_types=1);

namespace Phil\Transcription;

use Override;
use Phil\Transcription\Contract\Transcriber;

/**
 * The transcriber an application calls: it asks the consent of the people heard in a recording before any transcriber
 * it wraps, local or not, sees the request, and leaves every transcript to review.
 */
final readonly class Transcription implements Transcriber
{
    public function __construct(
        private Transcriber $transcriber,
    ) {
    }

    #[Override]
    public function processing(): Processing
    {
        return $this->transcriber->processing();
    }

    #[Override]
    public function transcribe(TranscriptionRequest $request): Transcript
    {
        $request->assertAllows($this->transcriber->processing());
        $transcript = $this->transcriber->transcribe($request);

        return ReviewState::ToReview === $transcript->review ? $transcript : $transcript->withSegments($transcript->segments);
    }
}
