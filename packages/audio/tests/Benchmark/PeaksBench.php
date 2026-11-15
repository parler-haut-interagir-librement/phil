<?php

declare(strict_types=1);

namespace Phil\Audio\Tests\Benchmark;

use Phil\Audio\Peaks;
use Phil\Audio\PeaksComputer;
use Phil\Audio\PeaksReader;

/**
 * The waveform of a track is computed once, in a background job, from its samples decoded at 8 kHz; it is read and
 * reduced to the bars of a player at each page that shows it. Ten minutes of sound — 4.8 million samples — must be
 * computed in under a second, and the peaks of an hour — 36 000 runs — read, checked and reduced to 200 bars in a few
 * tens of milliseconds.
 */
final readonly class PeaksBench
{
    private string $samples;

    private string $json;

    private Peaks $hour;

    public function __construct()
    {
        $samples = '';

        for ($index = 0; $index < 4_800; ++$index) {
            $samples .= pack('v', ($index * 7_919) % 65_536);
        }

        $this->samples = str_repeat($samples, 1_000);
        $data = [];

        for ($run = 0; $run < 36_000; ++$run) {
            $data[] = -($run % 128);
            $data[] = $run % 127;
        }

        $this->hour = new Peaks(8_000, 800, 8, $data);
        $this->json = $this->hour->toJson();
    }

    /**
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchComputeTenMinutes(): void
    {
        new PeaksComputer()->fromString($this->samples, 8_000, 800);
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchReadAnHourAndDrawTwoHundredBars(): void
    {
        new PeaksReader()->read($this->json)->amplitudes(200);
    }
}
