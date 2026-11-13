<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit;

use Phil\Epub\Accessibility\Accessibility;
use Phil\Epub\Accessibility\AccessibilityFeature;
use Phil\Epub\Accessibility\AccessibilityHazard;
use Phil\Epub\Accessibility\AccessMode;
use Phil\Epub\Asset;
use Phil\Epub\Book;
use Phil\Epub\Chapter;
use Phil\Epub\Clip;
use Phil\Epub\Cover;
use Phil\Epub\Creator;
use Phil\Epub\EpubWriter;
use Phil\Epub\Identifier;
use Phil\Epub\LandmarkType;
use Phil\Epub\Layout;
use Phil\Epub\MediaOverlay;
use Phil\Epub\Metadata;
use Phil\Epub\Narration;
use Phil\Epub\Orientation;
use Phil\Epub\PageSpread;
use Phil\Epub\Rendition;
use Phil\Epub\Spread;
use Phil\Epub\Tests\Unit\Fixture\Books;
use Phil\Epub\Ujo\UjoChapters;
use Phil\Epub\Viewport;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The books written by the library checked by EPUBCheck, the validator of the W3C. EPUBCheck takes about twenty
 * seconds a book, so the test runs only when `EPUBCHECK` names its executable.
 */
#[CoversNothing]
#[Group('epubcheck')]
final class EpubcheckTest extends TestCase
{
    private function check(Book $book): void
    {
        $epubcheck = getenv('EPUBCHECK');

        if (false === $epubcheck || '' === $epubcheck) {
            self::markTestSkipped('EPUBCHECK does not name the EPUBCheck executable.');
        }

        $path = sys_get_temp_dir().'/phil-epub-check-'.bin2hex(random_bytes(4)).'.epub';
        new EpubWriter()->write($book, $path);

        try {
            $process = new Process([$epubcheck, '--failonwarnings', $path], timeout: 120);
            $process->run();
            self::assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        } finally {
            unlink($path);
        }
    }

    public function testAFullBookIsValid(): void
    {
        $metadata = new Metadata(
            'A Full Book',
            'en',
            Identifier::uuid(Books::UUID),
            Books::modified(),
            creators: [new Creator('Ann Writer', 'aut', 'Writer, Ann')],
            contributors: [new Creator('Bo Drawer', 'ill')],
            publisher: 'Example Press',
            description: 'Every feature of the library at once.',
            published: '2026-11-06',
            subjects: ['Testing'],
            rights: 'All rights reserved.',
            otherIdentifiers: [Identifier::isbn('9780306406157')],
            accessibility: new Accessibility(
                sufficientModes: [[AccessMode::Textual]],
                features: [AccessibilityFeature::AlternativeText, AccessibilityFeature::StructuralNavigation, AccessibilityFeature::ReadingOrder],
                hazards: [AccessibilityHazard::None],
                summary: 'Text with described images, structured headings and a page list.',
                conformsTo: 'EPUB Accessibility 1.1 - WCAG 2.2 Level AA',
            ),
        );
        $cover = Asset::image('images/cover.png', Books::PNG);
        $book = new Book($metadata)
            ->withAsset(
                Asset::stylesheet('css/book.css', '@font-face { font-family: "Book"; src: url(../fonts/book.ttf); } body { font-family: "Book", serif; }'),
                Asset::font('fonts/book.ttf', str_repeat("\0\1\0\0", 400), true),
                Asset::image('images/map.png', Books::PNG),
                Asset::image('images/rune.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2 2"><rect width="2" height="2"/></svg>'),
            )
            ->withCover(new Cover($cover, 'A plain cover'))
            ->withNavigationInSpine()
            ->withChapter(
                new Chapter('<h1>A Full Book</h1><p>By Ann Writer</p>', landmark: LandmarkType::TitlePage, inToc: false),
                new Chapter('<span role="doc-pagebreak" aria-label="1"></span><h1 id="one">One</h1><p onclick="x()">Text with <b>bold</b>, a <a href="#rules">link</a>, <a href="https://example.com/">a site</a> and <a href="/local">a local address</a>.<br>Next line.</p><figure><img src="images/map.png" alt="A map" loading="lazy" srcset="x 2x"><figcaption>The map</figcaption></figure><img src="images/rune.svg" alt="A rune"><img src="https://example.com/remote.png" alt="A remote image"><svg viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg><table><tr><th>A</th><td>1</td></tr></table><script>alert(1)</script>'),
                new Chapter('<h1>Two</h1><span role="doc-pagebreak" aria-label="2"></span><h2 id="rules">Rules</h2><p>x = <math><mi>x</mi></math></p><aside><h3>Aside</h3></aside><dl><dt>Term</dt><dd>Definition</dd></dl>', fileName: 'text/two.xhtml'),
            );

        $this->check($book);
    }

