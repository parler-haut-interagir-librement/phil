<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Functional;

use Override;
use Phil\FaqBundle\Application\FaqEditor;
use Phil\FaqBundle\Domain\Contract\FaqRepository;
use Phil\FaqBundle\Domain\Model\Question;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\FaqBundle\Infrastructure\Console\ExportCommand;
use Phil\FaqBundle\Infrastructure\Console\ImportCommand;
use Phil\FaqBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * `phil:faq:export` and `phil:faq:import`, through the console of the application.
 */
#[CoversClass(ExportCommand::class)]
#[CoversClass(ImportCommand::class)]
final class ConsoleTest extends KernelTestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->assertInstanceOf(TestKernel::class, $kernel);
        $kernel->resetDatabase();
        $this->directory = sys_get_temp_dir().'/phil-faq-console-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*');
        array_map(unlink(...), false === $files ? [] : $files);
        rmdir($this->directory);
        parent::tearDown();
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testTheQuestionsGoToAFileAndComeBack(): void
    {
        $editor = self::getContainer()->get(FaqEditor::class);
        $this->assertInstanceOf(FaqEditor::class, $editor);
        $section = $editor->addSection(Locale::fromString('en'), 'Orders', 'orders');
        $editor->addQuestion($section->id, Locale::fromString('en'), 'How do I pay?', "By **card**.\n\nOr transfer.");

        $export = $this->command('phil:faq:export');
        self::assertSame(Command::SUCCESS, $export->execute([]));
        self::assertStringContainsString("key: orders\n", $export->getDisplay());
        self::assertStringContainsString("anchor: how-do-i-pay\n", $export->getDisplay());
        self::assertStringContainsString("answer: |-\n", $export->getDisplay());

        $file = $this->directory.'/faq.yaml';
        self::assertSame(Command::SUCCESS, $export->execute(['file' => $file]));
        self::assertStringContainsString('The questions are written to', $export->getDisplay());
        file_put_contents($file, str_replace('How do I pay?', 'How can I pay?', (string) file_get_contents($file)));

        $import = $this->command('phil:faq:import');
        self::assertSame(Command::SUCCESS, $import->execute(['file' => $file]));
        self::assertStringContainsString('1 sections and 1 questions are read.', $import->getDisplay());

        $repository = self::getContainer()->get(FaqRepository::class);
        $this->assertInstanceOf(FaqRepository::class, $repository);
        $question = $repository->questionByAnchor('how-do-i-pay');
        self::assertSame('How can I pay?', $question instanceof Question ? $repository->questionTranslation($question, 'en')?->text() : null);
    }

    public function testAFileThatCannotBeWrittenOrReadFails(): void
    {
        $export = $this->command('phil:faq:export');
        self::assertSame(Command::FAILURE, $export->execute(['file' => $this->directory.'/missing/faq.yaml']));
        self::assertStringContainsString('cannot be written', preg_replace('/\s+/', ' ', $export->getDisplay()) ?? '');

        $import = $this->command('phil:faq:import');
        self::assertSame(Command::FAILURE, $import->execute(['file' => $this->directory.'/missing.yaml']));
        self::assertStringContainsString('of 5242880 bytes at most', preg_replace('/\s+/', ' ', $import->getDisplay()) ?? '');

        file_put_contents($this->directory.'/broken.yaml', "sections: [\n");
        self::assertSame(Command::FAILURE, $import->execute(['file' => $this->directory.'/broken.yaml']));
        self::assertStringContainsString('The file is not valid YAML', $import->getDisplay());

        file_put_contents($this->directory.'/shape.yaml', "sections: { a: 1 }\n");
        self::assertSame(Command::FAILURE, $import->execute(['file' => $this->directory.'/shape.yaml']));
        self::assertStringContainsString('sections is a list.', $import->getDisplay());

        file_put_contents($this->directory.'/large.yaml', str_repeat('#', ImportCommand::MAX_BYTES + 1));
        self::assertSame(Command::FAILURE, $import->execute(['file' => $this->directory.'/large.yaml']));
    }

    private function command(string $name): CommandTester
    {
        $kernel = self::$kernel;
        $this->assertInstanceOf(KernelInterface::class, $kernel);

        return new CommandTester(new Application($kernel)->find($name));
    }
}
