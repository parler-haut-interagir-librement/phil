<?php

declare(strict_types=1);

namespace Phil\Transcription\Tests\Unit;

use Override;
use Phil\Transcription\Contract\Transcriber;
use Phil\Transcription\Deepgram\DeepgramTranscriber;
use Phil\Transcription\Exception\ConsentRequired;
use Phil\Transcription\Exception\InvalidTranscription;
use Phil\Transcription\Exception\LimitExceeded;
use Phil\Transcription\Exception\TranscriptionFailed;
use Phil\Transcription\Internal\Http;
use Phil\Transcription\Internal\Multipart;
use Phil\Transcription\Language;
use Phil\Transcription\OpenAi\OpenAiTranscriber;
use Phil\Transcription\Processing;
use Phil\Transcription\RecordingConsent;
use Phil\Transcription\ReviewState;
use Phil\Transcription\Segment;
use Phil\Transcription\Tests\Support\RecordingClient;
use Phil\Transcription\Transcript;
use Phil\Transcription\Transcription;
use Phil\Transcription\TranscriptionLimits;
use Phil\Transcription\TranscriptionRequest;
use Phil\Transcription\WhisperCpp\WhisperCppTranscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The transcribers over HTTP, each service played by a mock client: what is sent, what is refused before anything is
 * sent, and how the answers are read.
 */
#[CoversClass(WhisperCppTranscriber::class)]
#[CoversClass(OpenAiTranscriber::class)]
#[CoversClass(DeepgramTranscriber::class)]
#[CoversClass(Transcription::class)]
#[CoversClass(Http::class)]
#[CoversClass(Multipart::class)]
final class TranscriberTest extends TestCase
{
    private const string WHISPER = '{"segments":[{"start":0.5,"end":1.25,"text":" Bonjour."}]}';

    private string $recording;

    #[Override]
    protected function setUp(): void
    {
        $this->recording = sys_get_temp_dir().'/phil-transcription-'.bin2hex(random_bytes(6)).' épisode.wav';
        file_put_contents($this->recording, 'RIFF-audio');
    }

    #[Override]
    protected function tearDown(): void
    {
        @unlink($this->recording);
    }

    private function request(RecordingConsent $consent = RecordingConsent::LocalOnly, ?string $prompt = null): TranscriptionRequest
    {
        return new TranscriptionRequest($this->recording, new Language('fr-CA'), $consent, $prompt);
    }

    /**
     * @return array<string, string> the fields of a multipart body, the file under "file"
     */
    private function fields(string $body, string $contentType): array
    {
        self::assertSame(1, preg_match('/^Content-Type: multipart\/form-data; boundary=(phil-[0-9a-f]{32})$/D', $contentType, $match));
        self::assertStringEndsWith("\r\n--".$match[1]."--\r\n", $body);
        $fields = [];

        foreach (\array_slice(explode('--'.$match[1], $body), 1, -1) as $part) {
            [$head, $value] = explode("\r\n\r\n", $part, 2);
            self::assertSame(1, preg_match('/name="([^"]+)"/', $head, $name));
            $fields[$name[1]] = substr($value, 0, -2);

            if ('file' === $name[1]) {
                $fields['file:head'] = $head;
            }
        }

        return $fields;
    }

