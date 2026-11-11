<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Check;

use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rule;

/**
 * The fonts of the document: each must be embedded, and its `OS/2` embedding permissions, as `phil/fonts` reads them,
 * must allow it — not restricted, not limited to bitmaps, and embedded whole when it forbids subsetting.
 */
final readonly class Fonts implements Check
{
    public function check(Document $document, PrintProfile $profile): array
    {
        $findings = [];

        foreach ($document->fonts as $font) {
            $permissions = $font->permissions;

            if (!$font->embedded) {
                $findings[] = Finding::of(Rule::FontNotEmbedded, \sprintf('The font "%s" is not embedded; the printer would replace it.', $font->name), subject: $font->name);
            } elseif (!$permissions->usage->allowsEmbedding()) {
                $findings[] = Finding::of(Rule::FontRestricted, \sprintf('The font "%s" forbids embedding without its owner\'s permission.', $font->name), subject: $font->name);
            } elseif ($permissions->bitmapOnly()) {
                $findings[] = Finding::of(Rule::FontBitmapOnly, \sprintf('The font "%s" may only be embedded as bitmaps, which do not print sharp.', $font->name), subject: $font->name);
            } elseif ($font->subset && !$permissions->allowsSubsetting()) {
                $findings[] = Finding::of(Rule::FontSubsetForbidden, \sprintf('The font "%s" is embedded as a subset, which its licence forbids; embed it whole.', $font->name), subject: $font->name);
            }
        }

        return $findings;
    }
}
