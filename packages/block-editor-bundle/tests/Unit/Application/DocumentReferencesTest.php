<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Unit\Application;

use Phil\BlockEditorBundle\Application\Reference\DocumentReferences;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentReferences::class)]
final class DocumentReferencesTest extends TestCase
{
    private const string DOCUMENT = <<<'JSON'
        {"blocks": [
            {"id": "a", "type": "paragraph", "data": {"text": "Hi <a href=\"/u/1\" data-mention=\"user:1\">@Ada</a>, see <a data-ref=\"page:7\">P</a>, <a data-ref=\"page:7\">P again</a> and <a data-mention=\"user:2\">@Al</a>."}},
            {"id": "b", "type": "image", "data": {"file": {"url": "/a.png"}, "caption": "<a data-ref=\"page:8\">c</a>"}},
            {"id": "t", "type": "toggle", "data": {"text": "<a data-mention=\"user:1\">@Ada</a>", "blocks": [
                {"id": "c", "type": "list", "data": {"style": "unordered", "items": [{"content": "<a data-ref=\"page:9\">x</a>", "items": []}]}},
                {"id": "d", "type": "attaches", "data": {"file": {"url": "/doc.pdf", "size": 1, "name": "doc.pdf", "extension": "pdf"}}},
                {"id": "e", "type": "image", "data": {"file": {"url": "/a.png"}}}
            ]}},
            {"id": "g", "type": "gallery", "data": {"files": [{"url": "/g1.png", "caption": ""}, {"url": "/g2.png", "caption": ""}]}},
            {"id": "h", "type": "paragraph", "data": {"text": "data-ref=\"page:0\" as text"}}
        ]}
        JSON;

    public function testTheReferencesAndFilesOfADocument(): void
    {
        $document = new Ujo()->decode(self::DOCUMENT);

        self::assertSame(['user:1', 'user:2'], DocumentReferences::mentions($document));
        self::assertSame(['page:7', 'page:8', 'page:9'], DocumentReferences::links($document));
        self::assertSame(['/a.png', '/doc.pdf', '/g1.png', '/g2.png'], DocumentReferences::files($document));
    }

    public function testADocumentWithoutAny(): void
    {
        $document = new Ujo()->decode('{"blocks": [{"id": "a", "type": "paragraph", "data": {"text": "x"}}, {"id": "b", "type": "code", "data": {"code": "file"}}]}');

        self::assertSame([], DocumentReferences::mentions($document));
        self::assertSame([], DocumentReferences::links($document));
        self::assertSame([], DocumentReferences::files($document));
    }
}
