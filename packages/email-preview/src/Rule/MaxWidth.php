<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Rule;

use Override;
use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\Contract\Rule;
use Phil\EmailPreview\Finding;
use Phil\EmailPreview\Style\InlineStyle;

/**
 * The email fits the reading pane of a mail client: no element is wider than the limit, 600 pixels by default, by its
 * `width` attribute or by the `width`, `min-width` or `max-width` of its style. A percentage or another unit is not
 * read.
 */
final readonly class MaxWidth implements Rule
{
    public const string NAME = 'max-width';

    public const int DEFAULT_PIXELS = 600;

    private const array PROPERTIES = ['width', 'min-width', 'max-width'];

    public function __construct(
        public int $pixels = self::DEFAULT_PIXELS,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedEmail $email): iterable
    {
        $message = \sprintf('An element is wider than %d pixels.', $this->pixels);

        foreach ($email->document->getElementsByTagName('*') as $element) {
            $name = $element->localName;
            $width = $element->getAttribute('width');

            if (null !== $width && InlineStyle::pixels($width) > $this->pixels) {
                yield Finding::error(self::NAME, $message, $name.' width="'.$width.'"');
            }

            foreach (InlineStyle::declarations($element->getAttribute('style') ?? '') as $property => $value) {
                if (\in_array($property, self::PROPERTIES, true) && InlineStyle::pixels($value) > $this->pixels) {
                    yield Finding::error(self::NAME, $message, $name.' style="'.$property.': '.$value.'"');
                }
            }
        }
    }
}
