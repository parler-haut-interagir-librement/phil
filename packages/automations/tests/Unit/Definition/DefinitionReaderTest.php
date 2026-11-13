<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Definition;

use Phil\Automations\Definition\DefinitionReader;
use Phil\Automations\Definition\DefinitionWriter;
use Phil\Automations\Definition\Step;
use Phil\Automations\Definition\StepKind;
use Phil\Automations\Exception\InvalidDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefinitionReader::class)]
#[CoversClass(DefinitionWriter::class)]
final class DefinitionReaderTest extends TestCase
{
    private const string COMPLETE = <<<'JSON'
        {"id":"welcome","version":2,"name":"Welcome","trigger":{"type":"newsletter.confirmed","options":{"list":"news"}},"steps":[{"id":"hello","type":"action","action":"newsletter.send","options":{"template":"hello"},"retries":3},{"id":"wait","type":"delay","seconds":86400},{"id":"active","type":"filter","condition":{"language":"expression","source":"subject != null"}},{"id":"pro","type":"branch","condition":{"language":"expression","source":"payload['plan'] == 'pro'"},"then":[{"id":"tip","type":"action","action":"newsletter.send"}],"else":[{"id":"later","type":"delay","seconds":60}]},{"id":"empty","type":"branch","condition":{"language":"expression","source":"true"},"then":[]}]}
        JSON;

    public function testItReadsEveryKindOfStepAndWritesThemBackTheSame(): void
    {
        $definition = new DefinitionReader()->read(self::COMPLETE);

        self::assertSame('welcome', $definition->id->value);
        self::assertSame(2, $definition->version);
        self::assertSame('newsletter.confirmed', $definition->trigger->type);
        self::assertSame(['list' => 'news'], $definition->trigger->options->values);
        self::assertSame([StepKind::Action, StepKind::Delay, StepKind::Filter, StepKind::Branch, StepKind::Branch], array_map(static fn (Step $step): StepKind => $step->kind, $definition->steps));
        self::assertSame(3, $definition->steps[0]->retries);
        self::assertSame(86400, $definition->steps[1]->seconds);
        self::assertSame('tip', $definition->steps[3]->then[0]->id);
        self::assertSame('later', $definition->steps[3]->else[0]->id);
        self::assertSame(self::COMPLETE, new DefinitionWriter()->write($definition));
    }

    public function testOptionalKeysTakeTheirDefault(): void
    {
        $definition = new DefinitionReader()->read('{"id":"a","version":1,"name":"A","trigger":{"type":"t","options":[]},"steps":[{"id":"s","type":"action","action":"x","options":{}},{"id":"f","type":"filter","condition":{"source":"true"}}]}');

        self::assertSame([], $definition->trigger->options->values);
        self::assertSame(0, $definition->steps[0]->retries);
        self::assertSame([], $definition->steps[0]->options->values);
        self::assertSame('expression', $definition->steps[1]->condition?->language);
        self::assertSame('{"id":"a","version":1,"name":"A","trigger":{"type":"t"},"steps":[{"id":"s","type":"action","action":"x"},{"id":"f","type":"filter","condition":{"language":"expression","source":"true"}}]}', new DefinitionWriter()->write($definition));
        self::assertSame(['id' => 'a', 'version' => 1, 'name' => 'A', 'trigger' => ['type' => 't']], \array_slice(new DefinitionWriter()->toArray($definition), 0, 4));
    }

    #[DataProvider('refused')]
    public function testItRefusesWhatIsNotADefinition(string $json, string $message): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains($message);

        new DefinitionReader()->read($json);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        $base = '{"id":"a","version":1,"name":"A","trigger":{"type":"t"},"steps":%s}';