    public function testWhisperCppIsLocalAndReceivesTheRecordingAndTheLanguage(): void
    {
        $service = new RecordingClient(new MockResponse(self::WHISPER));
        $transcriber = new WhisperCppTranscriber($service->client, 'http://whisper:8080/');
        $transcript = $transcriber->transcribe($this->request(prompt: 'Gnome, Tilt'));
        $sent = $service->last();
        $fields = $this->fields($sent['body'], $sent['headers']['content-type'][0]);

        self::assertSame(Processing::Local, $transcriber->processing());
        self::assertSame('POST', $sent['method']);
        self::assertSame('http://whisper:8080/inference', $sent['url']);
        self::assertSame(['response_format' => 'verbose_json', 'language' => 'fr', 'temperature' => '0', 'prompt' => 'Gnome, Tilt', 'file' => 'RIFF-audio'], array_diff_key($fields, ['file:head' => 1]));
        self::assertSame("\r\nContent-Disposition: form-data; name=\"file\"; filename=\"".basename(str_replace(' é', '___', $this->recording))."\"\r\nContent-Type: application/octet-stream", $fields['file:head']);
        self::assertSame('whisper.cpp', $transcript->engine);
        self::assertSame([500, 1_250, 'Bonjour.'], [$transcript->segments[0]->start, $transcript->segments[0]->end, $transcript->segments[0]->text]);
        self::assertSame('fr-CA', $transcript->language->tag);
    }

    public function testWhisperCppNeedsNoConsentAndSendsNoHintUnasked(): void
    {
        $service = new RecordingClient(new MockResponse(self::WHISPER));
        new WhisperCppTranscriber($service->client, 'https://whisper.internal/base')->transcribe($this->request());
        $sent = $service->last();

        self::assertSame('https://whisper.internal/base/inference', $sent['url']);
        self::assertArrayNotHasKey('prompt', $this->fields($sent['body'], $sent['headers']['content-type'][0]));
    }

    public function testOpenAiIsRefusedWithoutConsentBeforeAnythingIsSent(): void
    {
        $service = new RecordingClient(new MockResponse(self::WHISPER));
        $transcriber = new OpenAiTranscriber($service->client, 'sk-test');

        try {
            $transcriber->transcribe(new TranscriptionRequest('/nonexistent.wav', new Language('fr')));
            self::fail('A cloud transcriber refuses a request without consent.');
        } catch (ConsentRequired) {
            self::assertSame([], $service->requests);
        }

        self::assertSame(Processing::Cloud, $transcriber->processing());
    }

    public function testOpenAiReceivesTheModelAndTheKey(): void
    {
        $service = new RecordingClient(new MockResponse(self::WHISPER));
        $transcript = new OpenAiTranscriber($service->client, 'sk-test', 'gpt-4o-transcribe')->transcribe($this->request(RecordingConsent::Cloud, 'Gnome'));
        $sent = $service->last();
        $fields = $this->fields($sent['body'], $sent['headers']['content-type'][0]);

        self::assertSame(OpenAiTranscriber::ENDPOINT, $sent['url']);
        self::assertSame(['Authorization: Bearer sk-test'], $sent['headers']['authorization']);
        self::assertSame(['model' => 'gpt-4o-transcribe', 'language' => 'fr', 'response_format' => 'verbose_json', 'timestamp_granularities[]' => 'segment', 'prompt' => 'Gnome', 'file' => 'RIFF-audio'], array_diff_key($fields, ['file:head' => 1]));
        self::assertSame('openai:gpt-4o-transcribe', $transcript->engine);
    }

    public function testOpenAiSpeaksToAnotherEndpointWithItsDefaults(): void
    {
        $service = new RecordingClient(new MockResponse(self::WHISPER));
        $transcript = new OpenAiTranscriber($service->client, 'k', endpoint: 'https://speech.example.com/v1/audio/transcriptions')->transcribe($this->request(RecordingConsent::Cloud));
        $sent = $service->last();

        self::assertSame('https://speech.example.com/v1/audio/transcriptions', $sent['url']);
        self::assertArrayNotHasKey('prompt', $this->fields($sent['body'], $sent['headers']['content-type'][0]));
        self::assertSame('openai:whisper-1', $transcript->engine);
    }

