<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Application;

use Phil\AiBundle\Application\Exception\UnknownTask;
use Phil\AiBundle\Application\NoUserKeys;
use Phil\AiBundle\Application\PriceList;
use Phil\AiBundle\Application\TaskCatalog;
use Phil\AiBundle\Application\TaskDefinition;
use Phil\AiBundle\Application\TaskRequest;
use Phil\AiBundle\Application\ToolDefinition;
use Phil\AiBundle\Domain\Exception\InvalidAiValue;
use Phil\AiBundle\Domain\Journal\Output;
use Phil\AiBundle\Domain\ValueObject\Price;
use Phil\AiBundle\Domain\ValueObject\TaskKind;
use Phil\AiBundle\Domain\ValueObject\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaskDefinition::class)]
#[CoversClass(ToolDefinition::class)]
#[CoversClass(TaskCatalog::class)]
#[CoversClass(TaskRequest::class)]
#[CoversClass(PriceList::class)]
#[CoversClass(NoUserKeys::class)]
#[CoversClass(UnknownTask::class)]
final class DefinitionsTest extends TestCase
{
    public function testADefinitionNamesItsPlatformModelOptionsAndReserve(): void
    {
        $definition = new TaskDefinition(TaskKind::fromString('draft.rewrite'), 'main', 'model-a', ['max_tokens' => 512], 300);

        self::assertSame(['draft.rewrite', 'main', 'model-a', ['max_tokens' => 512], 300], [$definition->kind->value, $definition->platform, $definition->model, $definition->options, $definition->reserve]);

        $plain = new TaskDefinition(TaskKind::fromString('draft.rewrite'), 'a', str_repeat('m', 128));
        self::assertSame([[], 1, str_repeat('m', 128)], [$plain->options, $plain->reserve, $plain->model]);
        self::assertSame('a'.str_repeat('_-9', 21), new TaskDefinition(TaskKind::fromString('k'), 'a'.str_repeat('_-9', 21), 'm')->platform);

        foreach ([
            ['Main', 'm', 1, '"Main" is not a platform name: up to 64 lowercase letters, digits, "_" or "-", starting with a letter.'],
            ['main.eu', 'm', 1, '"main.eu" is not a platform name: up to 64 lowercase letters, digits, "_" or "-", starting with a letter.'],
            ["main\n", 'm', 1, "\"main\n\" is not a platform name: up to 64 lowercase letters, digits, \"_\" or \"-\", starting with a letter."],
            ['1main', 'm', 1, '"1main" is not a platform name: up to 64 lowercase letters, digits, "_" or "-", starting with a letter.'],
            ['a'.str_repeat('b', 64), 'm', 1, '"a'.str_repeat('b', 64).'" is not a platform name: up to 64 lowercase letters, digits, "_" or "-", starting with a letter.'],
            ['main', '', 1, 'A model is named by 1 to 128 bytes.'],
            ['main', str_repeat('m', 129), 1, 'A model is named by 1 to 128 bytes.'],
            ['main', 'm', 0, 'A task reserves one token or more; got 0.'],
        ] as [$platform, $model, $reserve, $message]) {
            try {
                new TaskDefinition(TaskKind::fromString('k'), $platform, $model, [], $reserve);
                self::fail($message);
            } catch (InvalidAiValue $invalidAiValue) {
                self::assertSame($message, $invalidAiValue->getMessage());
            }
        }
    }

    public function testADefinitionNamesWhatItAsksForItsToolsAndItsPurpose(): void
    {
        $kind = TaskKind::fromString('rules.answer');
        $lookUp = new ToolDefinition('look_up', 'Looks a rule up.', ['type' => 'object']);
        $roll = new ToolDefinition('Roll-2', str_repeat('d', 1024));
        $definition = new TaskDefinition($kind, 'main', 'model-a', [], 1, Output::Structured, Tokens::class, [$lookUp, $roll], 'Answers a rules question.');

        self::assertSame([Output::Structured, Tokens::class, [$lookUp, $roll], 'Answers a rules question.'], [$definition->output, $definition->structure, $definition->tools, $definition->purpose]);
        self::assertSame([Output::Text, null, [], ''], [new TaskDefinition($kind, 'main', 'm')->output, new TaskDefinition($kind, 'main', 'm')->structure, new TaskDefinition($kind, 'main', 'm')->tools, new TaskDefinition($kind, 'main', 'm')->purpose]);
        self::assertSame([$lookUp], new TaskDefinition($kind, 'main', 'm', [], 1, Output::Text, null, [$lookUp])->tools);
        self::assertSame(Output::Image, new TaskDefinition($kind, 'main', 'm', [], 1, Output::Image)->output);
        self::assertSame(['look_up', 'Looks a rule up.', ['type' => 'object'], null], [$lookUp->name, $lookUp->description, $lookUp->parameters, $roll->parameters]);
        self::assertSame(str_repeat('a', 64), new ToolDefinition(str_repeat('a', 64), 'x')->name);

        foreach ([
            [Output::Structured, null, [], 'The task "rules.answer" names the class of its structured output when, and only when, it asks for one.'],
            [Output::Text, Tokens::class, [], 'The task "rules.answer" names the class of its structured output when, and only when, it asks for one.'],
            [Output::Image, null, [$lookUp], 'The task "rules.answer" generates media and proposes no tool call.'],
            [Output::Speech, null, [$lookUp], 'The task "rules.answer" generates media and proposes no tool call.'],
            [Output::Text, null, [$lookUp, new ToolDefinition('look_up', 'Again.')], 'The task "rules.answer" names the tool "look_up" twice.'],
        ] as [$output, $structure, $tools, $message]) {
            try {
                new TaskDefinition($kind, 'main', 'm', [], 1, $output, $structure, $tools);
                self::fail($message);
            } catch (InvalidAiValue $invalidAiValue) {
                self::assertSame($message, $invalidAiValue->getMessage());
            }
        }

        foreach ([
            ['look up', 'x', '"look up" is not a tool name: 1 to 64 letters, digits, "_" or "-".'],
            ['', 'x', '"" is not a tool name: 1 to 64 letters, digits, "_" or "-".'],
            [str_repeat('a', 65), 'x', '"'.str_repeat('a', 65).'" is not a tool name: 1 to 64 letters, digits, "_" or "-".'],
            ["roll\n", 'x', "\"roll\n\" is not a tool name: 1 to 64 letters, digits, \"_\" or \"-\"."],
            ['roll', ' ', 'The tool "roll" is described in 1 to 1024 bytes.'],
            ['roll', str_repeat('d', 1025), 'The tool "roll" is described in 1 to 1024 bytes.'],
        ] as [$name, $description, $message]) {
            try {
                new ToolDefinition($name, $description);
                self::fail($message);
            } catch (InvalidAiValue $invalidAiValue) {
                self::assertSame($message, $invalidAiValue->getMessage());
            }
        }
    }

