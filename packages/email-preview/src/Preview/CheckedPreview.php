<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Preview;

use Phil\EmailPreview\Report;
use Phil\EmailTemplatesBundle\Application\Preview\PreviewVariant;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedEmail;

/**
 * One preview of a template and what its check found: the variant, the email rendered with its sample data, and the
 * report shown beside it.
 */
final readonly class CheckedPreview
{
    public function __construct(
        public PreviewVariant $variant,
        public RenderedEmail $email,
        public Report $report,
    ) {
    }
}
