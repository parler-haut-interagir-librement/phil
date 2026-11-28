<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\SitesConsoleBundle\Application\Console\ConfigurationPushes;
use Phil\SitesConsoleBundle\Application\Console\UpdatePlans;
use Phil\SitesConsoleBundle\Infrastructure\Console\KeysCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\KeysRotateCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\PullCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\PurgeCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\PushCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\SiteAddCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\SiteRemoveCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\UpdatePlanCommand;
use Phil\SitesConsoleBundle\Infrastructure\Console\UpdateRunCommand;
use Phil\SitesConsoleBundle\Infrastructure\Http\ConsoleController;
use Phil\SitesConsoleBundle\Infrastructure\Messenger\DeliverConfigurationPushHandler;
use Phil\SitesConsoleBundle\Infrastructure\Messenger\PullSiteHandler;
use Phil\SitesConsoleBundle\Infrastructure\Messenger\RunUpdatePlanHandler;
use Phil\SitesConsoleBundle\Tests\Functional\App\MemoryApplier;
use Phil\SitesConsoleBundle\Tests\Functional\App\TestKernel;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(ConsoleController::class)]
#[CoversClass(KeysCommand::class)]
#[CoversClass(KeysRotateCommand::class)]
#[CoversClass(SiteAddCommand::class)]
#[CoversClass(SiteRemoveCommand::class)]
#[CoversClass(PullCommand::class)]
#[CoversClass(PushCommand::class)]
#[CoversClass(UpdatePlanCommand::class)]
#[CoversClass(UpdateRunCommand::class)]
#[CoversClass(PurgeCommand::class)]
#[CoversClass(PullSiteHandler::class)]
#[CoversClass(DeliverConfigurationPushHandler::class)]
#[CoversClass(RunUpdatePlanHandler::class)]
final class SitesConsoleTest extends TestCase
{
    private ?TestKernel $kernel = null;

    #[Override]
    protected function tearDown(): void
    {
        if ($this->kernel instanceof TestKernel) {
            $this->kernel->shutdown();
            new Filesystem()->remove($this->kernel->root());
        }
    }