    public function testTheCatalogFindsADefinitionByKind(): void
    {
        $rewrite = new TaskDefinition(TaskKind::fromString('draft.rewrite'), 'main', 'model-a');
        $title = new TaskDefinition(TaskKind::fromString('title.suggest'), 'main', 'model-b');
        $catalog = new TaskCatalog($rewrite, $title);

        self::assertSame($title, $catalog->get(TaskKind::fromString('title.suggest')));
        self::assertSame($rewrite, $catalog->get(TaskKind::fromString('draft.rewrite')));
        self::assertSame(['draft.rewrite', 'title.suggest'], $catalog->kinds());
        self::assertSame([$rewrite, $title], $catalog->all());
        self::assertSame([], new TaskCatalog()->kinds());
        self::assertSame([], new TaskCatalog()->all());

        try {
            $catalog->get(TaskKind::fromString('draft.expand'));
            self::fail('An unknown task was found.');
        } catch (UnknownTask $unknownTask) {
            self::assertSame('No task "draft.expand" is defined: add it under phil_ai.tasks.', $unknownTask->getMessage());
        }

        $this->expectExceptionObject(InvalidAiValue::because('The task "draft.rewrite" is defined twice.'));
        new TaskCatalog($rewrite, $title, new TaskDefinition(TaskKind::fromString('draft.rewrite'), 'other', 'model-c'));
    }

    public function testARequestNamesItsKindSubjectLocaleParametersAndOwner(): void
    {
        $request = new TaskRequest('draft.rewrite', 'project-1', 'fr', ['text' => 'Il était une fois'], 'member-1');

        self::assertSame(['draft.rewrite', 'project-1', 'fr', ['text' => 'Il était une fois'], 'member-1'], [$request->kind->value, $request->subject->value, $request->locale, $request->parameters, $request->owner]);
        self::assertSame([[], null], [new TaskRequest('k', 's', 'en')->parameters, new TaskRequest('k', 's', 'en')->owner]);

        $this->expectException(InvalidAiValue::class);
        new TaskRequest('k', '', 'en');
    }

    public function testThePriceListCostsEachModelInItsCurrency(): void
    {
        $prices = new PriceList('USD', ['model-a' => new Price(3_000_000, 15_000_000, 'USD')]);

        self::assertSame('USD', $prices->currency());
        self::assertSame([111, 'USD'], [$prices->costOf('model-a', new Tokens(12, 5))->micros, $prices->costOf('model-a', new Tokens(12, 5))->currency]);
        self::assertSame([0, 'USD'], [$prices->costOf('model-b', new Tokens(12, 5))->micros, $prices->costOf('model-b', new Tokens(12, 5))->currency]);
        self::assertSame('EUR', new PriceList('EUR')->costOf('model-a', new Tokens(1, 1))->currency);

        try {
            new PriceList('usd');
            self::fail('A malformed currency was let through.');
        } catch (InvalidAiValue $invalidAiValue) {
            self::assertSame('"usd" is not a currency code: three uppercase letters.', $invalidAiValue->getMessage());
        }

        $this->expectExceptionObject(InvalidAiValue::because('The model "model-b" is priced in EUR, the list in USD.'));
        new PriceList('USD', ['model-a' => new Price(1, 1, 'USD'), 'model-b' => new Price(1, 1, 'EUR')]);
    }

    public function testNoOneLendsAKey(): void
    {
        self::assertNull(new NoUserKeys()->keyFor('member-1', 'main', 'AI task "draft.rewrite"'));
    }
}