        yield 'not JSON' => ['{', 'A definition is a JSON object: Syntax error'];
        yield 'a string' => ['"x"', 'A definition is a JSON object.'];
        yield 'too large' => [str_repeat(' ', 262_145), 'A definition is at most 262144 bytes of JSON.'];
        yield 'too deep JSON' => [str_repeat('[', 65).str_repeat(']', 65), 'A definition is a JSON object: Maximum stack depth exceeded'];
        yield 'an unknown key' => ['{"id":"a","version":1,"name":"A","trigger":{"type":"t"},"steps":[],"x":1}', 'The definition has no key "x".'];
        yield 'a missing key' => ['{"id":"a","version":1,"name":"A","trigger":{"type":"t"}}', 'The definition needs the key "steps".'];
        yield 'a list as trigger' => ['{"id":"a","version":1,"name":"A","trigger":["t"],"steps":[]}', 'The trigger is a JSON object.'];
        yield 'a trigger without type' => ['{"id":"a","version":1,"name":"A","trigger":{"options":{}},"steps":[]}', 'The trigger needs the key "type".'];
        yield 'a numeric id' => ['{"id":1,"version":1,"name":"A","trigger":{"type":"t"},"steps":[]}', 'Id is a string.'];
        yield 'a string version' => ['{"id":"a","version":"1","name":"A","trigger":{"type":"t"},"steps":[]}', 'Version is an integer.'];
        yield 'list options' => ['{"id":"a","version":1,"name":"A","trigger":{"type":"t","options":[1]},"steps":[]}', 'The options of the trigger is a JSON object.'];
        yield 'steps as an object' => [\sprintf($base, '{"a":1}'), 'Steps are a JSON array.'];
        yield 'a step as a string' => [\sprintf($base, '["x"]'), 'A step is a JSON object.'];
        yield 'a step without id' => [\sprintf($base, '[{"type":"delay","seconds":1}]'), 'The id of a step is a string.'];
        yield 'an unknown kind' => [\sprintf($base, '[{"id":"s","type":"loop"}]'), 'The type of the step "s" is "action", "delay", "filter" or "branch".'];
        yield 'an action without action' => [\sprintf($base, '[{"id":"s","type":"action"}]'), 'The step "s" needs the key "action".'];
        yield 'an action with seconds' => [\sprintf($base, '[{"id":"s","type":"action","action":"x","seconds":1}]'), 'The step "s" has no key "seconds".'];
        yield 'string retries' => [\sprintf($base, '[{"id":"s","type":"action","action":"x","retries":"1"}]'), 'The retries of the step "s" is an integer.'];
        yield 'a delay without seconds' => [\sprintf($base, '[{"id":"s","type":"delay"}]'), 'The step "s" needs the key "seconds".'];
        yield 'a delay with an action' => [\sprintf($base, '[{"id":"s","type":"delay","seconds":1,"action":"x"}]'), 'The step "s" has no key "action".'];
        yield 'a filter without condition' => [\sprintf($base, '[{"id":"s","type":"filter"}]'), 'The step "s" needs the key "condition".'];
        yield 'a filter with then' => [\sprintf($base, '[{"id":"s","type":"filter","condition":{"source":"1"},"then":[]}]'), 'The step "s" has no key "then".'];
        yield 'a condition as a string' => [\sprintf($base, '[{"id":"s","type":"filter","condition":"true"}]'), 'The condition of the step "s" is a JSON object.'];
        yield 'a condition without source' => [\sprintf($base, '[{"id":"s","type":"filter","condition":{"language":"expression"}}]'), 'The condition of the step "s" needs the key "source".'];
        yield 'a condition with more' => [\sprintf($base, '[{"id":"s","type":"filter","condition":{"source":"1","x":1}}]'), 'The condition of the step "s" has no key "x".'];
        yield 'a branch without then' => [\sprintf($base, '[{"id":"s","type":"branch","condition":{"source":"1"}}]'), 'The step "s" needs the key "then".'];
        yield 'a branch with seconds' => [\sprintf($base, '[{"id":"s","type":"branch","condition":{"source":"1"},"then":[],"seconds":1}]'), 'The step "s" has no key "seconds".'];
        yield 'too many steps' => [\sprintf($base, '['.implode(',', array_fill(0, 201, '1')).']'), 'A definition holds at most 200 steps, nested at most 15 levels deep.'];
        yield 'too deep branches' => [\sprintf($base, str_repeat('[{"id":"s","type":"branch","condition":{"source":"1"},"then":', 16).'[]'.str_repeat('}]', 16)), 'nested at most 15 levels deep'];
    }
}
