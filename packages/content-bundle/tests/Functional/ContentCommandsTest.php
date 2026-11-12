<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional;

use Phil\ContentBundle\Infrastructure\Console\ContentCommand;
use Phil\ContentBundle\Infrastructure\Console\CreateContentCommand;
use Phil\ContentBundle\Infrastructure\Console\EditContentCommand;
use Phil\ContentBundle\Infrastructure\Console\ListContentCommand;
use Phil\ContentBundle\Infrastructure\Console\PublishContentCommand;
use Phil\ContentBundle\Infrastructure\Console\UnpublishContentCommand;
use Phil\ContentBundle\Infrastructure\Doctrine\ConstructorContentRootFactory;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineContentRootRepository;
use Phil\ContentBundle\Tests\Functional\App\Entity\StaticPage;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The console commands, the way an editor uses them before an administration exists.
 */
#[CoversClass(ContentCommand::class)]
#[CoversClass(CreateContentCommand::class)]
#[CoversClass(EditContentCommand::class)]
#[CoversClass(PublishContentCommand::class)]
#[CoversClass(UnpublishContentCommand::class)]
#[CoversClass(ListContentCommand::class)]
#[CoversClass(ConstructorContentRootFactory::class)]
#[CoversClass(DoctrineContentRootRepository::class)]
final class ContentCommandsTest extends FunctionalTestCase
{
    public function testAnEditorCreatesEditsPublishesListsAndUnpublishes(): void
    {
        $create = $this->command('phil:content:create');
        self::assertSame(Command::SUCCESS, $create->execute(
            ['resource-key' => 'static_page', 'locale' => 'fr', '--field' => ['slug=a-propos', 'bodySource=À propos'], '--publish' => true],
            ['verbosity' => OutputInterface::VERBOSITY_QUIET],
        ));
        self::assertSame("static_page:1\n", $create->getDisplay());
        self::assertSame(200, $this->request('/fr/a-propos')->getStatusCode());

        $edit = $this->command('phil:content:edit');
        self::assertSame(Command::SUCCESS, $edit->execute(['reference' => 'static_page:1', 'locale' => 'fr', '--json' => ['bodySource="Qui sommes-nous"']]));
        self::assertStringContainsString('Edited the draft of static_page:1.', $edit->getDisplay());

        $list = $this->command('phil:content:list');
        self::assertSame(Command::SUCCESS, $list->execute([]));
        self::assertMatchesRegularExpression('/static_page:1\s+fr\s+2\s+draft\s+a-propos\s+1/', $list->getDisplay());

        $publish = $this->command('phil:content:publish');
        self::assertSame(Command::SUCCESS, $publish->execute(['reference' => 'static_page:1', 'locale' => 'fr']));
        self::assertStringContainsString('Published static_page:1 in fr.', $publish->getDisplay());
        self::assertStringContainsString('Qui sommes-nous', (string) $this->request('/fr/a-propos')->getContent());

        $unpublish = $this->command('phil:content:unpublish');
        self::assertSame(Command::SUCCESS, $unpublish->execute(['reference' => 'static_page:1', 'locale' => 'fr']));
        self::assertStringContainsString('Unpublished static_page:1 in fr.', $unpublish->getDisplay());
        self::assertSame(404, $this->request('/fr/a-propos')->getStatusCode());

        self::assertSame(Command::SUCCESS, $list->execute(['resource-key' => ['static_page']]));
        self::assertMatchesRegularExpression('/static_page:1\s+fr\s+2\s+unpublished\s+a-propos\s*\n/', $list->getDisplay());
    }

    public function testAFileFeedsTheDraft(): void
    {
        $file = sys_get_temp_dir().'/phil-content-news-'.bin2hex(random_bytes(4)).'.yaml';
        file_put_contents($file, "templateKey: news\nslug: ouverture\nexcerptTitle: Ouverture\nbodySource: |\n  Le **site** ouvre.\n");

        try {
            $create = $this->command('phil:content:create');
            self::assertSame(Command::SUCCESS, $create->execute(['resource-key' => 'news', 'locale' => 'fr', '--file' => $file, '--publish' => true]));
        } finally {
            unlink($file);
        }

        self::assertMatchesRegularExpression('/Created news:[0-9a-f-]{36}\./', $create->getDisplay());

        $list = $this->command('phil:content:list');
        self::assertSame(Command::SUCCESS, $list->execute(['resource-key' => ['static_page']]));
        self::assertStringNotContainsString('news:', $list->getDisplay(), 'Only the kinds named are listed.');
        self::assertSame(Command::SUCCESS, $list->execute([]));
        self::assertStringContainsString('news:', $list->getDisplay());
        self::assertStringContainsString('<b>site</b>', (string) $this->request('/fr/news/ouverture')->getContent());
    }

