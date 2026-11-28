<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Preview;

use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\EmailChecker;
use Phil\EmailPreview\Sending;
use Phil\EmailTemplatesBundle\Application\Contract\PreviewRenderer;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;

/**
 * Each preview of a template, rendered by the preview renderer of phil/email-templates-bundle with its sample data and
 * checked: the variants the renderer gives, in its order, each with its report.
 */
final readonly class PreviewChecks
{
    public function __construct(
        private PreviewRenderer $renderer,
        private EmailChecker $checker = new EmailChecker(),
    ) {
    }

    /**
     * The previews of what the site sends, or of its draft when `$draft` is true, as they would be sent.
     *
     * @return list<CheckedPreview>
     */
    public function check(TemplateKey $key, Site $site, Locale $locale, bool $draft = false, Sending $sending = new Sending()): array
    {
        $previews = [];

        foreach ($this->renderer->variants($key, $site, $locale, $draft) as $variant) {
            $email = $this->renderer->render($variant);
            $previews[] = new CheckedPreview($variant, $email, $this->checker->check(CheckedEmail::fromRendered($email, $sending)));
        }

        return $previews;
    }
}
