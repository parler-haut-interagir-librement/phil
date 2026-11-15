<?php

declare(strict_types=1);

namespace Phil\Transcription;

use Phil\Transcription\Exception\ConsentRequired;
use Phil\Transcription\Exception\InvalidTranscription;

/**
 * A recording to transcribe: the path of its local file, its declared language, the consent of the people heard in it,
 * and words the service may expect — names, terms — as a hint.
 */
final readonly class TranscriptionRequest
{
    public const int MAX_PROMPT_BYTES = 1_000;

    /**
     * @throws InvalidTranscription when the path is empty or the hint too long
     */
    public function __construct(
        public string $path,
        public Language $language,
        public RecordingConsent $consent = RecordingConsent::LocalOnly,
        public ?string $prompt = null,
    ) {
        if ('' === $path) {
            throw new InvalidTranscription('A request names the file of its recording.');
        }

        if (null !== $prompt && \strlen($prompt) > self::MAX_PROMPT_BYTES) {
            throw new InvalidTranscription(\sprintf('A hint holds %d bytes at most.', self::MAX_PROMPT_BYTES));
        }
    }

    /**
     * Whether the recording may go where this processing sends it.
     */
    public function allows(Processing $processing): bool
    {
        return Processing::Local === $processing || RecordingConsent::Cloud === $this->consent;
    }

    /**
     * @throws ConsentRequired when the recording may not go where this processing sends it
     */
    public function assertAllows(Processing $processing): void
    {
        if (!$this->allows($processing)) {
            throw new ConsentRequired('The people heard in this recording did not agree that it leaves the site: only a local transcriber may transcribe it.');
        }
    }
}
