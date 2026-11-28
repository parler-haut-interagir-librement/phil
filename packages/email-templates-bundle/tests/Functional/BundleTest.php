<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Functional;

use Override;
use Phil\EmailTemplatesBundle\Application\Contract\SiteScope;
use Phil\EmailTemplatesBundle\Application\EmailComposer;
use Phil\EmailTemplatesBundle\Application\EmailTemplates;
use Phil\EmailTemplatesBundle\Application\Rendering\EmailRenderer;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderOptions;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Infrastructure\DependencyInjection\EmailTemplatesServices;
use Phil\EmailTemplatesBundle\Infrastructure\Messenger\SendTemplatedEmail;
use Phil\EmailTemplatesBundle\Infrastructure\Messenger\SendTemplatedEmailHandler;
use Phil\EmailTemplatesBundle\PhilEmailTemplatesBundle;
use Phil\EmailTemplatesBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;

/**
 * The bundle in a real application: its services wired, a template rendered and composed with the layout of the
 * application's Twig and the images of its directory, the extension the application gives the sandbox, a version saved
 * in the database, a message sent through Messenger and Mailer, the commands, and the stateless CSRF token.
 */
#[CoversClass(PhilEmailTemplatesBundle::class)]
#[CoversClass(EmailTemplatesServices::class)]
#[CoversClass(SendTemplatedEmailHandler::class)]
final class BundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: FrameworkBundle registers none, and no kernel can boot.');
        }

        self::bootKernel();
        ContentSchema::recreate(self::getContainer());
    }

    public function testATemplateIsRenderedAndSavedThroughTheServices(): void
    {
        $renderer = self::getContainer()->get(EmailRenderer::class);
        self::assertInstanceOf(EmailRenderer::class, $renderer);
        $email = $renderer->render('welcome', ['name' => 'Ada'], new RenderOptions(segments: ['vip']));

        self::assertSame('main', $email->site->value);
        self::assertStringContainsString('Test shop', $email->html);
        self::assertStringContainsString('Thank you for being a VIP.', $email->text);
        self::assertSame('logo', $email->embeds[0]->name);

        $templates = self::getContainer()->get(EmailTemplates::class);
        self::assertInstanceOf(EmailTemplates::class, $templates);
        $saved = $templates->save('plain', 'en', TemplateDefinition::fromArray(['subject' => '{{ "hi"|shout }}', 'body' => '<p>x</p>']), 0, author: 'editor-1');
        self::assertSame('2026-11-18 10:00:00', $saved->savedAt->format('Y-m-d H:i:s'));
        self::assertSame('Plain 7', $renderer->render('plain')->subject, 'A draft is not sent.');
        $published = $templates->publish('plain', 'en', author: 'editor-1');
        self::assertSame('2026-11-18 10:00:00', $published->publishedAt?->format('Y-m-d H:i:s'));
        self::assertSame('HI!', $renderer->render('plain')->subject);

        $composer = self::getContainer()->get(EmailComposer::class);
        self::assertInstanceOf(EmailComposer::class, $composer);
        self::assertSame('HI!', $composer->compose('plain')->getSubject());
        $sites = self::getContainer()->get(SiteScope::class);
        self::assertInstanceOf(SiteScope::class, $sites);
        self::assertSame('main', $sites->current()->value);
    }

    public function testAMessageIsSentThroughMessengerAndMailer(): void
    {
        $bus = self::getContainer()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);
        $bus->dispatch(new SendTemplatedEmail('welcome', 'r-7'));

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Welcome, Ada r-7', $email->getSubject());
        self::assertSame('ada@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('"Test shop" <shop@example.com>', $email->getFrom()[0]->toString());
    }

    public function testTheCommandsAndTheStatelessToken(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $tester = new CommandTester($application->find('phil:email-templates:check'));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('9 of 9 default templates checked without error.', $tester->getDisplay());
        self::assertSame(0, new CommandTester($application->find('phil:email-templates:list'))->execute([]));
        $render = new CommandTester($application->find('phil:email-templates:render'));
        self::assertSame(0, $render->execute(['key' => 'ticket', '--locale' => 'fr', '--part' => 'subject']));
        self::assertSame("Votre billet pour Concert d’automne\n", $render->getDisplay());
    }
}
