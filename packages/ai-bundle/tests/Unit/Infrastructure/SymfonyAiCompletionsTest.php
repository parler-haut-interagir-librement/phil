<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Infrastructure;

use Generator;
use Override;
use Phil\AiBundle\Application\Exception\UnknownPlatform;
use Phil\AiBundle\Application\Media;
use Phil\AiBundle\Application\ModelCall;
use Phil\AiBundle\Application\Prompt;
use Phil\AiBundle\Application\ProposedToolCall;
use Phil\AiBundle\Application\ToolDefinition;
use Phil\AiBundle\Domain\Journal\Output;
use Phil\AiBundle\Domain\ValueObject\Tokens;
use Phil\AiBundle\Infrastructure\SymfonyAi\SymfonyAiCompletions;
use Phil\AiBundle\Infrastructure\SymfonyAi\UserKeyPlatformFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use UnexpectedValueException;

#[CoversClass(SymfonyAiCompletions::class)]
#[CoversClass(UnknownPlatform::class)]
final class SymfonyAiCompletionsTest extends TestCase
{
    /** @var list<array{string, string, array<mixed>, mixed}> */
    private array $calls = [];

    public function testThePromptGoesToThePlatformAndTheTokensComeBack(): void
    {
        $completions = new SymfonyAiCompletions(['main' => $this->platform('application', new TokenUsage(promptTokens: 12, completionTokens: 5))]);

        $completion = $completions->complete(new ModelCall('main', 'model-a', new Prompt('You edit prose.', 'Rewrite: once'), ['max_tokens' => 64]));

        self::assertSame('application answers', $completion->text);
        self::assertSame([12, 5], [$completion->tokens->input, $completion->tokens->output]);
        self::assertSame([['application', 'model-a', ['max_tokens' => 64], [['role' => 'system', 'content' => 'You edit prose.'], ['role' => 'user', 'content' => 'Rewrite: once']]]], $this->calls);
    }

    public function testAPromptWithoutSystemAndAPlatformThatCountsNothing(): void
    {
        $completions = new SymfonyAiCompletions(['main' => $this->platform('application', new TokenUsage())]);

        $completion = $completions->complete(new ModelCall('main', 'model-a', new Prompt(null, 'Name a dragon')));

        self::assertSame(0, $completion->tokens->total());
        self::assertSame([['application', 'model-a', [], [['role' => 'user', 'content' => 'Name a dragon']]]], $this->calls);
        self::assertSame(0, new SymfonyAiCompletions(['main' => $this->platform('application', null)])->complete(new ModelCall('main', 'model-a', new Prompt(null, 'x')))->tokens->total());
    }