    public function testOpenAiRefusesARecordingPastTwentyFiveMegabytes(): void
    {
        $handle = fopen($this->recording, 'c');
        self::assertIsResource($handle);
        ftruncate($handle, OpenAiTranscriber::MAX_AUDIO_BYTES + 1);
        fclose($handle);
        $service = new RecordingClient(new MockResponse(self::WHISPER));

        try {
            new OpenAiTranscriber($service->client, 'k')->transcribe($this->request(RecordingConsent::Cloud));
            self::fail('A recording past the limit is refused.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame('The recording holds more than 26214400 bytes.', $limitExceeded->getMessage());
        }

        self::assertSame([], $service->requests);
    }

    public function testDeepgramReceivesTheRecordingAsItsBodyWithTheFullLanguage(): void
    {
        $service = new RecordingClient(new MockResponse((string) file_get_contents(__DIR__.'/../Fixtures/deepgram.json')));
        $transcriber = new DeepgramTranscriber($service->client, 'dg-key', diarize: true);
        $transcript = $transcriber->transcribe($this->request(RecordingConsent::Cloud, 'Gnome'));
        $sent = $service->last();

        self::assertSame(Processing::Cloud, $transcriber->processing());
        self::assertSame(DeepgramTranscriber::ENDPOINT.'?model=nova-3&language=fr-CA&utterances=true&punctuate=true&smart_format=true&diarize=true&keyterm=Gnome', $sent['url']);
        self::assertSame(['Authorization: Token dg-key'], $sent['headers']['authorization']);
        self::assertSame(['Content-Type: application/octet-stream'], $sent['headers']['content-type']);
        self::assertSame('RIFF-audio', $sent['body']);
        self::assertSame('deepgram:nova-3', $transcript->engine);
        self::assertSame('Speaker 1', $transcript->segments[0]->speaker);
    }

    public function testDeepgramWithoutDiarizationOrHint(): void
    {
        $service = new RecordingClient(new MockResponse('{"results":{"utterances":[]}}'));
        new DeepgramTranscriber($service->client, 'k', 'nova-2', endpoint: 'https://dg.example.com/v1/listen/')->transcribe($this->request(RecordingConsent::Cloud));

        self::assertSame('https://dg.example.com/v1/listen?model=nova-2&language=fr-CA&utterances=true&punctuate=true&smart_format=true&diarize=false', $service->last()['url']);
    }

    public function testDeepgramIsRefusedWithoutConsent(): void
    {
        $service = new RecordingClient();

        $this->expectException(ConsentRequired::class);
        try {
            new DeepgramTranscriber($service->client, 'k')->transcribe($this->request());
        } finally {
            self::assertSame([], $service->requests);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badAddresses(): iterable
    {
        yield 'ftp' => ['ftp://whisper'];
        yield 'no host' => ['http:///inference'];
        yield 'a space' => ['http://whisper/a b'];
        yield 'a line' => ["http://whisper\n"];
        yield 'relative' => ['/inference'];
    }

    #[DataProvider('badAddresses')]
    public function testAnAddressIsHttpOrHttps(string $address): void
    {
        $this->expectException(InvalidTranscription::class);
        $this->expectExceptionMessageIsOrContains('The address of a transcription service is an http or https address.');

        new WhisperCppTranscriber(new RecordingClient()->client, $address);
    }

    public function testAnAddressInCapitalsIsAccepted(): void
    {
        $service = new RecordingClient(new MockResponse(self::WHISPER));
        new WhisperCppTranscriber($service->client, 'HTTP://10.0.0.5:8080')->transcribe($this->request());

        self::assertSame('http://10.0.0.5:8080/inference', strtolower($service->last()['url']));
    }

    public function testAServiceThatFailsIsReportedWithoutItsBody(): void
    {
        foreach ([500, 199, 302] as $status) {
            $service = new RecordingClient(new MockResponse('secret details', ['http_code' => $status]));

            try {
                new WhisperCppTranscriber($service->client, 'http://whisper')->transcribe($this->request());
                self::fail('A failure is reported.');
            } catch (TranscriptionFailed $exception) {
                self::assertSame(\sprintf('The transcription service answered HTTP %d.', $status), $exception->getMessage());
            }
        }

        self::assertCount(1, new WhisperCppTranscriber(new RecordingClient(new MockResponse(self::WHISPER, ['http_code' => 299]))->client, 'http://w')->transcribe($this->request())->segments);
    }

    public function testAServiceThatCannotBeReachedFails(): void
    {
        $service = new RecordingClient(new MockResponse('', ['error' => 'connection refused']));

        $this->expectException(TranscriptionFailed::class);
        $this->expectExceptionMessageIsOrContains('The transcription service cannot be reached: connection refused');
        new WhisperCppTranscriber($service->client, 'http://whisper')->transcribe($this->request());
    }

    public function testAResponsePastItsBytesIsCut(): void
    {
        $service = new RecordingClient(new MockResponse([str_repeat(' ', 40), self::WHISPER]));
        $limits = new TranscriptionLimits(maxResponseBytes: 40 + \strlen(self::WHISPER));
        self::assertCount(1, new WhisperCppTranscriber($service->client, 'http://w', $limits)->transcribe($this->request())->segments);

        $service = new RecordingClient(new MockResponse([str_repeat(' ', 40), self::WHISPER]));

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The response holds more than 41 bytes.');
        new WhisperCppTranscriber($service->client, 'http://w', new TranscriptionLimits(maxResponseBytes: 41))->transcribe($this->request());
    }

    public function testARecordingThatCannotBeReadIsRefused(): void
    {
        $this->expectException(InvalidTranscription::class);
        $this->expectExceptionMessageIsOrContains('The recording cannot be read.');

        new WhisperCppTranscriber(new RecordingClient()->client, 'http://w')->transcribe(new TranscriptionRequest(sys_get_temp_dir(), new Language('fr')));
    }

    public function testALargeRecordingIsStreamedInChunks(): void
    {
        $audio = str_repeat('0123456789abcdef', 150_000);
        file_put_contents($this->recording, $audio);
        $service = new RecordingClient(new MockResponse('{"results":{"utterances":[]}}'));
        new DeepgramTranscriber($service->client, 'k')->transcribe($this->request(RecordingConsent::Cloud));

        self::assertSame($audio, $service->last()['body']);

        $file = fopen($this->recording, 'r');
        self::assertIsResource($file);
        self::assertSame([1_048_576, 1_048_576, 2_400_000 - 2_097_152], array_map(strlen(...), iterator_to_array(Http::chunks($file), false)));
        fclose($file);
    }

    public function testTheFacadeAsksForConsentBeforeTheTranscriberSeesTheRequest(): void
    {
        $cloud = new class implements Transcriber {
            public int $calls = 0;

            public function processing(): Processing
            {
                return Processing::Cloud;
            }

            public function transcribe(TranscriptionRequest $request): Transcript
            {
                ++$this->calls;

                return new Transcript($request->language, [new Segment(0, 1, 'a')], ReviewState::Reviewed, 'x');
            }
        };
        $transcription = new Transcription($cloud);

        self::assertSame(Processing::Cloud, $transcription->processing());

        try {
            $transcription->transcribe($this->request());
            self::fail('The facade refuses a request without consent.');
        } catch (ConsentRequired) {
            self::assertSame(0, $cloud->calls);
        }

        $transcript = $transcription->transcribe($this->request(RecordingConsent::Cloud));
        self::assertSame(1, $cloud->calls);
        self::assertSame(ReviewState::ToReview, $transcript->review);
        self::assertSame('x', $transcript->engine);
        self::assertCount(1, $transcript->segments);
    }

    public function testTheFacadePassesATranscriptToReviewAsItIs(): void
    {
        $service = new RecordingClient(new MockResponse(self::WHISPER));
        $transcription = new Transcription(new WhisperCppTranscriber($service->client, 'http://w'));

        self::assertSame(Processing::Local, $transcription->processing());
        self::assertSame('whisper.cpp', $transcription->transcribe($this->request())->engine);
    }
}