    public function testTheConsoleReadsItsSitesAndShowsThem(): void
    {
        $this->addShop();
        $browser = $this->browser('operator');

        $browser->request('GET', '/admin/sites/');
        self::assertSame(200, $browser->getResponse()->getStatusCode());
        self::assertStringContainsString('no-store', (string) $browser->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('Never', $browser->getCrawler()->filter('#site-shop')->text());
        self::assertStringNotContainsString('<script', (string) $browser->getResponse()->getContent());

        $browser->submitForm('Read every site now');
        self::assertSame(303, $browser->getResponse()->getStatusCode());
        $browser->followRedirect();
        $row = $browser->getCrawler()->filter('#site-shop');
        self::assertStringContainsString('Up', $row->text());
        self::assertStringContainsString('2026-11-20 10:00 UTC', $row->text());
        self::assertSame('outline', $row->filter('[data-slot="badge"]')->attr('data-variant'));

        $browser->request('GET', '/admin/sites/sites/shop');
        $page = $browser->getCrawler();
        self::assertSame('Shop Up', trim($page->filter('h1')->text()));
        self::assertStringContainsString('https://shop.example.com/_sites-console', $page->text());
        self::assertStringContainsString('phil/url', $page->text());
        self::assertStringContainsString('0.1.4', $page->text());
        self::assertStringContainsString('Site registered', $page->text());

        $browser->submitForm('Read this site now');
        self::assertSame('/admin/sites/sites/shop', $browser->getResponse()->headers->get('Location'));

        $browser->request('GET', '/admin/sites/versions');
        self::assertStringContainsString('phil/url', $browser->getCrawler()->filter('table')->text());
        self::assertStringNotContainsString('symfony/http-kernel', $browser->getCrawler()->filter('table')->text());

        $browser->request('GET', '/admin/sites/journal');
        self::assertStringContainsString('ada', $browser->getCrawler()->filter('table')->text());
    }

    public function testPushesAndUpdatesFromTheCommandsAndTheScreens(): void
    {
        $this->addShop();
        $applier = $this->container()->get(MemoryApplier::class);
        $this->assertInstanceOf(MemoryApplier::class, $applier);

        $push = $this->command('phil:sites-console:push', ['settings' => '{"mail.footer":"Hello"}', '--actor' => 'ada']);
        self::assertSame(Command::SUCCESS, $push->getStatusCode(), $push->getDisplay());
        self::assertStringContainsString('shop   applied', $push->getDisplay());
        self::assertSame([['mail.footer' => 'Hello']], $applier->applied);

        $refused = $this->command('phil:sites-console:push', ['settings' => '{"cache.ttl":1}']);
        self::assertSame(Command::FAILURE, $refused->getStatusCode());
        self::assertStringContainsString('This site does not accept the keys cache.ttl.', $refused->getDisplay());

        $pushes = $this->container()->get(ConfigurationPushes::class);
        $this->assertInstanceOf(ConfigurationPushes::class, $pushes);
        $failed = $pushes->recent(1)[0];
        $browser = $this->browser('operator');
        $browser->request('GET', '/admin/sites/pushes');
        self::assertStringContainsString($failed->id(), (string) $browser->getResponse()->getContent());
        $browser->request('GET', '/admin/sites/pushes/'.$failed->id());
        self::assertStringContainsString('Failed', $browser->getCrawler()->filter('#delivery-shop')->text());
        $browser->submitForm('Deliver to the sites not reached');
        self::assertSame('/admin/sites/pushes/'.$failed->id(), $browser->getResponse()->headers->get('Location'));

        $retry = $this->command('phil:sites-console:push', ['--retry' => $failed->id()]);
        self::assertSame(Command::FAILURE, $retry->getStatusCode());

        $plan = $this->command('phil:sites-console:update:plan', ['package' => 'phil/url', 'version' => '0.1.5', '--batch-size' => 1]);
        self::assertSame(Command::SUCCESS, $plan->getStatusCode());
        $plans = $this->container()->get(UpdatePlans::class);
        $this->assertInstanceOf(UpdatePlans::class, $plans);
        $planned = $plans->recent(1)[0];

        $browser->request('GET', '/admin/sites/updates');
        self::assertStringContainsString('phil/url 0.1.5', $browser->getCrawler()->text());
        $browser->request('GET', '/admin/sites/updates/'.$planned->id());
        self::assertStringContainsString('Pending', $browser->getCrawler()->filter('#step-shop')->text());
        $browser->submitForm('Run the next batch');
        $browser->followRedirect();
        self::assertStringContainsString('Succeeded', $browser->getCrawler()->filter('#step-shop')->text());
        self::assertStringContainsString('Completed', $browser->getCrawler()->filter('h1')->text());
        self::assertSame(['phil/url 0.1.5'], $applier->updates);

        $this->command('phil:sites-console:update:plan', ['package' => 'phil/url', 'version' => '9.9.9', '--site' => ['shop']]);
        $id = $plans->recent(1)[0]->id();
        $run = $this->command('phil:sites-console:update:run', ['plan' => $id]);
        self::assertSame(Command::FAILURE, $run->getStatusCode());
        self::assertStringContainsString('The tests failed.', $run->getDisplay());
        self::assertStringContainsString('stopped', $run->getDisplay());
    }

    public function testTheCommandsRefuseWhatTheyCannotDo(): void
    {
        self::assertSame(Command::INVALID, $this->command('phil:sites-console:site:add', ['id' => 'Shop', 'url' => 'https://shop.example.com', 'public-key' => Keys::site()->verifyingKey()->toBase64()])->getStatusCode());
        self::assertSame(Command::FAILURE, $this->command('phil:sites-console:site:remove', ['id' => 'none'])->getStatusCode());
        self::assertSame(Command::FAILURE, $this->command('phil:sites-console:pull', ['--site' => ['none']])->getStatusCode());
        self::assertSame(Command::INVALID, $this->command('phil:sites-console:push', ['settings' => '[1'])->getStatusCode());
        self::assertSame(Command::INVALID, $this->command('phil:sites-console:push', ['settings' => '"x"'])->getStatusCode());
        self::assertSame(Command::INVALID, $this->command('phil:sites-console:update:plan', ['package' => 'phil/url', 'version' => '1', '--site' => ['none']])->getStatusCode());
        self::assertSame(Command::FAILURE, $this->command('phil:sites-console:update:run', ['plan' => 'none'])->getStatusCode());

        $keys = $this->command('phil:sites-console:keys');
        self::assertSame(Command::SUCCESS, $keys->getStatusCode());
        self::assertMatchesRegularExpression('/Private key.*\n[A-Za-z0-9_-]{86}\n\nPublic key.*\n[A-Za-z0-9_-]{43}/', $keys->getDisplay());
    }

    public function testAPullOfAnUnreachableSiteAndTheRemoval(): void
    {
        $this->addShop();
        self::assertSame(Command::SUCCESS, $this->command('phil:sites-console:site:add', ['id' => 'blog', 'url' => 'https://blog.example.com/_sites-console', 'public-key' => Keys::site()->verifyingKey()->toBase64(), '--group' => ['eu']])->getStatusCode());

        $pull = $this->command('phil:sites-console:pull', []);
        self::assertSame(Command::FAILURE, $pull->getStatusCode());
        self::assertStringContainsString('blog   unreachable', $pull->getDisplay());
        self::assertStringContainsString('The agent answered 401 without a valid signature.', $pull->getDisplay());
        self::assertSame(Command::SUCCESS, $this->command('phil:sites-console:pull', ['--site' => ['shop']])->getStatusCode());
        self::assertSame(Command::FAILURE, $this->command('phil:sites-console:pull', ['--group' => 'eu'])->getStatusCode());

        self::assertStringContainsString('0 snapshots removed.', $this->command('phil:sites-console:purge')->getDisplay());
        self::assertSame(Command::SUCCESS, $this->command('phil:sites-console:site:remove', ['id' => 'blog'])->getStatusCode());
    }

    public function testTheScreensTakeTheirRoles(): void
    {
        $this->addShop();

        $anonymous = $this->browser(null);
        $anonymous->request('GET', '/admin/sites/');
        self::assertSame(401, $anonymous->getResponse()->getStatusCode());

        $nobody = $this->browser('nobody');
        $nobody->request('GET', '/admin/sites/');
        self::assertSame(403, $nobody->getResponse()->getStatusCode());

        $viewer = $this->browser('viewer');
        $viewer->request('GET', '/admin/sites/');
        self::assertSame(200, $viewer->getResponse()->getStatusCode());
        self::assertCount(0, $viewer->getCrawler()->filter('form'));
        $viewer->request('POST', '/admin/sites/pull');
        self::assertSame(403, $viewer->getResponse()->getStatusCode());

        $operator = $this->browser('operator');
        $operator->request('POST', '/admin/sites/pull', ['_token' => 'forged']);
        self::assertSame(403, $operator->getResponse()->getStatusCode());
        $operator->request('GET', '/admin/sites/sites/none');
        self::assertSame(404, $operator->getResponse()->getStatusCode());
        $operator->request('GET', '/admin/sites/pushes/NONE');
        self::assertSame(404, $operator->getResponse()->getStatusCode());
        $operator->request('GET', '/admin/sites/updates/NONE');
        self::assertSame(404, $operator->getResponse()->getStatusCode());
    }

    public function testTheEmptyScreens(): void
    {
        $browser = $this->browser('viewer');

        foreach (['/admin/sites/' => 'No site is registered yet.', '/admin/sites/versions' => 'No watched package has been read yet.', '/admin/sites/journal' => 'The journal is empty.', '/admin/sites/pushes' => 'No configuration has been pushed yet.', '/admin/sites/updates' => 'No update has been planned yet.'] as $path => $text) {
            $browser->request('GET', $path);
            self::assertStringContainsString($text, $browser->getCrawler()->text(), $path);
        }
    }

    public function testTheConsoleOnDoctrine(): void
    {
        $this->kernel = new TestKernel('test', true, 'doctrine', true);
        $this->kernel->boot();

        $entityManager = $this->container()->get('doctrine.orm.default_entity_manager');
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $tool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        self::assertCount(6, $metadata);
        $this->addShop();
        self::assertSame(Command::SUCCESS, $this->command('phil:sites-console:pull')->getStatusCode());
        self::assertSame(Command::SUCCESS, $this->command('phil:sites-console:push', ['settings' => '{"mail.footer":"Hi"}'])->getStatusCode());

        $rotate = $this->command('phil:sites-console:keys:rotate');
        self::assertSame(Command::SUCCESS, $rotate->getStatusCode(), $rotate->getDisplay());
        $entityManager->clear();
        self::assertSame(Command::SUCCESS, $this->command('phil:sites-console:pull')->getStatusCode(), 'The console signs with the next key, read from the table.');
    }

    public function testTheConsoleKeyRotatesOnEverySiteAndTheRotationResumes(): void
    {
        $this->kernel = new TestKernel('test', true, 'memory', true);
        $this->kernel->boot();
        $this->addShop();
        self::assertSame(Command::SUCCESS, $this->command('phil:sites-console:site:add', ['id' => 'blog', 'url' => 'https://blog.example.com/_sites-console', 'public-key' => Keys::site()->verifyingKey()->toBase64()])->getStatusCode());

        $stopped = $this->command('phil:sites-console:keys:rotate', ['--actor' => 'ada']);
        self::assertSame(Command::FAILURE, $stopped->getStatusCode());
        self::assertStringContainsString(': staging', $stopped->getDisplay());
        self::assertMatchesRegularExpression('/blog\s+failed\s+pending\s+The agent answered 401 without a valid signature\./', $stopped->getDisplay());
        self::assertMatchesRegularExpression('/shop\s+pending\s+pending/', $stopped->getDisplay(), 'The run stops at the first site that fails.');
        self::assertStringContainsString('Fix it, then run this command again to resume.', (string) preg_replace('/\s+/', ' ', $stopped->getDisplay()));
        self::assertSame(Command::SUCCESS, $this->command('phil:sites-console:pull', ['--site' => ['shop']])->getStatusCode(), 'The current key still signs.');

        self::assertSame(Command::SUCCESS, $this->command('phil:sites-console:site:remove', ['id' => 'blog'])->getStatusCode());
        $finished = $this->command('phil:sites-console:keys:rotate', ['--actor' => 'ada']);
        self::assertSame(Command::SUCCESS, $finished->getStatusCode(), $finished->getDisplay());
        self::assertMatchesRegularExpression('/blog\s+skipped\s+skipped/', $finished->getDisplay());
        self::assertMatchesRegularExpression('/shop\s+done\s+done\s+The key is retired\./', $finished->getDisplay());
        self::assertStringContainsString('set agent.console_public_key to '.Keys::next()->verifyingKey()->toBase64(), (string) preg_replace('/\s+/', ' ', $finished->getDisplay()));
        self::assertSame(Command::SUCCESS, $this->command('phil:sites-console:pull', ['--site' => ['shop']])->getStatusCode(), 'The next key signs, and the site accepts it alone.');

        $browser = $this->browser('operator');
        $browser->request('GET', '/admin/sites/journal');

        $journal = $browser->getCrawler()->filter('table')->text();
        foreach (['Key rotation started', 'Next key staged', 'Signing switched to the next key', 'Previous key retired', 'Key rotation finished'] as $line) {
            self::assertStringContainsString($line, $journal);
        }
    }

    public function testTheRotationWithoutANextKeyPrintsOne(): void
    {
        $rotate = $this->command('phil:sites-console:keys:rotate');

        self::assertSame(Command::INVALID, $rotate->getStatusCode());
        self::assertMatchesRegularExpression('/console\.next_private_key.*\n[A-Za-z0-9_-]{86}\n\n.*\n[A-Za-z0-9_-]{43}/', $rotate->getDisplay());
    }

    private function addShop(): void
    {
        $add = $this->command('phil:sites-console:site:add', ['id' => 'shop', 'url' => 'https://shop.example.com/_sites-console', 'public-key' => Keys::site()->verifyingKey()->toBase64(), '--name' => 'Shop', '--actor' => 'ada']);
        self::assertSame(Command::SUCCESS, $add->getStatusCode(), $add->getDisplay());
        self::assertStringContainsString('The site "shop" is registered at https://shop.example.com/_sites-console.', $add->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function command(string $name, array $input = []): CommandTester
    {
        $application = new Application($this->kernel());
        $tester = new CommandTester($application->find($name));
        $tester->execute($input);

        return $tester;
    }

    private function browser(?string $user): KernelBrowser
    {
        $browser = new KernelBrowser($this->kernel());
        $browser->followRedirects(false);
        $browser->disableReboot();

        if (null !== $user) {
            $browser->setServerParameters(['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => 'p']);
        }

        return $browser;
    }

    private function container(): ContainerInterface
    {
        $container = $this->kernel()->getContainer()->get('test.service_container');
        $this->assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function kernel(): TestKernel
    {
        if (!$this->kernel instanceof TestKernel) {
            $this->kernel = new TestKernel('test', true);
            $this->kernel->boot();
        }

        return $this->kernel;
    }
}