    /**
     * A field given as something else than text, by a caller building the input itself, is left out.
     */
    public function testAFieldThatIsNotTextIsLeftOut(): void
    {
        $create = $this->command('phil:content:create');

        self::assertSame(Command::SUCCESS, $create->execute(['resource-key' => 'static_page', 'locale' => 'fr', '--field' => ['slug=a-propos', 7]]));
        self::assertStringContainsString('Created static_page:1.', $create->getDisplay());
    }

    public function testARefusalIsReportedAsAnError(): void
    {
        $create = $this->command('phil:content:create');
        $create->execute(['resource-key' => 'static_page', 'locale' => 'fr', '--field' => ['slug=a-propos'], '--publish' => true]);

        self::assertSame(Command::FAILURE, $create->execute(['resource-key' => 'static_page', 'locale' => 'fr', '--field' => ['slug=a-propos']]));
        self::assertStringContainsString('The slug "a-propos" is already the address of "static_page:1"', $create->getDisplay());

        $publish = $this->command('phil:content:publish');
        self::assertSame(Command::FAILURE, $publish->execute(['reference' => 'static_page:9', 'locale' => 'fr']));
        self::assertStringContainsString('No content root "static_page:9" exists.', $publish->getDisplay());

        self::assertSame(Command::FAILURE, $publish->execute(['reference' => 'static_page:1', 'locale' => 'de']));
        self::assertStringContainsString('The locale "de" is not served.', $publish->getDisplay());

        self::assertSame(Command::FAILURE, $publish->execute(['reference' => 'nothing', 'locale' => 'fr']));
    }

    public function testARootListedWithoutDraftShowsAnEmptyLine(): void
    {
        $this->entityManager()->persist(new StaticPage());
        $this->entityManager()->flush();

        $list = $this->command('phil:content:list');
        $list->execute(['resource-key' => ['static_page']]);

        self::assertMatchesRegularExpression('/static_page:1\s*\n/', $list->getDisplay());
    }

    public function testWhatTheConversionLosesIsShownBeforePublishing(): void
    {
        $create = $this->command('phil:content:create');
        self::assertSame(Command::SUCCESS, $create->execute(
            ['resource-key' => 'static_page', 'locale' => 'fr', '--field' => ['slug=pertes', 'bodyFormat=html', 'bodySource=<p>a<object>x</object><a href="ftp://x">f</a></p>']],
        ), $create->getDisplay());

        $publish = $this->command('phil:content:publish');
        $publish->setInputs(['no']);
        self::assertSame(Command::SUCCESS, $publish->execute(['reference' => 'static_page:1', 'locale' => 'fr'], ['interactive' => true]));
        self::assertStringContainsString('does not keep', $publish->getDisplay());
        self::assertMatchesRegularExpression('/object\s+body > p\[1\] > object\[1\]\s+unknown-element/', $publish->getDisplay());
        self::assertMatchesRegularExpression('/a\s+body > p\[1\] > a\[1\]\s+refused-scheme/', $publish->getDisplay());
        self::assertStringContainsString('static_page:1 in fr is not published.', $publish->getDisplay());
        self::assertSame(404, $this->request('/fr/pertes')->getStatusCode());

        $publish->setInputs(['yes']);
        self::assertSame(Command::SUCCESS, $publish->execute(['reference' => 'static_page:1', 'locale' => 'fr'], ['interactive' => true]));
        self::assertStringContainsString('Published static_page:1 in fr.', $publish->getDisplay());

        self::assertSame(Command::SUCCESS, $this->command('phil:content:edit')->execute(['reference' => 'static_page:1', 'locale' => 'fr', '--field' => ['bodyFormat=html', 'bodySource=<p><object>y</object></p>']]));
        self::assertSame(Command::FAILURE, $publish->execute(['reference' => 'static_page:1', 'locale' => 'fr'], ['interactive' => false]));
        self::assertStringContainsString('unknown-element', $publish->getDisplay());
        self::assertStringContainsString('would lose 1 element(s)', $publish->getDisplay());
        self::assertStringNotContainsString('Published', $publish->getDisplay());

        self::assertSame(Command::SUCCESS, $publish->execute(['reference' => 'static_page:1', 'locale' => 'fr', '--accept-losses' => true], ['interactive' => false]));
        self::assertStringContainsString('unknown-element', $publish->getDisplay());
        self::assertStringContainsString('Published static_page:1 in fr.', $publish->getDisplay());
    }

    private function command(string $name): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        return new CommandTester(new Application($kernel)->find($name));
    }
}
