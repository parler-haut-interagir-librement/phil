<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure;

use Phil\ApiContract\Exception\InvalidContractValue;
use Phil\ApiContract\Exception\UnreadableDocument;
use Phil\ApiContract\OpenApi\ContractVerifier;
use Phil\ApiContract\OpenApi\OpenApiDocument;
use Phil\ApiContract\OpenApi\RuleLevel;
use Phil\ApiContract\OpenApi\Severity;
use Phil\ApiContract\Tests\Unit\OpenApi\FixedRule;
use Phil\ApiContractBundle\Infrastructure\Console\VerifyCommand;
use Phil\ApiContractBundle\Infrastructure\OpenApi\ContractVerifierFactory;
use Phil\ApiContractBundle\Infrastructure\OpenApi\DocumentReader;
use Phil\ApiContractBundle\Tests\Unit\Infrastructure\Fixture\HouseRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(VerifyCommand::class)]
#[CoversClass(DocumentReader::class)]
#[CoversClass(ContractVerifierFactory::class)]
final class VerificationTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/Fixture/openapi/';

    public function testAJsonOrYamlDocumentIsRead(): void
    {
        self::assertSame('Shop', $this->info(DocumentReader::read(self::FIXTURES.'clean.json')));
        self::assertSame('Shop', $this->info(DocumentReader::read(self::FIXTURES.'warnings.yaml')));

        $copy = $this->temporary('upper.YML', "openapi: 3.1.0\ninfo: {title: Upper}\n");
        self::assertSame('Upper', $this->info(DocumentReader::read($copy)));
        $json = $this->temporary('yaml.json.txt', '{"info": {"title": "Named"}}');
        self::assertSame('Named', $this->info(DocumentReader::read($json)), 'A name that does not end in .yaml or .yml is JSON.');
    }

    public function testADocumentThatCannotBeReadIsRefused(): void
    {
        $directory = $this->temporary('directory', null);

        foreach ([
            self::FIXTURES.'missing.json' => 'The file "'.self::FIXTURES.'missing.json" cannot be read.',
            $directory => 'The file "'.$directory.'" cannot be read.',
            self::FIXTURES.'invalid.yaml' => 'The document is not YAML: ',
            self::FIXTURES.'list.yml' => 'The document is a list, not an object.',
            self::FIXTURES.'scalar.yaml' => 'The document is not a YAML mapping.',
            self::FIXTURES.'warnings.yaml.json' => 'The file',
        ] as $file => $message) {
            try {
                DocumentReader::read($file);
                self::fail($message);
            } catch (UnreadableDocument $unreadableDocument) {
                self::assertStringStartsWith($message, $unreadableDocument->getMessage());
            }
        }
    }

    public function testADocumentIsBoundedBeforeItIsRead(): void
    {
        $exact = $this->temporary('exact.json', '{"a":"'.str_repeat('x', OpenApiDocument::MAX_BYTES - 8).'"}');
        self::assertArrayHasKey('a', DocumentReader::read($exact)->data);

        $large = $this->temporary('large.yaml', str_repeat('#', OpenApiDocument::MAX_BYTES + 1));
        $this->expectExceptionObject(UnreadableDocument::because('The document is larger than 8388608 bytes.'));
        DocumentReader::read($large);
    }

    public function testTheFactoryAddsTheApplicationsRulesAndLeavesOutTheSkippedOnes(): void
    {
        $house = new HouseRule();
        $verifier = ContractVerifierFactory::create((static function () use ($house): iterable {
            yield 'service.id' => $house;
        })(), ['operation-description', 'house-rule']);

        self::assertCount(16, $verifier->rules);
        self::assertCount(\count(ContractVerifier::builtInRules()) + 1, ContractVerifierFactory::create([$house], [])->rules);
        self::assertSame('house-rule', ContractVerifierFactory::create([$house], [])->rules[17]->id());

        $this->expectException(InvalidContractValue::class);
        ContractVerifierFactory::create([], ['house-rule']);
    }

    public function testTheNameDecidesTheFormatOfTheDocument(): void
    {
        $yaml = $this->temporary('DOC.YAML', "openapi: 3.1.0\ninfo: {title: Caps}\n");
        self::assertSame('Caps', $this->info(DocumentReader::read($yaml)));

        $notYaml = $this->temporary('a.yaml.json', '{"info": {"title": "Json"}, "info": {"title": "Last"}}');
        self::assertSame('Last', $this->info(DocumentReader::read($notYaml)), 'YAML refuses a key twice, JSON keeps the last.');

        $newline = $this->temporary("a.yaml\n", '{"info": {"title": "Newline"}, "info": {"title": "Last"}}');
        self::assertSame('Last', $this->info(DocumentReader::read($newline)), 'A name that ends with a line break does not end in .yaml.');
    }

    public function testTheWordsOfTheYamlParserFollowTheMessage(): void
    {
        try {
            DocumentReader::read(self::FIXTURES.'invalid.yaml');
            self::fail('The document is not YAML.');
        } catch (UnreadableDocument $unreadableDocument) {
            self::assertGreaterThan(\strlen('The document is not YAML: '), \strlen($unreadableDocument->getMessage()));
        }
    }

    public function testTheFactoryTakesRulesUnderMixedKeys(): void
    {
        $verifier = ContractVerifierFactory::create(['service' => new HouseRule(), 0 => new FixedRule('other', RuleLevel::Minimal, Severity::Error, 0)], []);

        self::assertCount(\count(ContractVerifier::builtInRules()) + 2, $verifier->rules);
    }

    public function testTheJsonReportKeepsTheTextOfTheDocumentReadable(): void
    {
        $tester = $this->tester();
        $tester->execute(['document' => $this->temporary('v1.json', '{"openapi": "3.1.0", "info": {"title": "T", "version": "1"}, "paths": {"/v1/café": {"get": {"responses": {"200": {"description": "ok"}}}}}}'), '--format' => 'json']);

        self::assertStringContainsString('/v1/café', $tester->getDisplay());
        self::assertStringNotContainsString('\\u00e9', $tester->getDisplay());
    }

    public function testACleanDocumentPasses(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['document' => self::FIXTURES.'clean.json']));
        self::assertSame("0 error(s), 0 warning(s) at the recommended level.\n", $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['document' => self::FIXTURES.'clean.json', '--level' => 'strict', '--fail-on' => 'warning']));
        self::assertSame("0 error(s), 0 warning(s) at the strict level.\n", $tester->getDisplay());
    }

    public function testWarningsFailOnlyAtTheWarningThreshold(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['document' => self::FIXTURES.'warnings.yaml']));
        self::assertSame(
            "warning json-content at /paths/~1orders/get/responses/200/content/text~1csv: The media type \"text/csv\" is not JSON.\n0 error(s), 1 warning(s) at the recommended level.\n",
            $tester->getDisplay(),
        );
        self::assertSame(Command::FAILURE, $tester->execute(['document' => self::FIXTURES.'warnings.yaml', '--fail-on' => 'warning']));
        self::assertSame(Command::SUCCESS, $tester->execute(['document' => self::FIXTURES.'warnings.yaml', '--level' => 'minimal', '--fail-on' => 'warning']));
        self::assertSame("0 error(s), 0 warning(s) at the minimal level.\n", $tester->getDisplay());
    }

    public function testErrorsFailAndTheDocumentsTextIsEscaped(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['document' => self::FIXTURES.'broken.json'], ['decorated' => true]));
        self::assertSame(
            "\e[37;41merror\e[39;49m   no-version-in-path at /paths/~1v1~1<info>orders: The path \"/v1/<info>orders\" carries a version segment.\n1 error(s), 0 warning(s) at the recommended level.\n",
            $tester->getDisplay(),
        );

        $tester->execute(['document' => self::FIXTURES.'warnings.yaml'], ['decorated' => true]);
        self::assertStringStartsWith("\e[33mwarning\e[39m json-content at ", $tester->getDisplay());
    }

    public function testTheReportIsWrittenAsJson(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['document' => self::FIXTURES.'broken.json', '--format' => 'json'], ['decorated' => true]));
        self::assertSame(
            ['level' => 'recommended', 'findings' => [['rule' => 'no-version-in-path', 'severity' => 'error', 'location' => '/paths/~1v1~1<info>orders', 'message' => 'The path "/v1/<info>orders" carries a version segment.']]],
            json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR),
        );
        self::assertStringContainsString("\n    \"level\": \"recommended\",\n", $tester->getDisplay());
        self::assertStringContainsString('"/paths/~1v1~1<info>orders"', $tester->getDisplay(), 'Slashes are not escaped.');

        $tester->execute(['document' => $this->temporary('é.json', '{"openapi": "3.1.0", "info": {"title": "é", "version": "1"}, "paths": {"/café": {}}}'), '--format' => 'json', '--level' => 'strict']);
        self::assertStringContainsString('{"level":"strict"', (string) json_encode(json_decode($tester->getDisplay(), true)));
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringNotContainsString('é', $tester->getDisplay());
    }

    public function testTheConfiguredDocumentLevelAndThresholdAreTheDefaults(): void
    {
        $tester = new CommandTester(new VerifyCommand(ContractVerifier::withBuiltInRules(), self::FIXTURES.'warnings.yaml', 'minimal', 'warning'));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("0 error(s), 0 warning(s) at the minimal level.\n", $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->execute(['--level' => 'recommended']));

        $named = new CommandTester(new VerifyCommand(ContractVerifier::withBuiltInRules(), self::FIXTURES.'missing.json'));
        self::assertSame(Command::SUCCESS, $named->execute(['document' => self::FIXTURES.'clean.json']));
    }

    public function testWhatCannotBeVerifiedIsInvalid(): void
    {
        $tester = $this->tester();

        foreach ([
            [[], 'Name the document to verify, or set verification.document.'],
            [['document' => self::FIXTURES.'clean.json', '--level' => 'lenient'], 'The level is minimal, recommended or strict.'],
            [['document' => self::FIXTURES.'clean.json', '--level' => ['strict']], 'The level is minimal, recommended or strict.'],
            [['document' => self::FIXTURES.'clean.json', '--fail-on' => 'notice'], 'The threshold is error or warning.'],
            [['document' => self::FIXTURES.'clean.json', '--fail-on' => ['error']], 'The threshold is error or warning.'],
            [['document' => [self::FIXTURES.'clean.json']], 'Name the document to verify, or set verification.document.'],
            [['document' => self::FIXTURES.'clean.json', '--format' => 'xml'], 'The format is text or json.'],
            [['document' => self::FIXTURES.'list.yml'], 'The document is a list, not an object.'],
            [['document' => self::FIXTURES.'<b>.json'], 'The file "'.self::FIXTURES.'<b>.json" cannot be read.'],
        ] as [$input, $message]) {
            self::assertSame(Command::INVALID, $tester->execute($input, ['decorated' => true]), $message);
            self::assertSame("\e[37;41m".$message."\e[39;49m\n", $tester->getDisplay());
        }
    }

    public function testTheCommandIsNamedAndDescribed(): void
    {
        $command = $this->command();

        self::assertSame('phil:api-contract:verify', $command->getName());
        self::assertSame('Verifies an OpenAPI document against a level of rules', $command->getDescription());
        self::assertSame(['recommended', 'error', 'text'], [$command->getDefinition()->getOption('level')->getDefault(), $command->getDefinition()->getOption('fail-on')->getDefault(), $command->getDefinition()->getOption('format')->getDefault()]);
        self::assertFalse($command->getDefinition()->getArgument('document')->isRequired());
        self::assertTrue($command->getDefinition()->getOption('level')->isValueRequired());
        self::assertTrue($command->getDefinition()->getOption('fail-on')->isValueRequired());
        self::assertTrue($command->getDefinition()->getOption('format')->isValueRequired());
        self::assertSame(['strict', 'warning'], [new VerifyCommand(ContractVerifier::withBuiltInRules(), null, 'strict', 'warning')->getDefinition()->getOption('level')->getDefault(), new VerifyCommand(ContractVerifier::withBuiltInRules(), null, 'strict', 'warning')->getDefinition()->getOption('fail-on')->getDefault()]);
    }

    private function command(): VerifyCommand
    {
        return new VerifyCommand(ContractVerifier::withBuiltInRules());
    }

    private function tester(): CommandTester
    {
        return new CommandTester($this->command());
    }

    private function info(OpenApiDocument $document): mixed
    {
        $info = $document->data['info'] ?? null;

        return \is_array($info) ? $info['title'] : null;
    }

    private function temporary(string $name, ?string $content): string
    {
        $directory = sys_get_temp_dir().'/api-contract-'.bin2hex(random_bytes(6));
        mkdir($directory);
        $path = $directory.'/'.$name;
        null === $content ? mkdir($path) : file_put_contents($path, $content);
        register_shutdown_function(static function () use ($path, $directory): void {
            is_dir($path) ? rmdir($path) : unlink($path);
            rmdir($directory);
        });

        return $path;
    }
}
