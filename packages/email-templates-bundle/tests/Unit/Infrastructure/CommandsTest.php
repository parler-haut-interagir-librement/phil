<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Infrastructure;

use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Infrastructure\Console\CheckTemplatesCommand;
use Phil\EmailTemplatesBundle\Infrastructure\Console\ListTemplatesCommand;
use Phil\EmailTemplatesBundle\Infrastructure\Console\RenderTemplateCommand;
use Phil\EmailTemplatesBundle\Infrastructure\Yaml\YamlDefaultTemplates;
use Phil\EmailTemplatesBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ListTemplatesCommand::class)]
#[CoversClass(CheckTemplatesCommand::class)]
#[CoversClass(RenderTemplateCommand::class)]
final class CommandsTest extends TestCase
{
    public function testTheListOfASite(): void
    {
        $stage = new Stage();
        $stage->publish('plain', 'en', TemplateDefinition::fromArray(['subject' => 'Own', 'body' => '<p>x</p>']));
        $stage->templates->save('ticket', 'de', TemplateDefinition::fromArray(['subject' => 'Entwurf', 'body' => '<p>x</p>']), 0);

        $tester = new CommandTester(new ListTemplatesCommand($stage->templates));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertMatchesRegularExpression('/plain\s+\|\s+en\s+\|\s+site\s+\|\s+1\s+\|\s+published\s+\|\s+2026-11-18 10:00:00 UTC/', $display);
        self::assertMatchesRegularExpression('/ticket\s+\|\s+de\s+\|\s+\|\s+\|\s+unpublished\s+\|\s+2026-11-18 10:00:00 UTC/', $display);
        self::assertMatchesRegularExpression('/ticket\s+\|\s+fr\s+\|\s+default\s+\|\s+\|\s+\|/', $display);

        self::assertSame(Command::SUCCESS, $tester->execute(['--site' => '']));
        self::assertMatchesRegularExpression('/plain\s+\|\s+en\s+\|\s+default/', $tester->getDisplay());
        self::assertSame(Command::INVALID, $tester->execute(['--site' => 'a b']));
        self::assertStringContainsString('A site is 1 to 64 letters', $tester->getDisplay());
    }

    public function testTheCheckOfEveryDefaultFile(): void
    {
        $stage = new Stage();
        $tester = new CommandTester(new CheckTemplatesCommand($stage->defaults, $stage->validator));

        self::assertSame(Command::SUCCESS, $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));
        self::assertStringContainsString('OK   ticket (fr)', $tester->getDisplay());
        self::assertStringContainsString('9 of 9 default templates checked without error.', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringNotContainsString('OK   ticket (fr)', $tester->getDisplay());

        $directory = sys_get_temp_dir().'/phil-email-check-'.bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory.'/broken.en.yaml', "subject: 'Hi {{ who }}'\nbody: '<p>x</p>'\n");
        file_put_contents($directory.'/fine.en.yaml', "subject: 'Hi'\nbody: '<p>x</p>'\n");
        $broken = new CommandTester(new CheckTemplatesCommand(new YamlDefaultTemplates([$directory]), $stage->validator));

        self::assertSame(Command::FAILURE, $broken->execute([]));
        self::assertStringContainsString("FAIL broken (en)\n     The template reads \"who\", which its schema does not declare.", $broken->getDisplay());
        self::assertStringContainsString('1 of 2 default templates checked without error.', $broken->getDisplay());
    }

    public function testARenderWithTheSampleValues(): void
    {
        $stage = new Stage();
        $tester = new CommandTester(new RenderTemplateCommand($stage->previews));

        self::assertSame(Command::SUCCESS, $tester->execute(['key' => 'welcome', '--part' => 'subject']));
        self::assertSame("Welcome, Ada\n", $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['key' => 'welcome', '--part' => 'preheader']));
        self::assertSame("Your account is ready.\n", $tester->getDisplay());
        $text = new CommandTester(new RenderTemplateCommand($stage->previews));
        self::assertSame(Command::SUCCESS, $text->execute(['key' => 'welcome', '--part' => 'text', '--segment' => ['new']]));
        self::assertStringContainsString('Welcome aboard.', $text->getDisplay());
        $html = new CommandTester(new RenderTemplateCommand($stage->previews));
        self::assertSame(Command::SUCCESS, $html->execute(['key' => 'welcome', '--locale' => 'fr']));
        $page = $html->getDisplay();
        self::assertStringStartsWith('<!doctype html>', $page);
        self::assertStringContainsString('<strong>Ada</strong>', $page);

        $stage->publish('plain', 'en', TemplateDefinition::fromArray(['subject' => 'Shared v1', 'body' => '<p>x</p>']), Site::shared());
        $stage->publish('plain', 'en', TemplateDefinition::fromArray(['subject' => 'Shared v2', 'body' => '<p>x</p>']), Site::shared());
        $stage->templates->save('plain', 'en', TemplateDefinition::fromArray(['subject' => 'Shared draft', 'body' => '<p>x</p>']), 2, Site::shared());
        self::assertSame(Command::SUCCESS, $tester->execute(['key' => 'plain', '--part' => 'subject', '--template-version' => '1']));
        self::assertSame("Shared v1\n", $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['key' => 'plain', '--part' => 'subject']));
        self::assertSame("Shared v2\n", $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['key' => 'plain', '--part' => 'subject', '--draft' => true]));
        self::assertSame("Shared draft\n", $tester->getDisplay());
        self::assertSame('fr', new RenderTemplateCommand($stage->previews, 'fr')->getDefinition()->getOption('locale')->getDefault());
    }

    public function testARenderThatCannotBeDone(): void
    {
        $tester = new CommandTester(new RenderTemplateCommand(new Stage()->previews));

        self::assertSame(Command::INVALID, $tester->execute(['key' => 'welcome', '--part' => 'pdf']));
        self::assertSame("The part is subject, preheader, html or text; the version a whole number from 1.\n", $tester->getDisplay());
        self::assertSame(Command::INVALID, $tester->execute(['key' => 'welcome', '--template-version' => '0']));
        self::assertSame(Command::FAILURE, $tester->execute(['key' => 'nothing']));
        self::assertStringContainsString('No email template "nothing"', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->execute(['key' => 'Bad']));
        self::assertSame(Command::FAILURE, $tester->execute(['key' => 'plain', '--template-version' => '3']));
        self::assertStringContainsString('has no published version 3', $tester->getDisplay());
    }
}
