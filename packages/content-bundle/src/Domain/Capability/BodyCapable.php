<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

use Phil\ContentBundle\Domain\ValueObject\Body;

/**
 * A row with a main text: an article's body, a page's content (`Body`: a source, its format, its HTML).
 *
 * **A dedicated capability, not a field of the template.** The template's values are a free document that
 * the bundle never renders; a body is the one text the bundle compiles, and so the one place where HTML is
 * produced. Keeping it apart gives it typed columns and a single path to the screen:
 *
 * - **On write**, `BodyDataMapper` compiles the source (Markdown to HTML, HTML as written) and purifies the
 *   result, through the application's `MarkupCompiler` and `HtmlPurifier`, then stores the three. A draft keeps
 *   the format its author writes in.
 * - **On publication**, `ContentPublisher` stores on the live row the body the `BodyConverter` makes of the
 *   draft's: the source converted into UJO, the format `ujo`, and the HTML compiled from that UJO, purified.
 * - **On read**, `BodyNormalizer` purifies the stored HTML again before handing it out. A row written around
 *   the data mapper — by a migration, by hand — is purified all the same, and a stricter policy applies to
 *   every row the day it is configured, without recompiling anything.
 *
 * The body lives on the localized row. Use `BodyTrait` for the implementation.
 */
interface BodyCapable
{
    public function getBody(): ?Body;

    public function setBody(?Body $body): void;
}
