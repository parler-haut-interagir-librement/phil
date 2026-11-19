<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

/**
 * What an import leaves out because a UJO document has no place for it, or an export because a word processing
 * document has no place for it.
 */
enum Omitted: string
{
    case Comment = 'comment';
    case TrackedChange = 'tracked-change';
    case HeaderFooter = 'header-footer';
    case TextBox = 'text-box';
    case Drawing = 'drawing';
    case Equation = 'equation';
    case EmbeddedObject = 'embedded-object';
    case Image = 'image';
    case SvgContent = 'svg-content';
    case NestedTable = 'nested-table';
    case MergedCell = 'merged-cell';
    case InternalLink = 'internal-link';
    case HiddenText = 'hidden-text';
    case Formatting = 'formatting';
    case Macro = 'macro';
    case Element = 'element';
    case Style = 'style';
    case Block = 'block';
    case Annotation = 'annotation';

    /**
     * What the omission means for the imported text, in a sentence.
     */
    public function description(): string
    {
        return match ($this) {
            self::Comment => 'Comments and annotations are not imported.',
            self::TrackedChange => 'Tracked changes are imported as accepted: deleted text is left out.',
            self::HeaderFooter => 'Page headers and footers are not imported.',
            self::TextBox => 'Text boxes and frames are not imported.',
            self::Drawing => 'Shapes, charts and diagrams are not imported.',
            self::Equation => 'Equations are not imported.',
            self::EmbeddedObject => 'Embedded objects are not imported.',
            self::Image => 'Some images could not be read.',
            self::SvgContent => 'Scripts, links to other files and other unsafe parts of SVG images were removed.',
            self::NestedTable => 'Tables inside table cells are imported as their text.',
            self::MergedCell => 'Merged cells are split: the cells they covered are empty.',
            self::InternalLink => 'Links to places inside the document keep their text but lose their target.',
            self::HiddenText => 'Hidden text is not imported.',
            self::Formatting => 'Colours, fonts and sizes are not imported.',
            self::Macro => 'Macros are never run nor imported.',
            self::Element => 'Elements without a place in a block document are not imported.',
            self::Style => 'Paragraphs of styles the application leaves out are not imported.',
            self::Block => 'Blocks without a place in a word processing document are not exported.',
            self::Annotation => 'Form fields, stamps and other annotations of the page are not imported.',
        };
    }
}
