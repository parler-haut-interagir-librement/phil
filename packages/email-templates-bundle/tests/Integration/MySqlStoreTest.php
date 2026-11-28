<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Integration;

use Override;
use Phil\EmailTemplatesBundle\Infrastructure\Content\ContentTemplateStore;
use Phil\EmailTemplatesBundle\Infrastructure\Content\Entity\EmailTemplateContent;
use Phil\EmailTemplatesBundle\Infrastructure\Content\TemplateKeysCollation;
use Phil\EmailTemplatesBundle\Infrastructure\Content\TemplatePayload;
use Phil\EmailTemplatesBundle\Tests\Functional\ContentStoreTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The content store on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`: the site and the key in `utf8mb4_bin`.
 */
#[CoversClass(ContentTemplateStore::class)]
#[CoversClass(EmailTemplateContent::class)]
#[CoversClass(TemplatePayload::class)]
#[CoversClass(TemplateKeysCollation::class)]
final class MySqlStoreTest extends ContentStoreTestCase
{
    #[Override]
    protected function databaseUrl(): string
    {
        $url = getenv('PHIL_TEST_MYSQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_TEST_MYSQL_URL to run this test against a real server.');
        }

        return $url;
    }
}
