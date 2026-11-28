<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Functional;

use Override;
use Phil\EmailTemplatesBundle\Infrastructure\Content\ContentTemplateStore;
use Phil\EmailTemplatesBundle\Infrastructure\Content\Entity\EmailTemplateContent;
use Phil\EmailTemplatesBundle\Infrastructure\Content\TemplatePayload;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The content store on the SQLite file of the test application.
 */
#[CoversClass(ContentTemplateStore::class)]
#[CoversClass(EmailTemplateContent::class)]
#[CoversClass(TemplatePayload::class)]
final class ContentStoreTest extends ContentStoreTestCase
{
    #[Override]
    protected function databaseUrl(): ?string
    {
        return null;
    }
}