    public function testAUjoDocumentIsValid(): void
    {
        $json = (string) json_encode(['blocks' => [
            ['id' => 't', 'type' => 'toc', 'data' => ['depth' => 2]],
            ['id' => 'h1', 'type' => 'header', 'data' => ['text' => 'Chapter One', 'level' => 1]],
            ['id' => 'p1', 'type' => 'paragraph', 'data' => ['text' => 'Hello <b>there</b> &amp; welcome.']],
            ['id' => 'b1', 'type' => 'pageBreak', 'data' => []],
            ['id' => 'h2', 'type' => 'header', 'data' => ['text' => 'Chapter Two', 'level' => 1]],
            ['id' => 'l1', 'type' => 'list', 'data' => ['style' => 'ordered', 'items' => [['content' => 'First', 'items' => []]]]],
            ['id' => 'q1', 'type' => 'quote', 'data' => ['text' => 'A quote', 'caption' => 'Someone', 'alignment' => 'left']],
            ['id' => 'c1', 'type' => 'code', 'data' => ['code' => "echo 'a';\n"]],
            ['id' => 'd1', 'type' => 'delimiter', 'data' => []],
            ['id' => 'i1', 'type' => 'image', 'data' => ['file' => ['url' => 'https://example.com/a.png'], 'caption' => 'An image']],
            ['id' => 'w1', 'type' => 'warning', 'data' => ['title' => 'Careful', 'message' => 'Hot']],
            ['id' => 'tb', 'type' => 'table', 'data' => ['withHeadings' => true, 'content' => [['A', 'B'], ['1', '2']]]],
        ]]);

        $this->check(Books::book()->withChapter(...new UjoChapters()->chapters($json)));
    }

    public function testANarratedBookWithMediaAndAnEpub2TableOfContentsIsValid(): void
    {
        $mp3 = Asset::audio('audio/one.mp3', Books::MP3.str_repeat("\xFF\xFB\x90\x00".str_repeat("\0", 413), 3));
        $book = Books::book()
            ->withAsset(
                Asset::stylesheet('css/book.css', '.reading { background: yellow; }'),
                $mp3,
                Asset::audio('audio/two.flac', 'fLaC', fallback: Asset::audio('audio/two.mp3', $mp3->bytes)),
                Asset::video('video/clip.mp4', Books::MP4),
                Asset::textTrack('video/clip.vtt', Books::VTT),
            )
            ->withNarration(new Narration(['Ann Reader'], 'reading'))
            ->withNcx()
            ->withChapter(
                new Chapter(
                    '<h1 id="title">One</h1><p id="p1">Hello.</p><span role="doc-pagebreak" aria-label="1"></span><audio controls="" src="audio/two.flac">Listen</audio><video controls="" src="video/clip.mp4"><track kind="captions" src="video/clip.vtt" srclang="en" label="English"/></video>',
                    overlay: new MediaOverlay(new Clip('title', 'audio/one.mp3', 0, 1000), new Clip('p1', 'audio/one.mp3', 1000, 2500)),
                ),
                new Chapter('<h1>Two</h1><h2>Part</h2><p>Text.</p>', fileName: 'text/two.xhtml'),
            );

        $this->check($book);
    }

    public function testAFixedLayoutBookIsValid(): void
    {
        $book = Books::book()
            ->withRendition(Rendition::fixed(1200, 1600, Orientation::Portrait, Spread::Landscape))
            ->withCover(new Cover(Asset::image('images/cover.png', Books::PNG), 'A plain cover'))
            ->withNavigationInSpine()
            ->withChapter(
                new Chapter('<h1>Page one</h1>', pageSpread: PageSpread::Right),
                new Chapter('<h1>Page two</h1>', viewport: new Viewport(600, 800), pageSpread: PageSpread::Left),
                new Chapter('<h1>Notes</h1><p>Reflowed.</p>', layout: Layout::Reflowable),
            );

        $this->check($book);
    }
}
