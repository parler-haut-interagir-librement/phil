<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Status;

/**
 * Where the template that renders comes from: the site's own version, the version shared by every site, or the default
 * file of the bundle or of the application.
 */
enum TemplateOrigin: string
{
    case Site = 'site';
    case Shared = 'shared';
    case Default = 'default';
}
