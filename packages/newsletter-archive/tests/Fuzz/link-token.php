<?php

declare(strict_types=1);

/*
 * The tokens of the archive's links on texts nobody wrote: whatever it reads, the reader gives an issue or nothing,
 * never throws nor warns, and never gives the issue of a token it did not issue.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Doctrine\DBAL\DriverManager;
use Phil\NewsletterArchive\Application\ArchiveReader;
use Phil\NewsletterArchive\Application\LinkTokens;
use Phil\NewsletterArchive\Domain\Model\ArchivedIssue;
use Phil\NewsletterArchive\Infrastructure\Dbal\ArchiveSchema;
use Phil\NewsletterArchive\Infrastructure\Dbal\DbalArchiveRepository;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$manager = $connection->createSchemaManager();
$manager->migrateSchema(ArchiveSchema::configure($manager->introspectSchema()));
$issues = new DbalArchiveRepository($connection);
$token = LinkTokens::generate();
$issue = new ArchivedIssue('c1', 'default', 'a-shared-issue', 'l1', 'news', 'News', 'A shared issue', '', '<p>Hello</p>', new DateTimeImmutable(), new DateTimeImmutable());
$issue->share(LinkTokens::hash($token), new DateTimeImmutable());
$issues->save($issue);
$reader = new ArchiveReader($issues);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $token): void {
    if ($reader->shared($input) instanceof ArchivedIssue && $input !== $token) {
        throw new LogicException('A token the archive did not issue opened an issue.');
    }
});
$config->setMaxLen(200);
