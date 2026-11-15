<?php

declare(strict_types=1);

namespace Phil\Transcription\Contract;

use Phil\Transcription\Exception\TranscriptionException;
use Phil\Transcription\Processing;
use Phil\Transcription\Transcript;
use Phil\Transcription\TranscriptionRequest;

/**
 * Turns a recording into a transcript to review. A transcriber that sends the recording to another company says so by
 * its processing, and refuses a request without the consent of the people heard in it.
 */
interface Transcriber
{
    public function processing(): Processing;

    /**
     * @throws TranscriptionException when consent is missing, the recording cannot be read or sent, the service fails,
     *                                or a limit is passed
     */
    public function transcribe(TranscriptionRequest $request): Transcript;
}
