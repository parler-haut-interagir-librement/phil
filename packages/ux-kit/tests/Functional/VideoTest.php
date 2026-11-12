<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Twig\Error\RuntimeError;

/**
 * A video that waits for its reader: the controls="" of the browser, nothing fetched before it is played, its poster, its
 * text tracks and its transcript.
 */
#[CoversNothing]
final class VideoTest extends KitTestCase
{
    public function testItWaitsForTheReaderWithItsPosterSourcesAndTracks(): void
    {
        self::assertSame(
            '<figure><video id="talk" class="isVideo wide" controls="" preload="none" playsinline="" width="1280" height="720" poster="/talk.jpg" aria-describedby="talk-transcript">'
            .'<source src="/talk.webm" type="video/webm"><source src="/talk.mp4" type="video/mp4">'
            .'<track kind="captions" src="/talk.en.vtt" srclang="en" label="English">'
            .'<track kind="subtitles" src="/talk.fr.vtt" srclang="fr-CA" label="Français" default="">'
            .'<a href="/talk.webm">Download</a></video>'
            .'<details class="isDescription"><summary>Transcript</summary><p id="talk-transcript">We open the doors.</p></details>'
            .'<figcaption>The opening talk<small class="isCredit">Ana Lima</small></figcaption></figure>',
            $this->render('<twig:Video id="talk" class="wide" :sources="[{src: \'/talk.webm\', type: \'video/webm\'}, {src: \'/talk.mp4\', type: \'video/mp4\'}]" poster="/talk.jpg" :width="1280" :height="720" :tracks="[{src: \'/talk.en.vtt\', srclang: \'en\', label: \'English\'}, {src: \'/talk.fr.vtt\', srclang: \'fr-CA\', label: \'Français\', kind: \'subtitles\', default: true}]" fallbackLabel="Download" caption="The opening talk" credit="Ana Lima" transcript="We open the doors." />'),
        );
    }

    public function testASingleSourceWithoutCaptionIsTheVideoAlone(): void
    {
        self::assertSame(
            '<video class="isVideo" controls="" preload="metadata" playsinline="" width="320" height="180" muted="" loop="" aria-label="The field"><source src="/clip.webm"><a href="/clip.webm">Download the video</a></video>',
            $this->render('<twig:Video src="/clip.webm" :width="320" :height="180" preload="metadata" :muted="true" :loop="true" label="The field" />'),
        );
        self::assertStringContainsString(' preload="none" ', $this->render('<twig:Video src="/clip.webm" :width="320" :height="180" preload="auto" />'), 'never fetched whole before it is played');
        self::assertStringNotContainsString('autoplay', $this->render('<twig:Video src="/clip.webm" :width="320" :height="180" autoplay />'), 'never played on its own');
    }

    public function testARefusedSourceTrackOrPosterIsLeftOut(): void
    {
        self::assertSame(
            '<video class="isVideo" controls="" preload="none" playsinline="" width="8" height="6"><source src="/kept.mp4" type="video/mp4"><track kind="chapters" src="/chapters.vtt" srclang="en" label="Chapters"><a href="/kept.mp4">Download the video</a></video>',
            $this->render('<twig:Video :width="8" :height="6" poster="javascript:alert(1)" :sources="[
                {src: \'data:video/mp4;base64,AAAA\'},
                {src: \'https://tracker.example.com/v.mp4\'},
                {src: \'/image.png\', type: \'image/png\'},
                {src: \'\'},
                {src: \'/kept.mp4\', type: \'video/mp4\'},
            ]" :tracks="[
                {src: \'data:text/vtt,WEBVTT\', srclang: \'en\', label: \'Inline\'},
                {src: \'https://tracker.example.com/t.vtt\', srclang: \'en\', label: \'Elsewhere\'},
                {src: \'/a.vtt\', srclang: \'en us\', label: \'Bad language\'},
                {src: \'/b.vtt\', srclang: \'en\', label: \'\'},
                {src: \'/c.vtt\', srclang: \'en\', label: \'Metadata\', kind: \'metadata\'},
                {src: \'/chapters.vtt\', srclang: \'en\', label: \'Chapters\', kind: \'chapters\'},
            ]" />'),
        );
        self::assertStringContainsString(
            ' poster="data:image/svg+xml,%3Csvg%3E">',
            $this->render('<twig:Video src="/clip.webm" :width="8" :height="6" poster="data:image/svg+xml,%3Csvg%3E" />'),
            'an inline image is a poster',
        );
        self::assertStringNotContainsString('poster', $this->render('<twig:Video src="/clip.webm" :width="8" :height="6" poster="https://tracker.example.com/p.jpg" />'));
    }

    public function testWithoutASourceLeftNothingIsDrawn(): void
    {
        self::assertSame('', $this->render('<twig:Video :width="8" :height="6" caption="Lost" />'));
        self::assertSame('', $this->render('<twig:Video src="vbscript:x" :width="8" :height="6" />'));
    }

    public function testWithoutAnIdentifierTheTranscriptHasOneOfItsOwn(): void
    {
        self::assertMatchesRegularExpression(
            '#^<figure><video class="isVideo" controls="" preload="none" playsinline="" width="8" height="6" aria-describedby="(video-transcript-\d+)"><source src="/a.webm"><a href="/a.webm">Download the video</a></video><details class="isDescription"><summary>What is said</summary><p id="\1">Words</p></details></figure>$#',
            $this->render('<twig:Video src="/a.webm" :width="8" :height="6" transcript="Words" transcriptLabel="What is said" />'),
        );
    }

    public function testTheSizeIsRequired(): void
    {
        $this->expectException(RuntimeError::class);

        $this->render('<twig:Video src="/a.webm" />');
    }
}
