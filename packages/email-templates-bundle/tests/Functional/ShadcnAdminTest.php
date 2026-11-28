<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Functional;

use Override;
use Phil\EmailTemplatesBundle\Infrastructure\Http\AdminController;
use Phil\EmailTemplatesBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The same pages drawn by the recipes of the Shadcn kit of the Symfony UX Toolkit, installed at the root of the
 * components directory: the same fields, frames, buttons and messages, each carrying the slot of its recipe.
 */
#[CoversClass(AdminController::class)]
final class ShadcnAdminTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel(['environment' => TestKernel::SHADCN, 'debug' => false] + $options);
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: FrameworkBundle registers none, and no kernel can boot.');
        }

        if (null === TestKernel::shadcnKit()) {
            self::markTestSkipped('The Symfony UX Toolkit is not installed.');
        }

        $this->client = self::createClient();
        $this->client->disableReboot();

        ContentSchema::recreate(self::getContainer());

        $this->client->loginUser(new InMemoryUser('editor-1', 'x', ['ROLE_MAIL']));
    }

    public function testTheListAndThePageThroughTheShadcnRecipes(): void
    {
        $list = $this->client->request('GET', '/admin/email-templates/');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $list->filter('[data-slot="table-container"] table'));
        self::assertSame('/admin/email-templates/?scope=shared', $list->filter('a[data-slot="button"]')->attr('href'));
        self::assertContains('Default', $list->filter('[data-slot="badge"]')->each(static fn (Crawler $badge): string => $badge->text()));

        $page = $this->client->request('GET', '/admin/email-templates/welcome/en');
        self::assertResponseIsSuccessful();
        self::assertSame('Welcome, {{ name }}', $page->filter('input[data-slot="input"]#phil-email-template-subject')->attr('value'));
        self::assertCount(4, $page->filter('textarea[data-slot="textarea"]'));
        self::assertSame('html', $page->filter('select[data-slot="native-select"] option[selected]')->attr('value'));
        self::assertSame('Subject', $page->filter('label[for="phil-email-template-subject"]')->text());
        self::assertSame('Save the draft', $page->filter('button[data-slot="button"][value="save"]')->text());
        self::assertSame('submit', $page->filter('button[data-slot="button"][value="save"]')->attr('type'));
        self::assertCount(4, $page->filter('iframe[sandbox=""]'));
    }

    public function testTheMessagesThroughTheShadcnAlert(): void
    {
        $this->client->request('POST', '/admin/email-templates/plain/en', ['_token' => 'csrf-token', 'action' => 'save', 'subject' => 'Hi', 'body' => '<p>x</p>', 'expected' => '0'], server: ['HTTP_ORIGIN' => 'http://localhost']);
        $page = $this->client->request('GET', '/admin/email-templates/plain/en?done=saved');
        self::assertSame('The draft is saved. Publish it to send it.', $page->filter('[role="status"] [data-slot="alert"]')->text());
        $this->client->request('POST', '/admin/email-templates/plain/en', ['_token' => 'csrf-token', 'action' => 'publish'], server: ['HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseStatusCodeSame(303);

        $page = $this->client->request('POST', '/admin/email-templates/plain/en', ['_token' => 'csrf-token', 'action' => 'save', 'subject' => 'Hi {{ who }}', 'body' => '<p>x</p>', 'expected' => '1'], server: ['HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('The template reads "who", which its schema does not declare.', $page->filter('[data-slot="alert"]#phil-email-template-errors li')->text());
        self::assertCount(1, $page->filter('button[data-slot="button"][value="reset"]'));
    }
}
