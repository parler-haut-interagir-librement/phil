<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Functional;

use Override;
use Phil\UploadScan\FakeScanner;
use Phil\UploadScanBundle\Application\Contract\QuarantineInventory;
use Phil\UploadScanBundle\Application\QuarantinedFile;
use Phil\UploadScanBundle\Application\QuarantinePurger;
use Phil\UploadScanBundle\Infrastructure\Admin\QuarantineAdminController;
use Phil\UploadScanBundle\Infrastructure\Console\PurgeQuarantineCommand;
use Phil\UploadScanBundle\Infrastructure\Validator\CleanFileValidator;
use Phil\UploadScanBundle\PhilUploadScanBundle;
use Phil\UploadScanBundle\Tests\Functional\App\TestKernel;
use Phil\UploadScanBundle\Tests\Unit\Fixture\Temporary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The whole path in a booted application: a file sent to an upload page, scanned by `#[CleanFile]`, an infected one
 * refused and quarantined, listed on the administration page for an administrator only, deleted for good there behind
 * its CSRF token, the purge command registered. The scanner is the fake one, or a real clamd when
 * `PHIL_UPLOAD_SCAN_CLAMD` names its address (`tcp://127.0.0.1:3310`): the group `clamd` then runs against it.
 */
#[CoversClass(PhilUploadScanBundle::class)]
#[CoversClass(CleanFileValidator::class)]
#[CoversClass(QuarantineAdminController::class)]
#[CoversClass(PurgeQuarantineCommand::class)]
#[CoversClass(QuarantinePurger::class)]
#[Group('clamd')]
final class UploadToQuarantineTest extends WebTestCase
{
    private KernelBrowser $browser;

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        $clamd = getenv('PHIL_UPLOAD_SCAN_CLAMD');

        return new TestKernel('test', true, \is_string($clamd) && '' !== $clamd ? $clamd : null);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $kernel = self::$kernel;
        self::assertInstanceOf(TestKernel::class, $kernel);
        Temporary::remove($kernel->quarantineDirectory());
    }

    public function testACleanFileIsAcceptedAndNothingIsQuarantined(): void
    {
        $this->browser->request('POST', '/upload', files: ['file' => $this->upload('A first chapter.', 'chapter.txt')]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame([], $this->inventory()->files(10));
    }

    public function testAnInfectedFileIsRefusedQuarantinedListedAndDeleted(): void
    {
        $this->browser->request('POST', '/upload', files: ['file' => $this->upload(FakeScanner::eicar(), 'Chapter 2.docx')]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('This file contains a threat and was refused.', $this->browser->getResponse()->getContent());

        $files = $this->inventory()->files(10);
        self::assertCount(1, $files);
        $file = $files[0];
        self::assertInstanceOf(QuarantinedFile::class, $file);
        self::assertSame('Chapter 2.docx', $file->name);
        self::assertNotNull($file->signature);
        self::assertStringContainsStringIgnoringCase('eicar', $file->signature);
        self::assertSame(\strlen(FakeScanner::eicar()), $file->size);

        $this->browser->request('GET', '/admin/quarantine/');
        self::assertResponseStatusCodeSame(401);

        $this->browser->request('GET', '/admin/quarantine/', server: ['PHP_AUTH_USER' => 'reader', 'PHP_AUTH_PW' => 'reader']);
        self::assertResponseStatusCodeSame(403);

        $crawler = $this->browser->request('GET', '/admin/quarantine/', server: ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody tr td', 'Chapter 2.docx');
        self::assertSelectorTextContains('tbody code', $file->id);
        self::assertStringNotContainsString(FakeScanner::eicar(), (string) $this->browser->getResponse()->getContent());

        $this->browser->request('POST', '/admin/quarantine/'.$file->id.'/delete', ['_token' => 'forged'], server: ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin']);
        self::assertResponseStatusCodeSame(400);
        self::assertCount(1, $this->inventory()->files(10));

        $this->browser->submit($crawler->selectButton('Delete for good')->form(), [], ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin']);
        self::assertResponseRedirects('/admin/quarantine/?done=deleted', 303);
        self::assertSame([], $this->inventory()->files(10));
    }

    public function testThePurgeCommandIsRegistered(): void
    {
        $this->browser->request('POST', '/upload', files: ['file' => $this->upload(FakeScanner::eicar(), 'cover.png')]);

        $application = new Application(self::$kernel ?? self::bootKernel());
        $tester = new CommandTester($application->find('phil:upload-scan:purge-quarantine'));

        self::assertSame(0, $tester->execute(['--dry-run' => true]));
        self::assertStringContainsString('0 quarantined file(s) kept for more than 30 day(s) would be deleted.', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
        self::assertCount(1, $this->inventory()->files(10));
    }

    private function inventory(): QuarantineInventory
    {
        $inventory = self::getContainer()->get(QuarantineInventory::class);
        self::assertInstanceOf(QuarantineInventory::class, $inventory);

        return $inventory;
    }

    private function upload(string $contents, string $name): UploadedFile
    {
        return new UploadedFile(Temporary::file($contents, $name), $name, test: true);
    }
}
