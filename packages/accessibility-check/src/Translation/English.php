<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Translation;

/**
 * The messages of the rules in English, by key.
 */
final class English
{
    public const string LOCALE = 'en';

    /** @var array<string, string> */
    public const array MESSAGES = [
        'image-alt.missing' => 'The image {source} has no text alternative: describe it in an alt text, or give it alt="" when it is only decoration.',
        'image-alt.blank' => 'The text alternative of the image {source} holds only spaces: describe the image, or give it alt="" when it is only decoration.',
        'image-alt.filename' => 'The text alternative of the image {source} is a file name, "{alt}": describe what the image shows.',
        'heading-order.skipped' => 'This h{level} heading follows an h{previous}: a level is skipped.',
        'heading-h1.multiple' => 'This is first-level heading number {position}: a page has one.',
        'heading-h1.forbidden' => 'This content holds a first-level heading, which the title of its page already is: start its headings at h2.',
        'heading-h1.missing' => 'The page has no first-level heading.',
        'empty-heading.empty' => 'This h{level} heading has no text.',
        'link-name.empty' => 'The link to {href} has no text: screen readers announce only its address.',
        'button-name.empty' => 'This button has no text: say what it does.',
        'link-text.generic' => 'The text of the link to {href}, "{text}", does not say where it leads: describe its destination.',
        'color-contrast.insufficient' => 'The text "{text}" has a contrast of {ratio}:1 between {foreground} and {background}; it needs {required}:1.',
        'color-contrast.unresolved' => 'The colour {value} depends on the style sheets of the page: check the contrast of this text in a browser.',
        'table-headers.missing' => 'This table has no header cell: mark its headings with th, or turn on the heading row of the block.',
        'html-lang.missing' => 'The page does not say its language: give the html element a lang attribute.',
        'html-lang.invalid' => 'The language of the page, "{lang}", is not a valid language tag.',
        'lang-change.invalid' => '"{lang}" is not a valid language tag.',
        'lang-change.empty' => 'This passage is marked as of an unknown language.',
        'lang-change.direction-only' => 'This passage changes direction without saying its language.',
        'frame-title.missing' => 'The frame {source} has no title: say what it holds.',
        'video-captions.missing' => 'The video {source} has no captions track.',
        'duplicate-id.duplicate' => 'The identifier "{id}" is used {count} times: a reference to it reaches only the first.',
        'svg-name.missing' => 'This drawing has no title: give it a title element, or aria-hidden="true" when it is only decoration.',
        'report.blocked' => 'The accessibility check found {count} problem(s) to fix before publishing, starting with: {message}',
    ];
}
