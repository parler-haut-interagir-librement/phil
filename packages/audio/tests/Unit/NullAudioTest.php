<?php

declare(strict_types=1);

namespace Phil\Audio\Tests\Unit;

use Phil\Audio\AudioFormat;
use Phil\Audio\Exception\AudioToolUnavailable;
use Phil\Audio\NullAudio;
use Phil\Audio\Rendition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullAudio::class)]
final class NullAudioTest extends TestCase
{
    public function testEveryCallSaysNoToolIsConfigured(): void
    {
        $calls = [
            static function (NullAudio $audio): void { $audio->transcode('a', 'b', new Rendition(AudioFormat::Mp3)); },
            static function (NullAudio $audio): void { $audio->read('a'); },
            static function (NullAudio $audio): void { $audio->peaks('a'); },
        ];

        foreach ($calls as $call) {
            try {
                $call(new NullAudio());
                self::fail('The null adapter refuses every call.');
            } catch (AudioToolUnavailable $exception) {
                self::assertSame('No audio tool is configured: the files are served as they were uploaded.', $exception->getMessage());
            }
        }
    }
}
