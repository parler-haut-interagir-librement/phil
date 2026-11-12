<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Benchmark;

use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * A content is checked each time an author publishes it, while the author waits: a document of 50 pages, about 25 000
 * words in 1 500 paragraphs, 300 headings, 100 images, 50 tables and some 600 links, is checked as HTML and as a UJO
 * document, whose fields are each parsed apart. The time must stay in proportion to the size of the document.
 *
 * @BeforeMethods("setUp")
 */
final class AccessibilityCheckBench
{
    private readonly AccessibilityChecker $checker;

    private string $html = '';

    private Document $document;

    public function __construct()
    {
        $this->checker = new AccessibilityChecker();
        $this->document = new Document();
    }

    public function setUp(): void
    {
        $html = '<!doctype html><html lang="en"><head><title>Book</title></head><body><h1>A book of 50 pages</h1>';
        $blocks = [];

        for ($page = 1; $page <= 50; ++$page) {
            $html .= '<section><h2>Chapter '.$page.'</h2>';
            $blocks[] = new Block('h'.$page, 'header', ['text' => 'Chapter '.$page, 'level' => 2]);

            for ($section = 1; $section <= 5; ++$section) {
                $html .= '<h3>Part '.$section.'</h3>';
                $blocks[] = new Block('h'.$page.'-'.$section, 'header', ['text' => 'Part '.$section, 'level' => 3]);

                for ($paragraph = 1; $paragraph <= 6; ++$paragraph) {
                    $text = str_repeat('Words of the story, ', 7).'with <a href="/notes/'.$page.'">the notes of page '.$page.'</a> and <span style="color: #555">a quiet aside</span>.';
                    $html .= '<p>'.$text.'</p>';
                    $blocks[] = new Block('p'.$page.'-'.$section.'-'.$paragraph, 'paragraph', ['text' => $text]);
                }
            }

            $html .= '<figure><img src="/images/'.$page.'.jpg" alt="Plate '.$page.'"><figcaption>Plate '.$page.'</figcaption></figure>';
            $html .= '<img src="/images/IMG_'.$page.'.jpg"><table><tr><th>Name</th><th>Value</th></tr><tr><td>a</td><td>1</td></tr></table></section>';
            $blocks[] = new Block('i'.$page, 'image', ['file' => ['url' => '/images/'.$page.'.jpg'], 'caption' => 'Plate '.$page]);
            $blocks[] = new Block('j'.$page, 'image', ['file' => ['url' => '/images/IMG_'.$page.'.jpg'], 'caption' => '']);
            $blocks[] = new Block('t'.$page, 'table', ['withHeadings' => true, 'content' => [['Name', 'Value'], ['a', '1']]]);
        }

        $this->html = $html.'</body></html>';
        $this->document = new Document($blocks);
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchHtml(): void
    {
        $this->checker->checkHtml($this->html);
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchUjo(): void
    {
        $this->checker->checkUjo($this->document);
    }
}