    public function testThePersonsKeyBuildsTheirOwnPlatform(): void
    {
        $factory = new class($this) implements UserKeyPlatformFactory {
            /** @var list<string> */
            public array $keys = [];

            public function __construct(
                private readonly SymfonyAiCompletionsTest $test,
            ) {
            }

            #[Override]
            public function create(#[SensitiveParameter] string $apiKey): PlatformInterface
            {
                $this->keys[] = $apiKey;

                return $this->test->platform('member', new TokenUsage(promptTokens: 1, completionTokens: 2));
            }
        };
        $completions = new SymfonyAiCompletions(['main' => $this->platform('application', null)], ['main' => $factory]);

        self::assertSame('member answers', $completions->complete(new ModelCall('main', 'model-a', new Prompt(null, 'x')), 'sk-member')->text);
        self::assertSame(['sk-member'], $factory->keys);

        try {
            new SymfonyAiCompletions(['main' => $this->platform('application', null)])->complete(new ModelCall('main', 'model-a', new Prompt(null, 'x')), 'sk-member');
            self::fail('A key of the person reached a platform that takes none.');
        } catch (UnknownPlatform $unknownPlatform) {
            self::assertSame('The platform "main" takes no key of the person: give it a user_key_factory.', $unknownPlatform->getMessage());
        }

        $this->expectExceptionObject(UnknownPlatform::of('other'));
        $this->expectExceptionMessageIsOrContains('No platform "other" is defined: add it under phil_ai.platforms.');
        $completions->complete(new ModelCall('other', 'model-a', new Prompt(null, 'x')));
    }

    public function testAStructuredTaskAsksForItsClassAndReadsTheObject(): void
    {
        $completions = new SymfonyAiCompletions(['main' => $this->answering(new ObjectResult(['name' => 'Ysolde']), new TokenUsage(promptTokens: 3, completionTokens: 4))]);

        $completion = $completions->complete(new ModelCall('main', 'model-a', new Prompt(null, 'Name a knight'), ['max_tokens' => 9], Output::Structured, Tokens::class));

        self::assertSame(['', ['name' => 'Ysolde'], [], null, 7], [$completion->text, $completion->data, $completion->toolCalls, $completion->media, $completion->tokens->total()]);
        self::assertSame(['max_tokens' => 9, 'response_format' => Tokens::class], $this->calls[0][2]);
    }

    public function testToolsGoAsSymfonyAiToolsAndTheCallsComeBackUnrun(): void
    {
        $completions = new SymfonyAiCompletions(['main' => $this->answering(new ToolCallResult([new ToolCall('call-1', 'look_up', ['rule' => 'grapple']), new ToolCall('call-2', 'roll')]), null)]);
        $tools = [new ToolDefinition('look_up', 'Looks a rule up.', ['type' => 'object', 'properties' => ['rule' => ['type' => 'string']]]), new ToolDefinition('roll', 'Rolls dice.')];

        $completion = $completions->complete(new ModelCall('main', 'model-a', new Prompt('You run games.', 'Grapple the troll'), [], Output::Text, null, $tools));

        self::assertEquals([new ProposedToolCall('call-1', 'look_up', ['rule' => 'grapple']), new ProposedToolCall('call-2', 'roll')], $completion->toolCalls);
        self::assertSame(['', null, 0], [$completion->text, $completion->data, $completion->tokens->total()]);
        self::assertSame([
            ['type' => 'function', 'function' => ['name' => 'look_up', 'description' => 'Looks a rule up.', 'parameters' => ['type' => 'object', 'properties' => ['rule' => ['type' => 'string']]]]],
            ['type' => 'function', 'function' => ['name' => 'roll', 'description' => 'Rolls dice.']],
        ], $this->calls[0][2]['tools'] ?? null);
    }

    public function testAnythingButAToolCallIsLeftOutOfTheProposedCalls(): void
    {
        // @phpstan-ignore argument.type (an answer that breaks the contract of its type)
        $completions = new SymfonyAiCompletions(['main' => $this->answering(new ToolCallResult(['not a call', new ToolCall('call-1', 'roll')]), null)]);

        $completion = $completions->complete(new ModelCall('main', 'model-a', new Prompt(null, 'Roll'), [], Output::Text, null, [new ToolDefinition('roll', 'Rolls dice.')]));

        self::assertEquals([0 => new ProposedToolCall('call-1', 'roll')], $completion->toolCalls);
    }

    public function testAnImageOrSpeechTaskSendsThePromptAloneAndReadsTheBytes(): void
    {
        $completions = new SymfonyAiCompletions(['main' => $this->answering(new BinaryResult('PNG', 'image/png'), null), 'voice' => $this->answering(new BinaryResult('MP3'), null)]);

        $image = $completions->complete(new ModelCall('main', 'model-a', new Prompt('ignored', 'A red dragon'), [], Output::Image));
        $speech = $completions->complete(new ModelCall('voice', 'model-b', new Prompt(null, 'Hello'), [], Output::Speech));

        self::assertEquals([new Media('PNG', 'image/png'), new Media('MP3', 'application/octet-stream')], [$image->media, $speech->media]);
        self::assertSame(['A red dragon', 'Hello'], [$this->calls[0][3], $this->calls[1][3]]);
        self::assertSame('', $image->text);
    }

    public function testAStreamYieldsTheTextThenTheCompletion(): void
    {
        $completions = new SymfonyAiCompletions(['main' => $this->answering(new StreamResult((static function (): Generator {
            yield new TextDelta('Once ');
            yield new ThinkingDelta('hmm');
            yield new TextDelta('upon');
            yield new ToolCallComplete([new ToolCall('call-1', 'roll', ['dice' => '2d6'])]);
            yield new ToolCallComplete([new ToolCall('call-2', 'look_up'), new ToolCall('call-3', 'roll')]);
        })()), new TokenUsage(promptTokens: 5, completionTokens: 2))]);

        $stream = $completions->stream(new ModelCall('main', 'model-a', new Prompt(null, 'Tell'), ['temperature' => 0.5], Output::Text, null, [new ToolDefinition('roll', 'Rolls dice.')]));

        self::assertSame(['Once ', 'upon'], iterator_to_array($stream, false));
        $completion = $stream->getReturn();
        self::assertSame(['Once upon', 7], [$completion->text, $completion->tokens->total()]);
        self::assertEquals([new ProposedToolCall('call-1', 'roll', ['dice' => '2d6']), new ProposedToolCall('call-2', 'look_up'), new ProposedToolCall('call-3', 'roll')], $completion->toolCalls);
        self::assertSame([true, 0.5], [$this->calls[0][2]['stream'] ?? null, $this->calls[0][2]['temperature'] ?? null]);
        self::assertArrayHasKey('tools', $this->calls[0][2]);
    }

    public function testAnAnswerOfTheWrongShapeIsAnError(): void
    {
        try {
            iterator_to_array(new SymfonyAiCompletions(['main' => $this->answering(new TextResult('whole'), null)])->stream(new ModelCall('main', 'model-a', new Prompt(null, 'x'))));
            self::fail('A whole answer passed for a stream.');
        } catch (UnexpectedValueException $unexpectedValueException) {
            self::assertSame('The platform answered '.TextResult::class.' to a request for a stream.', $unexpectedValueException->getMessage());
        }

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('The platform answered array where text was expected.');
        new SymfonyAiCompletions(['main' => $this->answering(new VectorResult(), null)])->complete(new ModelCall('main', 'model-a', new Prompt(null, 'x')));
    }

    public function answering(ResultInterface $result, ?TokenUsage $usage): PlatformInterface
    {
        return MockPlatformFactory::createPlatform(function (Model $model, array|string $payload, array $options) use ($result, $usage): ResultInterface {
            $this->calls[] = ['answering', $model->getName(), $options, \is_array($payload) ? $payload['messages'] ?? null : $payload];

            if ($usage instanceof TokenUsage) {
                $result->getMetadata()->add('token_usage', $usage);
            }

            return $result;
        });
    }

    public function platform(string $name, ?TokenUsage $usage): PlatformInterface
    {
        return MockPlatformFactory::createPlatform(function (Model $model, array|string $payload, array $options) use ($name, $usage): TextResult {
            $this->calls[] = [$name, $model->getName(), $options, \is_array($payload) ? $payload['messages'] : null];
            $result = new TextResult($name.' answers');

            if ($usage instanceof TokenUsage) {
                $result->getMetadata()->add('token_usage', $usage);
            }

            return $result;
        });
    }
}
