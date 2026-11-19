<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Export;

/**
 * The styles part of a written OpenDocument text: the common styles an office suite recognises by name — Standard,
 * Text body, the six headings with their outline levels, Title, Quotations, Preformatted Text, Caption, Footnote,
 * table contents and heading, Contents Heading, and the Source Text character style.
 *
 * @internal
 */
final class OdtStylesheet
{
    public const string STYLES = XmlText::DECLARATION
        .'<office:document-styles '.OdtWriter::NAMESPACES.' office:version="1.3"><office:styles>'
        .'<style:style style:name="Standard" style:family="paragraph" style:class="text"/>'
        .'<style:style style:name="Text_20_body" style:display-name="Text body" style:family="paragraph" style:parent-style-name="Standard" style:class="text"><style:paragraph-properties fo:margin-top="0in" fo:margin-bottom="0.0972in"/></style:style>'
        .'<style:style style:name="Heading" style:family="paragraph" style:parent-style-name="Standard" style:next-style-name="Text_20_body" style:class="text"><style:paragraph-properties fo:margin-top="0.1665in" fo:margin-bottom="0.0835in" fo:keep-with-next="always"/><style:text-properties fo:font-weight="bold"/></style:style>'
        .'<style:style style:name="Heading_20_1" style:display-name="Heading 1" style:family="paragraph" style:parent-style-name="Heading" style:default-outline-level="1" style:class="text"><style:text-properties fo:font-size="20pt"/></style:style>'
        .'<style:style style:name="Heading_20_2" style:display-name="Heading 2" style:family="paragraph" style:parent-style-name="Heading" style:default-outline-level="2" style:class="text"><style:text-properties fo:font-size="16pt"/></style:style>'
        .'<style:style style:name="Heading_20_3" style:display-name="Heading 3" style:family="paragraph" style:parent-style-name="Heading" style:default-outline-level="3" style:class="text"><style:text-properties fo:font-size="14pt"/></style:style>'
        .'<style:style style:name="Heading_20_4" style:display-name="Heading 4" style:family="paragraph" style:parent-style-name="Heading" style:default-outline-level="4" style:class="text"><style:text-properties fo:font-size="12pt" fo:font-style="italic"/></style:style>'
        .'<style:style style:name="Heading_20_5" style:display-name="Heading 5" style:family="paragraph" style:parent-style-name="Heading" style:default-outline-level="5" style:class="text"><style:text-properties fo:font-size="11pt"/></style:style>'
        .'<style:style style:name="Heading_20_6" style:display-name="Heading 6" style:family="paragraph" style:parent-style-name="Heading" style:default-outline-level="6" style:class="text"><style:text-properties fo:font-size="11pt" fo:font-style="italic"/></style:style>'
        .'<style:style style:name="Title" style:family="paragraph" style:parent-style-name="Heading" style:class="chapter"><style:paragraph-properties fo:text-align="center"/><style:text-properties fo:font-size="28pt"/></style:style>'
        .'<style:style style:name="Quotations" style:family="paragraph" style:parent-style-name="Standard" style:class="html"><style:paragraph-properties fo:margin-left="0.3937in" fo:margin-right="0.3937in" fo:margin-bottom="0.0972in"/><style:text-properties fo:font-style="italic"/></style:style>'
        .'<style:style style:name="Preformatted_20_Text" style:display-name="Preformatted Text" style:family="paragraph" style:parent-style-name="Standard" style:class="html"><style:text-properties style:font-name="Liberation Mono" fo:font-family="\'Liberation Mono\'" fo:font-size="10pt"/></style:style>'
        .'<style:style style:name="Caption" style:family="paragraph" style:parent-style-name="Standard" style:class="extra"><style:text-properties fo:font-size="10pt" fo:font-style="italic"/></style:style>'
        .'<style:style style:name="Footnote" style:family="paragraph" style:parent-style-name="Standard" style:class="extra"><style:text-properties fo:font-size="10pt"/></style:style>'
        .'<style:style style:name="Table_20_Contents" style:display-name="Table Contents" style:family="paragraph" style:parent-style-name="Standard" style:class="extra"/>'
        .'<style:style style:name="Table_20_Heading" style:display-name="Table Heading" style:family="paragraph" style:parent-style-name="Table_20_Contents" style:class="extra"><style:text-properties fo:font-weight="bold"/></style:style>'
        .'<style:style style:name="Source_20_Text" style:display-name="Source Text" style:family="text"><style:text-properties style:font-name="Liberation Mono" fo:font-family="\'Liberation Mono\'"/></style:style>'
        .'<style:style style:name="Contents_20_Heading" style:display-name="Contents Heading" style:family="paragraph" style:parent-style-name="Heading" style:class="index"><style:text-properties fo:font-size="16pt"/></style:style>'
        .'</office:styles></office:document-styles>';
}
