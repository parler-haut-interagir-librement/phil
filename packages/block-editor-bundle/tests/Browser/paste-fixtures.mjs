// Clipboard HTML in the shapes Word, Google Docs and a web page put on the clipboard, written for the measures: the
// marks each should keep, the text that must arrive, and what must never arrive.

export const FIXTURES = {
    'Word (Windows, HTML format)': {
        html:
            '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">' +
            '<head><meta name=Generator content="Microsoft Word 15"><style><!-- p.MsoNormal {margin:0cm;font-family:"Calibri";} --></style>' +
            '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Normal</w:View></w:WordDocument></xml><![endif]--></head>' +
            "<body lang=EN-CA style='tab-interval:36.0pt'><!--StartFragment-->" +
            "<p class=MsoNormal><b><span style='mso-bidi-font-weight:normal'>Bold</span></b><span> and </span><i>italic</i>, " +
            '<u>underlined</u>, <s>struck</s>, x<sup>2</sup>, H<sub>2</sub>O, <a href="https://example.org/page">a link</a><o:p></o:p></p>' +
            "<p class=MsoNormal>Second <span style='font-weight:bold'>styled</span> and <span style='font-style:italic'>slanted</span> line<o:p></o:p></p>" +
            '<!--EndFragment--></body></html>',
        text: 'Bold and italic, underlined, struck, x2, H2O, a link\nSecond styled and slanted line',
        marks: ['bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', 'link'],
        must: ['Bold', 'italic', 'underlined', 'struck', 'a link', 'styled', 'slanted'],
        never: ['mso-', 'MsoNormal', 'o:p', 'WordDocument', 'Normal</', 'Bold and'],
    },
    'Google Docs': {
        html:
            '<meta charset="utf-8"><b style="font-weight:normal;" id="docs-internal-guid-0a1b2c3d-7fff-1234-5678-9abcdef01234">' +
            '<p dir="ltr" style="line-height:1.38;margin-top:0pt;margin-bottom:0pt;">' +
            '<span style="font-size:11pt;font-family:Arial,sans-serif;font-weight:700;vertical-align:baseline;">Bold</span>' +
            '<span style="font-size:11pt;font-family:Arial,sans-serif;font-weight:400;"> and </span>' +
            '<span style="font-size:11pt;font-family:Arial,sans-serif;font-weight:400;font-style:italic;">italic</span>' +
            '<span style="font-weight:400;">, </span>' +
            '<span style="font-weight:400;text-decoration:underline;-webkit-text-decoration-skip:none;">underlined</span>' +
            '<span style="font-weight:400;">, </span>' +
            '<span style="font-weight:400;text-decoration:line-through;">struck</span>' +
            '<span style="font-weight:400;">, x</span><span style="font-weight:400;vertical-align:super;">2</span>' +
            '<span style="font-weight:400;">, H</span><span style="font-weight:400;vertical-align:sub;">2</span>' +
            '<span style="font-weight:400;">O, </span>' +
            '<a href="https://example.org/page" style="text-decoration:none;"><span style="color:#1155cc;text-decoration:underline;">a link</span></a></p>' +
            '<br><p dir="ltr" style="line-height:1.38;"><span style="font-weight:400;">Second line, normal weight</span></p></b>',
        text: 'Bold and italic, underlined, struck, x2, H2O, a link\n\nSecond line, normal weight',
        marks: ['bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', 'link'],
        must: ['Bold', 'italic', 'underlined', 'struck', 'a link', 'Second line, normal weight'],
        never: ['docs-internal-guid', 'font-size', 'Bold and', '<b>Second'],
    },
    'web page': {
        html:
            '<div class="article"><h2 id="t">A title</h2><p>A <strong>strong</strong> word, an <em>emphasis</em>, ' +
            '<code>code()</code>, <mark>marked</mark>, <a href="https://example.org/" target="_blank" rel="noopener">a link</a>, ' +
            '<a href="javascript:alert(1)">a trap</a>, <span onclick="alert(2)">a handler</span>' +
            '<script>steal()</script><img src="x" onerror="alert(3)"><style>p{color:red}</style></p></div>',
        text: 'A title\nA strong word, an emphasis, code(), marked, a link, a trap, a handler',
        marks: ['bold', 'italic', 'inlineCode', 'marker', 'link'],
        must: ['A title', 'strong', 'emphasis', 'code()', 'marked', 'a link', 'a trap', 'a handler'],
        never: ['javascript:', 'onclick', 'onerror', 'steal()', '<img', 'color:red', '<script'],
    },
};

/** The tag each mark is written with, to find it in what the editor saved. */
export const MARK_TAGS = {
    bold: '<b>',
    italic: '<i>',
    underline: '<u ',
    strikethrough: '<s ',
    superscript: '<sup>',
    subscript: '<sub>',
    link: '<a href=',
    inlineCode: '<code ',
    marker: '<mark ',
};

/** How much of a fixture arrived: the marks kept, the text present, nothing forbidden. */
export function score(fixture, html) {
    const kept = fixture.marks.filter((mark) => html.includes(MARK_TAGS[mark]));
    const present = fixture.must.filter((text) => html.includes(text));
    const leaked = fixture.never.filter((text) => html.includes(text));

    return {
        pass: kept.length === fixture.marks.length && present.length === fixture.must.length && 0 === leaked.length,
        marks: kept.length + ' / ' + fixture.marks.length,
        missing: fixture.marks.filter((mark) => !kept.includes(mark)),
        text: present.length + ' / ' + fixture.must.length,
        leaked,
    };
}
