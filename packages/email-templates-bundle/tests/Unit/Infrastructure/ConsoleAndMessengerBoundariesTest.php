<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Infrastructure;

use Phil\EmailTemplatesBundle\Application\Contract\DeliveryResolver;
use Phil\EmailTemplatesBundle\Application\Delivery;
use Phil\EmailTemplatesBundle\Application\EmailComposer;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Infrastructure\Console\ListTemplatesCommand;
use Phil\EmailTemplatesBundle\Infrastructure\Console\RenderTemplateCommand;
use Phil\EmailTemplatesBundle\Infrastructure\Messenger\SendTemplatedEmail;
use Phil\EmailTemplatesBundle\Infrastructure\Messenger\SendTemplatedEmailHandler;
use Phil\EmailTemplatesBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

#[CoversClass(ListTemplatesCommand::class)]
#[CoversClass(RenderTemplateCommand::class)]
#[CoversClass(SendTemplatedEmailHandler::class)]
#[CoversClass(SendTemplatedEmail::class)]
#[CoversClass(Delivery::class)]
final class ConsoleAndMessengerBoundariesTest extends TestCase
{
    public function testTheListHasItsHeaders(): void
    {
        $tester = new CommandTester(new ListTemplatesCommand(new Stage()->templates));
        $tester->execute([]);

        self::assertMatchesRegularExpression('/\|\s+Key\s+\|\s+Locale\s+\|\s+Origin\s+\|\s+Version\s+\|\s+Draft\s+\|\s+Saved at\s+\|/', $tester->getDisplay());
    }

    public function testTheSegmentsAreGivenOneOptionEach(): void
    {
        $stage = new Stage();
        $command = new RenderTemplateCommand($stage->previews);
        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, $command->run(new ArgvInput(['phil', 'welcome', '--part=text', '--segment=new', '--segment=vip']), $output));
        self::assertStringContainsString('Welcome aboard.', $output->fetch());
        self::assertTrue($command->getDefinition()->getOption('segment')->isArray());
    }

    public function testThePlainTextPartIsTheTextAndNotTheHtml(): void
    {
        $tester = new CommandTester(new RenderTemplateCommand(new Stage()->previews));

        self::assertSame(Command::SUCCESS, $tester->execute(['key' => 'plain', '--part' => 'text']));
        self::assertSame("Code: 7\n", $tester->getDisplay());
    }

    public function testTheSiteOfTheDeliveryIsTheOneRendered(): void
    {
        $stage = new Stage();
        $definition = static fn (string $subject): TemplateDefinition => TemplateDefinition::fromArray(['subject' => $subject, 'body' => '<p>x</p>', 'variables' => ['name' => ['type' => 'string']]]);
        $stage->publish('welcome', 'en', $definition('From the message'), Site::fromString('message'));
        $stage->publish('welcome', 'en', $definition('From the delivery'), Site::fromString('delivery'));

        $mailer = new class implements MailerInterface {
            /** @var list<RawMessage> */
            public array $sent = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->sent[] = $message;
            }
        };
        $resolver = new class implements DeliveryResolver {
            public function resolve(string $template, string $reference): Delivery
            {
                return new Delivery([new Address('ada@example.com')], [], 'en', [], 'delivery');
            }
        };

        new SendTemplatedEmailHandler($resolver, new EmailComposer($stage->renderer), $mailer)(new SendTemplatedEmail('welcome', 'r-1', 'message'));

        $email = $mailer->sent[0];
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('From the delivery', $email->getSubject());
    }
}
