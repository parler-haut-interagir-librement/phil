<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit\Html;

use Override;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\AttributeValue;
use Phil\Ujo\Html\BlockRenderer;
use Phil\Ujo\Html\HtmlFilter;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Html\InlineProfile;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Html\InlineTool;
use Phil\Ujo\Html\LanguageTag;
use Phil\Ujo\Html\Renderer\CodeRenderer;
use Phil\Ujo\Html\Renderer\LayoutRenderer;
use Phil\Ujo\Html\Renderer\ListRenderer;
use Phil\Ujo\Html\Renderer\MediaRenderer;
use Phil\Ujo\Html\Renderer\TextRenderer;
use Phil\Ujo\Html\Rendering;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Schema\Blocks;
use Phil\Ujo\Schema\Boolean;
use Phil\Ujo\Schema\Choice;
use Phil\Ujo\Schema\Context;
use Phil\Ujo\Schema\Dictionary;
use Phil\Ujo\Schema\Html;
use Phil\Ujo\Schema\Integer;
use Phil\Ujo\Schema\Lazy;
use Phil\Ujo\Schema\Link;
use Phil\Ujo\Schema\ListOf;
use Phil\Ujo\Schema\Plain;
use Phil\Ujo\Schema\Shape;
use Phil\Ujo\Schema\TextOrShape;
use Phil\Ujo\Tests\Unit\UjoTestCase;
use Phil\Ujo\Text\PlainText;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\CommunityTypes;
use Phil\Ujo\Type\EmbedServices;
use Phil\Ujo\Type\LayoutTypes;
use Phil\Ujo\Type\MediaTypes;
use Phil\Ujo\Type\StandardTypes;
use Phil\Ujo\Type\TableGrid;
use Phil\Ujo\Type\TuneRegistry;
use Phil\Ujo\Type\Tunes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(HtmlRenderer::class)]
#[CoversClass(Rendering::class)]
#[CoversClass(TextRenderer::class)]
#[CoversClass(CodeRenderer::class)]
#[CoversClass(ListRenderer::class)]
#[CoversClass(MediaRenderer::class)]
#[CoversClass(LayoutRenderer::class)]
#[CoversClass(PlainText::class)]
#[CoversClass(Normalizer::class)]
#[CoversClass(Context::class)]
#[CoversClass(Shape::class)]
#[CoversClass(ListOf::class)]
#[CoversClass(Integer::class)]
#[CoversClass(Plain::class)]
#[CoversClass(Choice::class)]
#[CoversClass(Link::class)]
#[CoversClass(Html::class)]
#[CoversClass(Blocks::class)]
#[CoversClass(Lazy::class)]
#[CoversClass(Boolean::class)]
#[CoversClass(StandardTypes::class)]
#[CoversClass(CommunityTypes::class)]
#[CoversClass(LayoutTypes::class)]
#[CoversClass(Tunes::class)]
#[CoversClass(BlockTypeRegistry::class)]
#[CoversClass(TuneRegistry::class)]
#[CoversClass(EmbedServices::class)]
#[CoversClass(InlineSanitizer::class)]
#[CoversClass(InlineProfile::class)]
#[CoversClass(InlineTool::class)]
#[CoversClass(Block::class)]
#[CoversClass(Document::class)]
#[CoversClass(MediaTypes::class)]
#[CoversClass(TableGrid::class)]
#[CoversClass(TextOrShape::class)]
#[CoversClass(Dictionary::class)]
#[CoversClass(LanguageTag::class)]
#[CoversClass(AttributeValue::class)]
final class RenderingTest extends UjoTestCase
{
    /**
     * @return iterable<string, array{string, array<array-key, mixed>, array<string, mixed>, string, string}>
     */
    public static function blocks(): iterable
    {
        yield 'paragraph' => ['paragraph', ['text' => 'A <b>b</b> & c'], [], '<p class="ujo-paragraph">A <b>b</b> &amp; c</p>', 'A b & c'];
        yield 'header' => ['header', ['text' => 'T <i>i</i>', 'level' => 3], [], '<h3 id="b" class="ujo-header">T <i>i</i></h3>', 'T i'];
        yield 'header with an anchor' => ['header', ['text' => 'T'], ['anchor' => ['anchor' => 'intro']], '<h2 id="intro" class="ujo-header">T</h2>', 'T'];
        yield 'unordered list' => ['list', ['style' => 'unordered', 'items' => [['content' => 'a', 'items' => [['content' => 'b']]], ['content' => 'c']]], [], '<ul class="ujo-list ujo-list--unordered"><li>a<ul><li>b</li></ul></li><li>c</li></ul>', "- a\n  - b\n- c"];
        yield 'ordered list' => ['list', ['style' => 'ordered', 'meta' => ['start' => 3, 'counterType' => 'lower-roman'], 'items' => [['content' => 'a', 'items' => [['content' => 'b']]], ['content' => 'c']]], [], '<ol class="ujo-list ujo-list--ordered" start="3" type="i"><li>a<ol><li>b</li></ol></li><li>c</li></ol>', "1. a\n  1. b\n2. c"];
        yield 'ordered list from one' => ['list', ['style' => 'ordered', 'meta' => ['start' => 1, 'counterType' => 'numeric'], 'items' => [['content' => 'a']]], [], '<ol class="ujo-list ujo-list--ordered"><li>a</li></ol>', '1. a'];
        yield 'checklist' => ['list', ['style' => 'checklist', 'items' => [['content' => 'a', 'meta' => ['checked' => true], 'items' => [['content' => 'b']]], ['content' => 'c']]], [], '<ul class="ujo-list ujo-list--checklist"><li data-checked="true">a<ul><li data-checked="false">b</li></ul></li><li data-checked="false">c</li></ul>', "[x] a\n  [ ] b\n[ ] c"];
        yield 'quote' => ['quote', ['text' => 'Q', 'caption' => 'C <i>B</i>', 'alignment' => 'center'], [], '<figure class="ujo-quote ujo-align-center"><blockquote>Q</blockquote><figcaption>C <i>B</i></figcaption></figure>', 'Q — C B'];
        yield 'quote without caption' => ['quote', ['text' => 'Q'], [], '<figure class="ujo-quote"><blockquote>Q</blockquote></figure>', 'Q'];
        yield 'warning' => ['warning', ['title' => 'Note:', 'message' => 'M'], [], '<aside class="ujo-warning" role="note"><p class="ujo-warning__title">Note:</p><p class="ujo-warning__message">M</p></aside>', 'Note: M'];
        yield 'warning without title' => ['warning', ['message' => 'M'], [], '<aside class="ujo-warning" role="note"><p class="ujo-warning__message">M</p></aside>', 'M'];
        yield 'delimiter' => ['delimiter', [], [], '<hr class="ujo-delimiter">', '***'];
        yield 'styled delimiter' => ['delimiter', ['style' => 'star'], [], '<hr class="ujo-delimiter ujo-delimiter--star">', '***'];
        yield 'code' => ['code', ['code' => "<?php\necho 1;", 'language' => 'php'], [], "<pre class=\"ujo-code\"><code class=\"language-php\">&lt;?php\necho 1;</code></pre>", "<?php\necho 1;"];
        yield 'code without language' => ['code', ['code' => 'a < b'], [], '<pre class="ujo-code"><code>a &lt; b</code></pre>', 'a < b'];
        yield 'raw' => ['raw', ['html' => '<div class="x"><p>a</p><script>x</script></div>'], [], '<div class="ujo-raw"><div><p>a</p></div></div>', 'a'];
        yield 'table' => ['table', ['withHeadings' => true, 'stretched' => true, 'content' => [['H1', 'H2'], ['<b>a</b>', 'b']]], [], '<table class="ujo-table ujo-table--stretched"><thead><tr><th>H1</th><th>H2</th></tr></thead><tbody><tr><td><b>a</b></td><td>b</td></tr></tbody></table>', "H1 | H2\na | b"];
        yield 'table without headings' => ['table', ['content' => [['a', 'b']]], [], '<table class="ujo-table"><tbody><tr><td>a</td><td>b</td></tr></tbody></table>', 'a | b'];
        yield 'empty table with headings' => ['table', ['withHeadings' => true], [], '<table class="ujo-table"><tbody></tbody></table>', ''];
        yield 'image' => ['image', ['file' => ['url' => 'https://cdn.example.com/a.png'], 'caption' => 'A <b>cat</b>', 'withBorder' => true, 'withBackground' => true, 'stretched' => true, 'unsplash' => ['author' => 'Ann', 'profileLink' => 'https://unsplash.com/@ann']], [], '<figure class="ujo-image ujo-image--border ujo-image--background ujo-image--stretched"><img src="https://cdn.example.com/a.png" alt="A cat" loading="lazy"><figcaption>A <b>cat</b> <small class="ujo-image__credit"><a href="https://unsplash.com/@ann" rel="noopener">Ann</a></small></figcaption></figure>', 'A cat'];
        yield 'image without caption' => ['image', ['file' => ['url' => '/a.png']], [], '<figure class="ujo-image"><img src="/a.png" alt="" loading="lazy"></figure>', ''];
        yield 'image with its own alt' => ['image', ['file' => ['url' => '/a.png'], 'caption' => 'A <b>cat</b>', 'alt' => 'Tabby "Tom"'], [], '<figure class="ujo-image"><img src="/a.png" alt="Tabby &quot;Tom&quot;" loading="lazy"><figcaption>A <b>cat</b></figcaption></figure>', 'A cat'];
        yield 'image with an alt and no caption' => ['image', ['file' => ['url' => '/a.png'], 'alt' => 'Tabby'], [], '<figure class="ujo-image"><img src="/a.png" alt="Tabby" loading="lazy"></figure>', 'Tabby'];
        yield 'decorative image with a caption' => ['image', ['file' => ['url' => '/a.png'], 'caption' => 'C', 'alt' => ''], [], '<figure class="ujo-image"><img src="/a.png" alt="" loading="lazy"><figcaption>C</figcaption></figure>', 'C'];
        yield 'linkTool' => ['linkTool', ['link' => 'https://example.com', 'meta' => ['title' => 'Ex', 'site_name' => 'Site', 'description' => 'D', 'image' => ['url' => '/i.png']]], [], '<a class="ujo-link-tool" href="https://example.com" rel="noopener nofollow"><img class="ujo-link-tool__image" src="/i.png" alt="" loading="lazy"><span class="ujo-link-tool__title">Ex</span><span class="ujo-link-tool__description">D</span><span class="ujo-link-tool__site">Site</span></a>', "Ex\nhttps://example.com"];
        yield 'bare linkTool' => ['linkTool', ['link' => 'https://example.com'], [], '<a class="ujo-link-tool" href="https://example.com" rel="noopener nofollow"><span class="ujo-link-tool__title">https://example.com</span></a>', 'https://example.com'];
        yield 'attaches' => ['attaches', ['file' => ['url' => '/f.pdf', 'size' => 91, 'name' => 'f.pdf', 'extension' => 'pdf'], 'title' => 'The file'], [], '<a class="ujo-attaches" href="/f.pdf" download data-size="91" data-extension="pdf">The file</a>', 'The file'];
        yield 'attaches by name' => ['attaches', ['file' => ['url' => '/f.pdf', 'name' => 'f.pdf']], [], '<a class="ujo-attaches" href="/f.pdf" download>f.pdf</a>', 'f.pdf'];
        yield 'attaches by address' => ['attaches', ['file' => ['url' => '/f.pdf']], [], '<a class="ujo-attaches" href="/f.pdf" download>/f.pdf</a>', ''];
        yield 'embed' => ['embed', ['service' => 'youtube', 'source' => 'https://youtu.be/x', 'embed' => 'https://www.youtube.com/embed/x', 'width' => 580, 'height' => 320, 'caption' => 'Clip'], [], '<figure class="ujo-embed ujo-embed--youtube"><iframe src="https://www.youtube.com/embed/x" title="youtube" width="580" height="320" loading="lazy" allowfullscreen sandbox="allow-scripts allow-same-origin allow-popups allow-presentation" referrerpolicy="strict-origin-when-cross-origin"></iframe><a class="ujo-print-fallback" href="https://youtu.be/x"><span class="ujo-print-fallback__url">https://youtu.be/x</span></a><figcaption>Clip</figcaption></figure>', 'Clip'];
        yield 'embed without size' => ['embed', ['service' => 'vimeo', 'source' => 'https://vimeo.com/1', 'embed' => 'https://player.vimeo.com/video/1'], [], '<figure class="ujo-embed ujo-embed--vimeo"><iframe src="https://player.vimeo.com/video/1" title="vimeo" loading="lazy" allowfullscreen sandbox="allow-scripts allow-same-origin allow-popups allow-presentation" referrerpolicy="strict-origin-when-cross-origin"></iframe><a class="ujo-print-fallback" href="https://vimeo.com/1"><span class="ujo-print-fallback__url">https://vimeo.com/1</span></a></figure>', ''];
        yield 'personality' => ['personality', ['name' => 'Ada', 'description' => 'D', 'link' => 'https://ada.example', 'photo' => '/ada.png'], [], '<div class="ujo-personality"><img class="ujo-personality__photo" src="/ada.png" alt="" loading="lazy"><p class="ujo-personality__name"><a href="https://ada.example" rel="noopener">Ada</a></p><p class="ujo-personality__description">D</p></div>', 'Ada — D'];
        yield 'bare personality' => ['personality', ['name' => 'Ada'], [], '<div class="ujo-personality"><p class="ujo-personality__name">Ada</p></div>', 'Ada'];
        yield 'alert' => ['alert', ['type' => 'danger', 'align' => 'center', 'text' => 'A'], [], '<div class="ujo-alert ujo-alert--danger ujo-align-center" role="alert">A</div>', 'A'];
        yield 'toggle' => ['toggle', ['text' => 'T', 'open' => true, 'blocks' => [['id' => 'i', 'type' => 'paragraph', 'data' => ['text' => 'in']]]], [], '<details class="ujo-toggle" open><summary>T</summary><p class="ujo-paragraph">in</p></details>', "T\n\nin"];
        yield 'closed toggle' => ['toggle', ['text' => 'T'], [], '<details class="ujo-toggle"><summary>T</summary></details>', 'T'];
        yield 'columns' => ['columns', ['cols' => [['blocks' => [['id' => 'i', 'type' => 'paragraph', 'data' => ['text' => 'a']]]], ['blocks' => [['id' => 'j', 'type' => 'paragraph', 'data' => ['text' => 'b']]]]]], [], '<div class="ujo-columns ujo-columns--2"><div class="ujo-column"><p class="ujo-paragraph">a</p></div><div class="ujo-column"><p class="ujo-paragraph">b</p></div></div>', "a\n\nb"];
        yield 'math' => ['math', ['latex' => 'a<b'], [], '<div class="ujo-math">a&lt;b</div>', 'a<b'];
        yield 'math in MathML' => ['math', ['latex' => 'x', 'mathml' => '<math display="block"><mi>x</mi><script>y</script></math>'], [], '<div class="ujo-math"><math display="block"><mi>x</mi></math></div>', 'x'];
        yield 'math in MathML alone' => ['math', ['mathml' => '<math><mi>y</mi></math>'], [], '<div class="ujo-math"><math><mi>y</mi></math></div>', 'y'];
        yield 'embed with its fallback for paper' => ['embed', ['service' => 'vimeo', 'source' => 'https://vimeo.com/1', 'embed' => 'https://player.vimeo.com/video/1', 'poster' => '/p.jpg', 'title' => 'Trailer', 'qrCode' => true], [], '<figure class="ujo-embed ujo-embed--vimeo"><iframe src="https://player.vimeo.com/video/1" title="vimeo" loading="lazy" allowfullscreen sandbox="allow-scripts allow-same-origin allow-popups allow-presentation" referrerpolicy="strict-origin-when-cross-origin"></iframe><a class="ujo-print-fallback" href="https://vimeo.com/1"><img class="ujo-print-fallback__poster" src="/p.jpg" alt="" loading="lazy"><span class="ujo-print-fallback__title">Trailer</span><span class="ujo-print-fallback__url">https://vimeo.com/1</span></a><div class="ujo-print-fallback ujo-qr-code" data-qr-code="https://vimeo.com/1">https://vimeo.com/1</div></figure>', 'Trailer'];
        yield 'video' => ['video', ['sources' => [['url' => '/v.webm', 'type' => 'video/webm'], ['url' => '/v.mp4']], 'poster' => '/p.jpg', 'tracks' => [['url' => '/fr.vtt', 'kind' => 'captions', 'language' => 'fr-CA', 'label' => 'Français', 'default' => true], ['url' => '/en.vtt']], 'loop' => true, 'muted' => true, 'width' => 640, 'height' => 360, 'title' => 'Clip', 'caption' => '<b>A</b> clip', 'qrCode' => false], [], '<figure class="ujo-video"><video controls loop muted poster="/p.jpg" width="640" height="360" preload="metadata" title="Clip"><source src="/v.webm" type="video/webm"><source src="/v.mp4"><track kind="captions" src="/fr.vtt" srclang="fr-CA" label="Français" default><track kind="subtitles" src="/en.vtt"></video><a class="ujo-print-fallback" href="/v.webm"><img class="ujo-print-fallback__poster" src="/p.jpg" alt="" loading="lazy"><span class="ujo-print-fallback__title">Clip</span><span class="ujo-print-fallback__url">/v.webm</span></a><figcaption><b>A</b> clip</figcaption></figure>', "Clip\nA clip"];
        yield 'audio' => ['audio', ['sources' => [['url' => 'https://example.com/a.ogg']], 'controls' => false, 'qrCode' => true], [], '<figure class="ujo-audio"><audio preload="metadata"><source src="https://example.com/a.ogg"></audio><a class="ujo-print-fallback" href="https://example.com/a.ogg"><span class="ujo-print-fallback__url">https://example.com/a.ogg</span></a><div class="ujo-print-fallback ujo-qr-code" data-qr-code="https://example.com/a.ogg">https://example.com/a.ogg</div></figure>', ''];
        yield 'rich table' => ['table', ['withHeadings' => true, 'caption' => 'Prices', 'align' => ['', 'right'], 'content' => [[['text' => 'Item', 'colspan' => 2]], [['text' => 'Sword', 'header' => true, 'rowspan' => 2], ['text' => '10', 'align' => 'center']], ['12'], [['text' => 'Shield', 'header' => true], '5']]], [], '<table class="ujo-table"><caption class="ujo-table__caption">Prices</caption><thead><tr><th colspan="2" scope="colgroup">Item</th></tr></thead><tbody><tr><th rowspan="2" scope="rowgroup">Sword</th><td class="ujo-align-center">10</td></tr><tr><td class="ujo-align-right">12</td></tr><tr><th scope="row">Shield</th><td class="ujo-align-right">5</td></tr></tbody></table>', "Prices\nItem\nSword | 10\n12\nShield | 5"];
        yield 'rich table head' => ['table', ['withHeadings' => true, 'content' => [[['text' => 'A'], 'B']]], [], '<table class="ujo-table"><thead><tr><th scope="col">A</th><th>B</th></tr></thead><tbody></tbody></table>', 'A | B'];
        yield 'abbreviations' => ['columns', ['cols' => [['blocks' => [
            ['id' => 'p', 'type' => 'paragraph', 'data' => ['text' => 'An <abbr title="Non-player character">NPC</abbr>, a <abbr title="Game master">gm</abbr>, an <abbr title="Other">NPC</abbr> and <abbr title="Armour &amp; class">AC</abbr><abbr title="x"> </abbr>.']],
            ['id' => 'l', 'type' => 'list', 'data' => ['style' => 'unordered', 'items' => [['content' => '<abbr title="Hit points">HP</abbr>']]]],
            ['id' => 'a', 'type' => 'abbreviations', 'data' => ['title' => 'Abbreviations']],
        ]]]], [], '<div class="ujo-columns ujo-columns--1"><div class="ujo-column"><p class="ujo-paragraph">An <abbr title="Non-player character">NPC</abbr>, a <abbr title="Game master">gm</abbr>, an <abbr title="Other">NPC</abbr> and <abbr title="Armour &amp; class">AC</abbr><abbr title="x"> </abbr>.</p>
<ul class="ujo-list ujo-list--unordered"><li><abbr title="Hit points">HP</abbr></li></ul>
<section class="ujo-abbreviations"><p class="ujo-abbreviations__title">Abbreviations</p><dl><dt><abbr title="Armour &amp; class">AC</abbr></dt><dd>Armour &amp; class</dd><dt><abbr title="Game master">gm</abbr></dt><dd>Game master</dd><dt><abbr title="Hit points">HP</abbr></dt><dd>Hit points</dd><dt><abbr title="Non-player character">NPC</abbr></dt><dd>Non-player character</dd></dl></section></div></div>', "An NPC, a gm, an NPC and AC .\n\n- HP"];
        yield 'no abbreviations' => ['abbreviations', [], [], '<section class="ujo-abbreviations"></section>', ''];
        yield 'language, data and note tunes' => ['paragraph', ['text' => 't'], ['language' => ['language' => 'ar', 'direction' => 'rtl'], 'data' => ['source' => 'import', 'toc' => 'x'], 'note' => ['text' => 'Check the translation']], '<p class="ujo-paragraph" lang="ar" dir="rtl">t</p>', 't'];
        yield 'mermaid' => ['mermaid', ['code' => 'A-->B', 'caption' => 'Flow'], [], '<figure class="ujo-mermaid"><pre class="mermaid">A--&gt;B</pre><figcaption>Flow</figcaption></figure>', 'A-->B'];
        yield 'chart' => ['chart', ['type' => 'bar', 'data' => ['labels' => ['é/"']]], [], '<div class="ujo-chart" data-chart="{&quot;type&quot;:&quot;bar&quot;,&quot;data&quot;:{&quot;labels&quot;:[&quot;é/\\&quot;&quot;]}}"></div>', ''];
        yield 'button' => ['button', ['link' => '/go', 'text' => 'Go'], [], '<a class="ujo-button" href="/go">Go</a>', 'Go (/go)'];
        yield 'gallery' => ['gallery', ['files' => [['url' => '/a.png', 'caption' => 'A'], ['url' => '/b.png']], 'style' => 'slider'], [], '<div class="ujo-gallery ujo-gallery--slider"><figure><img src="/a.png" alt="A" loading="lazy"><figcaption>A</figcaption></figure><figure><img src="/b.png" alt="" loading="lazy"></figure></div>', 'A'];
        yield 'pageBreak' => ['pageBreak', [], [], '<div class="ujo-page-break" aria-hidden="true"></div>', ''];
        yield 'columnBreak' => ['columnBreak', [], [], '<div class="ujo-column-break" aria-hidden="true"></div>', ''];
        yield 'pageStyle' => ['pageStyle', ['variant' => 'frontCover', 'classes' => ['dark', 'big']], [], '<div class="ujo-page-style dark big" aria-hidden="true" data-variant="frontCover"></div>', ''];
        yield 'bare pageStyle' => ['pageStyle', [], [], '<div class="ujo-page-style" aria-hidden="true"></div>', ''];
        yield 'wide' => ['wide', ['blocks' => [['id' => 'i', 'type' => 'paragraph', 'data' => ['text' => 'w']]]], [], '<div class="ujo-wide"><p class="ujo-paragraph">w</p></div>', 'w'];
        yield 'columnFlow' => ['columnFlow', ['count' => 3, 'blocks' => [['id' => 'i', 'type' => 'paragraph', 'data' => ['text' => 'f']]]], [], '<div class="ujo-column-flow ujo-column-flow--3"><p class="ujo-paragraph">f</p></div>', 'f'];
        yield 'spacer' => ['spacer', ['lines' => 3], [], '<div class="ujo-spacer" aria-hidden="true" data-lines="3"></div>', ''];
        yield 'pageNumber' => ['pageNumber', ['mode' => 'fixed', 'value' => '12'], [], '<span class="ujo-page-number" data-mode="fixed">12</span>', ''];
        yield 'automatic pageNumber' => ['pageNumber', ['value' => '12'], [], '<span class="ujo-page-number" data-mode="auto"></span>', ''];
        yield 'runningFooter' => ['runningFooter', ['text' => 'PART <b>1</b>'], [], '<div class="ujo-running-footer">PART <b>1</b></div>', 'PART 1'];
        yield 'footnote' => ['footnote', ['id' => 'fn1', 'text' => 'Note <i>n</i>'], [], '<aside id="footnote-fn1" class="ujo-footnote" role="note">Note <i>n</i></aside>', 'Note n'];
        yield 'index' => ['index', ['title' => 'Index', 'columns' => 3, 'entries' => [['term' => 'Cheese', 'pages' => '8', 'children' => [['term' => 'Blue', 'pages' => '9']]], ['term' => 'Wine']]], [], '<section class="ujo-index ujo-columns-3"><h2 class="ujo-index__title">Index</h2><ul><li><span class="ujo-index__term">Cheese</span><span class="ujo-index__pages">8</span><ul><li><span class="ujo-index__term">Blue</span><span class="ujo-index__pages">9</span></li></ul></li><li><span class="ujo-index__term">Wine</span></li></ul></section>', "Index\nCheese, 8\n  Blue, 9\nWine"];
        yield 'bare index' => ['index', [], [], '<section class="ujo-index ujo-columns-2"></section>', ''];
        yield 'watermark' => ['watermark', ['text' => 'DRAFT <b>'], [], '<div class="ujo-watermark" aria-hidden="true">DRAFT &lt;b&gt;</div>', 'DRAFT <b>'];
        yield 'note' => ['note', ['title' => 'Title <b>b</b>', 'blocks' => [['id' => 'i', 'type' => 'paragraph', 'data' => ['text' => 'n']]]], [], '<aside class="ujo-note"><p class="ujo-box__title">Title <b>b</b></p><p class="ujo-paragraph">n</p></aside>', "Title b\n\nn"];
        yield 'descriptive' => ['descriptive', ['blocks' => [['id' => 'i', 'type' => 'paragraph', 'data' => ['text' => 'd']]]], [], '<aside class="ujo-descriptive"><p class="ujo-paragraph">d</p></aside>', 'd'];
        yield 'statblock' => ['statblock', ['kind' => 'monster', 'name' => 'Duck', 'subtitle' => '<i>Small beast</i>', 'frame' => true, 'wide' => true, 'record' => 'creature:7', 'sections' => [['properties' => [['label' => 'Armor Class', 'value' => '15 <i>(chain)</i>']]], ['abilities' => [['label' => 'STR', 'score' => 10, 'modifier' => '+0'], ['label' => 'DEX', 'score' => 14]]], ['title' => 'Actions', 'blocks' => [['id' => 'i', 'type' => 'paragraph', 'data' => ['text' => 'Bite.']]]]]], [], '<section class="ujo-statblock ujo-statblock--monster ujo-frame ujo-wide" data-record="creature:7"><h3 class="ujo-statblock__name">Duck</h3><p class="ujo-statblock__subtitle"><i>Small beast</i></p><div class="ujo-statblock__section"><dl class="ujo-statblock__properties"><dt>Armor Class</dt><dd>15 <i>(chain)</i></dd></dl></div><div class="ujo-statblock__section"><table class="ujo-statblock__abilities"><tr><th>STR</th><th>DEX</th></tr><tr><td>10 (+0)</td><td>14</td></tr></table></div><div class="ujo-statblock__section"><h4 class="ujo-statblock__title">Actions</h4><p class="ujo-paragraph">Bite.</p></div></section>', "Duck\nSmall beast\nArmor Class: 15 (chain)\nSTR 10 (+0) DEX 14\nActions\nBite."];
        yield 'bare statblock' => ['statblock', ['name' => 'Spell', 'kind' => 'spell'], [], '<section class="ujo-statblock ujo-statblock--spell"><h3 class="ujo-statblock__name">Spell</h3></section>', 'Spell'];
        yield 'definitionList' => ['definitionList', ['items' => [['term' => 'AC', 'definitions' => ['15', '<b>16</b>']], ['term' => 'HP', 'definitions' => ['22']]]], [], '<dl class="ujo-definition-list"><dt>AC</dt><dd>15</dd><dd><b>16</b></dd><dt>HP</dt><dd>22</dd></dl>', "AC\n  15\n  16\nHP\n  22"];
        yield 'credit' => ['credit', ['title' => 'Bird', 'text' => '<a href="https://x.example">by L.</a>'], [], '<p class="ujo-credit"><strong class="ujo-credit__title">Bird</strong> <a href="https://x.example">by L.</a></p>', 'Bird — by L.'];
        yield 'credit without title' => ['credit', ['text' => 'by L.'], [], '<p class="ujo-credit">by L.</p>', 'by L.'];
        yield 'banner' => ['banner', ['text' => 'HOMEBREW <b>'], [], '<div class="ujo-banner">HOMEBREW &lt;b&gt;</div>', 'HOMEBREW <b>'];
        yield 'decoration' => ['decoration', ['kind' => 'stain', 'variant' => 8, 'color' => '#BBAD82'], [], '<div class="ujo-decoration ujo-decoration--stain-8" aria-hidden="true" style="--ujo-decoration-color:#BBAD82"></div>', ''];
        yield 'bare decoration' => ['decoration', [], [], '<div class="ujo-decoration ujo-decoration--stain-1" aria-hidden="true"></div>', ''];
        yield 'qrCode' => ['qrCode', ['data' => 'https://example.com/?a=1&b=2'], [], '<div class="ujo-qr-code" data-qr-code="https://example.com/?a=1&amp;b=2">https://example.com/?a=1&amp;b=2</div>', ''];
        yield 'container' => ['container', ['classes' => ['spellList', 'wide'], 'blocks' => [['id' => 'i', 'type' => 'paragraph', 'data' => ['text' => 'c']]]], [], '<div class="ujo-container spellList wide"><p class="ujo-paragraph">c</p></div>', 'c'];
        yield 'tunes' => ['paragraph', ['text' => 't'], ['alignment' => ['alignment' => 'center'], 'anchor' => ['anchor' => 'here'], 'textVariant' => 'call-out', 'notice' => ['style' => 'warning', 'caption' => 'Careful'], 'indent' => ['indentLevel' => 2], 'variant' => ['name' => 'classTable', 'frame' => true, 'decoration' => true], 'mask' => ['shape' => 'corner', 'variant' => 3, 'offsetX' => '10%', 'offsetY' => '-5%', 'rotation' => 45], 'layout' => ['position' => 'absolute', 'top' => '1px', 'right' => '2px', 'bottom' => '3px', 'left' => '4px', 'width' => '5px', 'height' => '6px', 'wrap' => 'right', 'zIndex' => 7], 'toc' => ['include' => false]], '<p id="here" class="ujo-paragraph ujo-align-center ujo-text-call-out ujo-notice ujo-notice-warning ujo-indent-2 ujo-variant-classTable ujo-frame ujo-decoration ujo-mask-corner-3 ujo-wrap-right" data-notice-caption="Careful" data-toc="exclude" style="position:absolute;top:1px;right:2px;bottom:3px;left:4px;width:5px;height:6px;z-index:7;--ujo-mask-offset-x:10%;--ujo-mask-offset-y:-5%;--ujo-mask-rotation:45deg">t</p>', 't'];
        yield 'neutral tunes' => ['paragraph', ['text' => 't'], ['textVariant' => '', 'notice' => ['style' => 'info'], 'indent' => ['indentLevel' => 0], 'variant' => ['name' => 'v'], 'layout' => [], 'toc' => ['include' => true]], '<p class="ujo-paragraph ujo-notice ujo-notice-info ujo-variant-v">t</p>', 't'];
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, mixed>    $tunes
     */
    #[DataProvider('blocks')]
    public function testEachTypeIsRenderedAsHtmlAndAsText(string $type, array $data, array $tunes, string $html, string $text): void
    {
        $document = new Document([self::normalize($type, $data, $tunes)]);

        self::assertSame($html, HtmlRenderer::default()->render($document));
        self::assertSame($text, new PlainText()->convert($document));
    }

    public function testEveryTypeHasADefaultRenderer(): void
    {
        $renderer = HtmlRenderer::default();

        foreach ([...TextRenderer::TYPES, ...CodeRenderer::TYPES, ...ListRenderer::TYPES, ...MediaRenderer::TYPES, ...LayoutRenderer::TYPES] as $type) {
            self::assertInstanceOf(BlockRenderer::class, $renderer->renderer($type));
        }

        self::assertInstanceOf(TextRenderer::class, $renderer->renderer('paragraph'));
        self::assertInstanceOf(CodeRenderer::class, $renderer->renderer('code'));
        self::assertInstanceOf(ListRenderer::class, $renderer->renderer('list'));
        self::assertInstanceOf(MediaRenderer::class, $renderer->renderer('image'));
        self::assertInstanceOf(LayoutRenderer::class, $renderer->renderer('columns'));
        self::assertSame(['paragraph', 'header', 'quote', 'warning', 'alert', 'raw', 'delimiter', 'math', 'mermaid', 'runningFooter', 'footnote', 'watermark', 'banner', 'credit'], TextRenderer::TYPES);
        self::assertSame(['code'], CodeRenderer::TYPES);
        self::assertSame(['list', 'definitionList', 'table', 'index', 'toc', 'abbreviations'], ListRenderer::TYPES);
        self::assertSame(['image', 'gallery', 'embed', 'video', 'audio', 'linkTool', 'attaches', 'personality', 'button', 'chart', 'qrCode'], MediaRenderer::TYPES);
        self::assertSame(['columns', 'toggle', 'note', 'descriptive', 'wide', 'columnFlow', 'container', 'pageBreak', 'columnBreak', 'pageStyle', 'spacer', 'pageNumber', 'decoration', 'statblock'], LayoutRenderer::TYPES);
    }

    public function testTopLevelBlocksAreRenderedOnePerLine(): void
    {
        $document = new Document([new Block('a', 'paragraph', ['text' => 'a']), new Block('b', 'delimiter'), new Block('c', 'paragraph', ['text' => 'c'])]);

        self::assertSame("<p class=\"ujo-paragraph\">a</p>\n<hr class=\"ujo-delimiter\">\n<p class=\"ujo-paragraph\">c</p>", HtmlRenderer::default()->render($document));
        self::assertSame("a\n\n***\n\nc", new PlainText()->convert($document));
        self::assertSame('', HtmlRenderer::default()->render(new Document()));
    }

    public function testATableOfContentsListsTheHeadingsOfTheDocument(): void
    {
        $document = self::normalizer()->normalize(Document::fromArray(['blocks' => [
            ['id' => 'toc', 'type' => 'toc', 'data' => ['title' => 'Contents', 'depth' => 3, 'wide' => true]],
            ['id' => 'h1', 'type' => 'header', 'data' => ['text' => 'One <b>&amp;</b>', 'level' => 1]],
            ['id' => 'w', 'type' => 'wide', 'data' => ['blocks' => [['id' => 'h2', 'type' => 'header', 'data' => ['text' => 'Two', 'level' => 3], 'tunes' => ['anchor' => ['anchor' => 'two']]]]]],
            ['id' => 'h3', 'type' => 'header', 'data' => ['text' => 'Three', 'level' => 4]],
            ['id' => 'h4', 'type' => 'header', 'data' => ['text' => 'Four', 'level' => 2], 'tunes' => ['toc' => ['include' => false]]],
            ['id' => 'h5', 'type' => 'header', 'data' => ['text' => 'Five', 'level' => 2], 'tunes' => ['toc' => ['include' => true]]],
            ['id' => 'p', 'type' => 'paragraph', 'data' => ['text' => 'p']],
        ]]));

        self::assertStringStartsWith('<nav class="ujo-toc ujo-wide"><p class="ujo-toc__title">Contents</p><ol><li class="ujo-toc__level-1"><a href="#h1">One &amp;</a></li><li class="ujo-toc__level-3"><a href="#two">Two</a></li><li class="ujo-toc__level-2"><a href="#h5">Five</a></li></ol></nav>'."\n", HtmlRenderer::default()->render($document));
    }

    public function testTablesOfContentsAndListsOfAbbreviationsReadTheDocumentOnce(): void
    {
        $document = new Document([
            new Block('t', 'toc', ['depth' => 2]),
            new Block('a', 'header', ['text' => 'A <abbr title="Hit points">HP</abbr>']),
            new Block('b', 'header', ['text' => 'B', 'level' => 3]),
            new Block('u', 'toc', ['depth' => 3]),
            new Block('x', 'abbreviations', []),
            new Block('y', 'abbreviations', ['title' => 'Again']),
        ]);

        self::assertSame(implode("\n", [
            '<nav class="ujo-toc"><ol><li class="ujo-toc__level-2"><a href="#a">A HP</a></li></ol></nav>',
            '<h2 id="a" class="ujo-header">A <abbr title="Hit points">HP</abbr></h2>',
            '<h3 id="b" class="ujo-header">B</h3>',
            '<nav class="ujo-toc"><ol><li class="ujo-toc__level-2"><a href="#a">A HP</a></li><li class="ujo-toc__level-3"><a href="#b">B</a></li></ol></nav>',
            '<section class="ujo-abbreviations"><dl><dt><abbr title="Hit points">HP</abbr></dt><dd>Hit points</dd></dl></section>',
            '<section class="ujo-abbreviations"><p class="ujo-abbreviations__title">Again</p><dl><dt><abbr title="Hit points">HP</abbr></dt><dd>Hit points</dd></dl></section>',
        ]), HtmlRenderer::default()->render($document));
    }

    public function testARenderingComputesAValueOncePerKey(): void
    {
        $rendering = new Rendering(new Document(), HtmlRenderer::default(), new InlineSanitizer());
        $calls = 0;
        $count = static function () use (&$calls): int {
            return ++$calls;
        };

        self::assertSame([1, 1, 2, 1], [$rendering->remember('a', $count), $rendering->remember('a', $count), $rendering->remember('b', $count), $rendering->remember('a', $count)]);
        self::assertSame(3, new Rendering(new Document(), HtmlRenderer::default(), new InlineSanitizer())->remember('a', $count));
        self::assertNull($rendering->remember('n', static fn (): null => null));
        $rendering->remember('n', $count);
        self::assertSame(3, $calls);
    }

    public function testARendererIsReplacedOrAddedPerType(): void
    {
        $shout = new class implements BlockRenderer {
            public function render(Block $block, Rendering $rendering): string
            {
                return $rendering->element('p', $block, strtoupper(Rendering::text($block->data['text'] ?? '')), ['data-x' => 'y', 'style' => 'color:red'], ['loud']);
            }
        };
        $renderer = HtmlRenderer::default()->with('paragraph', $shout)->with('custom', $shout);
        $document = new Document([new Block('a', 'paragraph', ['text' => 'hi'], ['layout' => ['top' => '1px']]), new Block('b', 'custom', ['text' => 'yo'])]);

        self::assertSame("<p class=\"ujo-paragraph loud\" data-x=\"y\" style=\"color:red;top:1px\">HI</p>\n<p class=\"ujo-custom loud\" data-x=\"y\" style=\"color:red\">YO</p>", $renderer->render($document));
    }

    public function testTheSanitizerOfTheRendererIsUsed(): void
    {
        $renderer = HtmlRenderer::default(new InlineSanitizer(['rich' => InlineProfile::plain()]));

        self::assertSame('<p class="ujo-paragraph">a</p>', $renderer->render(new Document([new Block('a', 'paragraph', ['text' => '<b>a</b>'])])));
        self::assertSame('<p class="ujo-paragraph"><b>a</b></p>', new HtmlRenderer(['paragraph' => new TextRenderer()])->render(new Document([new Block('a', 'paragraph', ['text' => '<b>a</b><script>x</script>'])])));
        self::assertSame('a', new PlainText()->convert(new Document([new Block('a', 'paragraph', ['text' => '<b>a</b>'])])));
    }

    public function testFiltersPassOverTheHtmlInOrder(): void
    {
        $document = new Document([new Block('a', 'paragraph', ['text' => 'a']), new Block('b', 'quote', ['text' => 'b'])]);
        $upper = new class implements HtmlFilter {
            public function filter(string $html): string
            {
                return strtoupper($html);
            }
        };
        $mark = new class implements HtmlFilter {
            public function filter(string $html): string
            {
                return '['.$html.']';
            }
        };
        $renderer = HtmlRenderer::default(new InlineSanitizer(['rich' => InlineProfile::plain()]))->withFilter($upper)->withFilter($mark);

        self::assertSame("[<P CLASS=\"UJO-PARAGRAPH\">A</P>\n<FIGURE CLASS=\"UJO-QUOTE\"><BLOCKQUOTE>B</BLOCKQUOTE></FIGURE>]", $renderer->render($document));
        self::assertSame('[<P CLASS="UJO-PARAGRAPH">&LT;B&GT;</P>]', $renderer->with('paragraph', new TextRenderer())->render(new Document([new Block('a', 'paragraph', ['text' => '<b>&lt;b&gt;</b>'])])));
        self::assertSame('<p class="ujo-paragraph">a</p>', HtmlRenderer::default()->render(new Document([new Block('a', 'paragraph', ['text' => 'a'])])));
    }

    public function testTheDataTuneIsWrittenOnlyWhenAsked(): void
    {
        $document = new Document([self::normalize('alert', ['text' => 't'], ['data' => ['source' => 'import "a"', 'toc' => 'x', 'notice-caption' => 'y'], 'toc' => ['include' => false]])]);
        $renderer = HtmlRenderer::default()->withDataAttributes()->withFilter(new class implements HtmlFilter {
            #[Override]
            public function filter(string $html): string
            {
                return $html;
            }
        })->with('quote', new TextRenderer());

        self::assertSame('<div class="ujo-alert ujo-alert--info ujo-align-left" data-source="import &quot;a&quot;" data-toc="exclude" role="alert">t</div>', $renderer->render($document));
        self::assertSame('<div class="ujo-alert ujo-alert--info ujo-align-left" role="alert" data-toc="exclude">t</div>', $renderer->withDataAttributes(false)->render($document));
    }

    public function testAMissingRendererIsAnError(): void
    {
        try {
            new HtmlRenderer()->render(new Document([new Block('a', 'paragraph', ['text' => 'a'])]));
            self::fail('A block is rendered without a renderer.');
        } catch (UnknownBlockType $unknownBlockType) {
            self::assertSame('No HTML renderer is registered for the block type "paragraph".', $unknownBlockType->getMessage());
        }
    }

    /**
     * @return iterable<string, array{BlockRenderer}>
     */
    public static function renderers(): iterable
    {
        yield 'text' => [new TextRenderer()];
        yield 'list' => [new ListRenderer()];
        yield 'media' => [new MediaRenderer()];
        yield 'layout' => [new LayoutRenderer()];
    }

    #[DataProvider('renderers')]
    public function testARendererRefusesATypeItDoesNotRender(BlockRenderer $renderer): void
    {
        $this->expectExceptionObject(UnknownBlockType::renderer('sparkle', 'HTML'));

        new HtmlRenderer(['sparkle' => $renderer])->render(new Document([new Block('a', 'sparkle')]));
    }

    public function testAnUncheckedBlockStillRendersSafely(): void
    {
        $document = new Document([
            new Block('a', 'header', ['text' => '<script>x</script>t', 'level' => 9]),
            new Block('b', 'header', ['text' => 't', 'level' => 0]),
            new Block('c', 'header', ['text' => 't', 'level' => '3']),
            new Block('d', 'paragraph', ['text' => 1]),
            new Block('e', 'wide', ['blocks' => ['x', new Block('f', 'paragraph', ['text' => 'in'])]]),
            new Block('g', 'columns', ['cols' => 'x']),
            new Block('h', 'list', ['items' => [['content' => 'a', 'items' => 'x'], 'b']]),
            new Block('i', 'image', []),
            new Block('j', 'statblock', []),
            new Block('k', 'spacer', []),
            new Block('l', 'decoration', ['kind' => ['x']]),
        ]);

        self::assertSame(implode("\n", [
            '<h6 id="a" class="ujo-header">t</h6>',
            '<h1 id="b" class="ujo-header">t</h1>',
            '<h2 id="c" class="ujo-header">t</h2>',
            '<p class="ujo-paragraph"></p>',
            '<div class="ujo-wide"><p class="ujo-paragraph">in</p></div>',
            '<div class="ujo-columns ujo-columns--0"></div>',
            '<ul class="ujo-list ujo-list--unordered"><li>a</li></ul>',
            '<figure class="ujo-image"><img alt="" loading="lazy"></figure>',
            '<section class="ujo-statblock ujo-statblock--monster"><h3 class="ujo-statblock__name"></h3></section>',
            '<div class="ujo-spacer" aria-hidden="true" data-lines="1"></div>',
            '<div class="ujo-decoration ujo-decoration---1" aria-hidden="true"></div>',
        ]), HtmlRenderer::default()->render($document));
        self::assertSame("t\n\nt\n\nt\n\nin\n\n- a", new PlainText()->convert($document));
    }

    public function testElementsAndAttributesAreWrittenEscaped(): void
    {
        self::assertSame('<a href="/a?b=&quot;c&quot;" download data-n="3">x</a>', Rendering::tag('a', ['href' => '/a?b="c"', 'download' => true, 'hidden' => false, 'title' => null, 'data-n' => 3], 'x'));
        self::assertSame('<br>', Rendering::tag('br', [], 'x'));
        self::assertSame('<img src="a">', Rendering::tag('img', ['src' => 'a']));
        self::assertSame('<hr>', Rendering::tag('hr', []));
        self::assertSame('&lt;b&gt;', Rendering::text('<b>'));
        self::assertSame('3', Rendering::text(3));
        self::assertSame('', Rendering::text(3.5));
        self::assertSame([], Rendering::array('x'));
        self::assertSame([['a' => 1]], Rendering::rows(['x', ['a' => 1]]));
    }

    public function testATextConversionIsReplacedOrAddedPerType(): void
    {
        $text = new PlainText()->with('paragraph', static fn (Block $block, PlainText $text): string => strtoupper($text->text($block->data['text'] ?? null)))->with('custom', static fn (): string => 'custom');
        $document = new Document([new Block('a', 'paragraph', ['text' => '<b>hi</b>']), new Block('b', 'custom'), new Block('c', 'unknown'), new Block('d', 'paragraph', ['text' => '  '])]);

        self::assertSame("HI\n\ncustom", $text->convert($document));
        self::assertSame('', $text->text(null));
        self::assertSame('', $text->blocks('x'));
        self::assertSame('HI', $text->block(new Block('e', 'paragraph', ['text' => 'hi'])));
    }

    public function testAPlayerWithoutItsSwitchesHasControlsOnly(): void
    {
        $document = new Document([
            new Block('a', 'video', ['sources' => [['url' => '/v.mp4']], 'tracks' => [['url' => '/fr.vtt', 'kind' => 'captions']]]),
            new Block('b', 'audio', ['sources' => [['url' => '/a.ogg']], 'title' => 'Song']),
        ]);

        self::assertSame("<figure class=\"ujo-video\"><video controls preload=\"metadata\"><source src=\"/v.mp4\"><track kind=\"captions\" src=\"/fr.vtt\"></video><a class=\"ujo-print-fallback\" href=\"/v.mp4\"><span class=\"ujo-print-fallback__url\">/v.mp4</span></a></figure>\n<figure class=\"ujo-audio\"><audio controls preload=\"metadata\" title=\"Song\"><source src=\"/a.ogg\"></audio><a class=\"ujo-print-fallback\" href=\"/a.ogg\"><span class=\"ujo-print-fallback__title\">Song</span><span class=\"ujo-print-fallback__url\">/a.ogg</span></a></figure>", HtmlRenderer::default()->render($document));
        self::assertSame('Song', new PlainText()->convert(new Document([$document->blocks[1]])));
    }

    public function testATableOfKeyedRowsAndCellsKeepsItsHeadAndItsAlignment(): void
    {
        $document = new Document([new Block('a', 'table', ['withHeadings' => true, 'align' => ['left', 'right'], 'content' => ['x' => ['k' => 'a', 'l' => 'b'], 'y' => ['c', 'd']]])]);

        self::assertSame('<table class="ujo-table"><thead><tr><th class="ujo-align-left">a</th><th class="ujo-align-right">b</th></tr></thead><tbody><tr><td class="ujo-align-left">c</td><td class="ujo-align-right">d</td></tr></tbody></table>', HtmlRenderer::default()->render($document));
    }

    public function testAbbreviationsAreSortedByTheirLettersInLowerCaseAndKeepTheirQuotes(): void
    {
        $document = new Document([
            new Block('a', 'paragraph', ['text' => '<abbr title="b">ÉB</abbr> <abbr title="a">éa</abbr> <abbr title="c">Éc</abbr> <abbr title="It\'s &quot;d&quot;">D</abbr>']),
            new Block('b', 'abbreviations', []),
        ]);

        self::assertStringEndsWith('<dl><dt><abbr title="It&#039;s &quot;d&quot;">D</abbr></dt><dd>It&#039;s &quot;d&quot;</dd><dt><abbr title="a">éa</abbr></dt><dd>a</dd><dt><abbr title="b">ÉB</abbr></dt><dd>b</dd><dt><abbr title="c">Éc</abbr></dt><dd>c</dd></dl></section>', HtmlRenderer::default()->render($document));
    }

    public function testARenderingWritesNoDataAttributeUnlessAsked(): void
    {
        $block = new Block('a', 'paragraph', ['text' => 'x'], ['data' => ['source' => 'import']]);
        $document = new Document([$block]);

        self::assertSame('<p class="ujo-paragraph">x</p>', new Rendering($document, HtmlRenderer::default(), new InlineSanitizer())->element('p', $block, 'x'));
        self::assertSame('<p class="ujo-paragraph" data-source="import">x</p>', new Rendering($document, HtmlRenderer::default(), new InlineSanitizer(), true)->element('p', $block, 'x'));
    }

    public function testTheRichProfileKeepsALanguageAloneRubyInRubyAndMathMl(): void
    {
        $sanitizer = new InlineSanitizer();

        self::assertSame('<span lang="en">x</span>', $sanitizer->sanitize('<span lang="en">x</span>'));
        self::assertSame('<ruby><ruby>a</ruby><rt>b</rt></ruby>', $sanitizer->sanitize('<ruby><ruby>a</ruby><rt>b</rt></ruby>'));
        self::assertSame('<math dir="rtl"><mi dir="rtl">x</mi></math>', $sanitizer->sanitize('<math dir="rtl"><mi dir="rtl">x</mi></math>', 'mathml'));
        self::assertContains('mathml-mi', array_map(static fn (InlineTool $tool): string => $tool->name, InlineProfile::mathMl()->tools));
        self::assertNull(new InlineTool('x', 'span', ['span.x'], 'x', ['data-a' => AttributeValue::Text], [], [], ['data-a'])->clean([]));
        self::assertTrue(LanguageTag::isValid('x'.str_repeat('-abcdefgh', 7)));
        self::assertFalse(LanguageTag::isValid('x'.str_repeat('-abcdefgh', 6).'-abcdefg-a'));
    }

    public function testTheTextIsGivenByTheLanguageOfEachBlock(): void
    {
        $document = new Document([
            new Block('a', 'paragraph', ['text' => 'Bonjour']),
            new Block('b', 'paragraph', ['text' => 'Hello'], ['language' => ['language' => 'en-CA']]),
            new Block('c', 'paragraph', ['text' => 'Salut'], ['language' => ['direction' => 'ltr']]),
            new Block('d', 'paragraph', ['text' => 'Hi'], ['language' => ['language' => 'EN-ca', 'direction' => 'ltr']]),
            new Block('e', 'paragraph', ['text' => ' '], ['language' => ['language' => 'de']]),
            new Block('f', 'paragraph', ['text' => 'Ciao'], ['language' => ['language' => 'it']]),
        ]);

        self::assertSame(['fr' => "Bonjour\n\nSalut", 'en-ca' => "Hello\n\nHi", 'it' => 'Ciao'], new PlainText()->passages($document, 'FR'));
        self::assertSame([], new PlainText()->passages(new Document([]), 'fr'));
    }

    public function testTheTextOfANumberedListAndOfAZeroIsKept(): void
    {
        $document = new Document([
            new Block('a', 'list', ['style' => 'ordered', 'items' => [['content' => '0', 'items' => [['content' => 'x']]], ['content' => 'b']]]),
            new Block('b', 'index', ['entries' => [['term' => '0', 'pages' => '0']]]),
            new Block('c', 'paragraph', ['text' => '0']),
            new Block('d', 'statblock', ['name' => '0', 'sections' => [['abilities' => [['label' => 'STR', 'score' => 0, 'modifier' => '0']]]]]),
        ]);

        self::assertSame("1. 0\n  1. x\n2. b\n\n0, 0\n\n0\n\n0\nSTR 0 (0)", new PlainText()->convert($document));
    }

    public function testDefaultsOfUncheckedData(): void
    {
        $document = new Document([
            new Block('a', 'toggle', ['text' => 't']),
            new Block('b', 'columnFlow', []),
            new Block('c', 'container', ['classes' => [3, 'a']]),
            new Block('d', 'list', ['style' => 'unordered', 'meta' => ['start' => 3, 'counterType' => 'lower-roman'], 'items' => []]),
            new Block('e', 'table', ['content' => ['x', ['a']]]),
            new Block('f', 'index', []),
            new Block('g', 'toc', []),
            new Block('h', 'image', ['file' => ['url' => '/a.png'], 'unsplash' => ['author' => 'A', 'profileLink' => '/p']]),
            new Block('i', 'statblock', ['name' => 'n', 'sections' => [['title' => 'T', 'properties' => [['label' => 'a', 'value' => '1'], ['label' => 'b', 'value' => '2']], 'abilities' => [['label' => 'S', 'score' => 1]], 'blocks' => [new Block('j', 'paragraph', ['text' => 'p'])]]]]),
            new Block('k', 'personality', ['name' => 3.5]),
        ]);

        self::assertSame(implode("\n", [
            '<details class="ujo-toggle"><summary>t</summary></details>',
            '<div class="ujo-column-flow ujo-column-flow--2"></div>',
            '<div class="ujo-container a"></div>',
            '<ul class="ujo-list ujo-list--unordered"></ul>',
            '<table class="ujo-table"><tbody><tr></tr><tr><td>a</td></tr></tbody></table>',
            '<section class="ujo-index ujo-columns-2"></section>',
            '<nav class="ujo-toc"><ol></ol></nav>',
            '<figure class="ujo-image"><img src="/a.png" alt="" loading="lazy"><figcaption><small class="ujo-image__credit"><a href="/p" rel="noopener">A</a></small></figcaption></figure>',
            '<section class="ujo-statblock ujo-statblock--monster"><h3 class="ujo-statblock__name">n</h3><div class="ujo-statblock__section"><h4 class="ujo-statblock__title">T</h4><dl class="ujo-statblock__properties"><dt>a</dt><dd>1</dd><dt>b</dt><dd>2</dd></dl><table class="ujo-statblock__abilities"><tr><th>S</th></tr><tr><td>1</td></tr></table><p class="ujo-paragraph">p</p></div></section>',
            '<div class="ujo-personality"><p class="ujo-personality__name"></p></div>',
        ]), HtmlRenderer::default()->render($document));
        self::assertSame("t\n\na\n\nn\nT\na: 1\nb: 2\nS 1\np", new PlainText()->convert($document));
    }

    public function testATableOfContentsReadsItsDepthAndTheLevelOfEachHeading(): void
    {
        $document = new Document([
            new Block('t', 'toc', ['depth' => 2]),
            new Block('a', 'header', ['text' => 'A']),
            new Block('b', 'header', ['text' => 'B', 'level' => 3]),
            new Block('c', 'header', ['text' => 'C', 'level' => 2]),
            new Block('d', 'header', ['text' => 'D', 'level' => 4]),
        ]);

        self::assertStringStartsWith('<nav class="ujo-toc"><ol><li class="ujo-toc__level-2"><a href="#a">A</a></li><li class="ujo-toc__level-2"><a href="#c">C</a></li></ol></nav>', HtmlRenderer::default()->render($document));
        self::assertStringStartsWith('<nav class="ujo-toc"><ol><li class="ujo-toc__level-2"><a href="#a">A</a></li><li class="ujo-toc__level-3"><a href="#b">B</a></li><li class="ujo-toc__level-2"><a href="#c">C</a></li></ol></nav>', HtmlRenderer::default()->render($document->withBlocks([new Block('t', 'toc'), ...\array_slice($document->blocks, 1)])));
    }
}
