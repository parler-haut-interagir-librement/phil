<?php

declare(strict_types=1);

namespace Phil\AiBundle\Infrastructure\SymfonyAi;

use Generator;
use Override;
use Phil\AiBundle\Application\Completion;
use Phil\AiBundle\Application\Exception\UnknownPlatform;
use Phil\AiBundle\Application\Media;
use Phil\AiBundle\Application\ModelCall;
use Phil\AiBundle\Application\Port\Completions;
use Phil\AiBundle\Application\ProposedToolCall;
use Phil\AiBundle\Application\ToolDefinition;
use Phil\AiBundle\Domain\Journal\Output;
use Phil\AiBundle\Domain\ValueObject\Tokens;
use SensitiveParameter;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use UnexpectedValueException;

/**
 * @phpstan-import-type JsonSchema from Factory
 *
 * The models through Symfony AI: the application's platforms by name, and for each platform that takes a person's own
 * key, the factory that builds it on that key.
 *
 * A text or structured task sends its prompt as a system message, when there is one, and a user message; an image or
 * speech task sends the user prompt alone, as text-to-image and text-to-speech models take it. A structured task asks
 * for its class through Symfony AI's `response_format` option, which the platform's structured output subscriber turns
 * into a schema and the answer into an object. The tools go as Symfony AI tools; the calls the model proposes come back
 * without being run. The tokens are those the platform reports, zero when it reports none.
 */
final readonly class SymfonyAiCompletions implements Completions
{
    /**
     * @param array<string, PlatformInterface>      $platforms        by name, on the application's keys
     * @param array<string, UserKeyPlatformFactory> $userKeyFactories by name, for a person's own key
     */
    public function __construct(
        private array $platforms,
        private array $userKeyFactories = [],
    ) {
    }

    #[Override]
    public function complete(ModelCall $call, #[SensitiveParameter] ?string $userKey = null): Completion
    {
        $result = $this->platform($call, $userKey)->invoke($call->model, $this->input($call), $this->options($call))->getResult();
        $tokens = $this->tokens($result->getMetadata());

        return match (true) {
            $result instanceof ObjectResult => new Completion('', $tokens, $result->getContent()),
            $result instanceof ToolCallResult => new Completion('', $tokens, toolCalls: $this->proposed($result->getContent())),
            $result instanceof BinaryResult => new Completion('', $tokens, media: new Media($result->getContent(), $result->getMimeType() ?? 'application/octet-stream')),
            default => new Completion($this->text($result->getContent()), $tokens),
        };
    }

    #[Override]
    public function stream(ModelCall $call, #[SensitiveParameter] ?string $userKey = null): Generator
    {
        $result = $this->platform($call, $userKey)->invoke($call->model, $this->input($call), ['stream' => true] + $this->options($call))->getResult();

        if (!$result instanceof StreamResult) {
            throw new UnexpectedValueException(\sprintf('The platform answered %s to a request for a stream.', $result::class));
        }

        $text = '';
        $toolCalls = [];

        foreach ($result->getContent() as $delta) {
            if ($delta instanceof TextDelta) {
                $text .= $delta->getText();

                yield $delta->getText();
            } elseif ($delta instanceof ToolCallComplete) {
                $toolCalls = [...$toolCalls, ...$this->proposed($delta->getToolCalls())];
            }
        }

        return new Completion($text, $this->tokens($result->getMetadata()), toolCalls: $toolCalls);
    }

    private function platform(ModelCall $call, #[SensitiveParameter] ?string $userKey): PlatformInterface
    {
        return null === $userKey
            ? $this->platforms[$call->platform] ?? throw UnknownPlatform::of($call->platform) : ($this->userKeyFactories[$call->platform] ?? throw UnknownPlatform::withoutUserKeys($call->platform))->create($userKey);
    }

    private function input(ModelCall $call): MessageBag|string
    {
        if (Output::Image === $call->output || Output::Speech === $call->output) {
            return $call->prompt->user;
        }

        $user = Message::ofUser($call->prompt->user);

        return null === $call->prompt->system ? new MessageBag($user) : new MessageBag(Message::forSystem($call->prompt->system), $user);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(ModelCall $call): array
    {
        $options = $call->options;

        if (null !== $call->structure) {
            $options[PlatformSubscriber::RESPONSE_FORMAT] = $call->structure;
        }

        if ([] !== $call->tools) {
            // The reference names no code to run: the bundle sends the definitions and never runs a call.
            $options['tools'] = array_map(static fn (ToolDefinition $tool): Tool => new Tool(new ExecutionReference(ProposedToolCall::class), $tool->name, $tool->description, self::schema($tool->parameters)), $call->tools);
        }

        return $options;
    }

    /**
     * @param array<array-key, mixed> $calls
     *
     * @return list<ProposedToolCall>
     */
    private function proposed(array $calls): array
    {
        return array_values(array_map(
            static fn (ToolCall $call): ProposedToolCall => new ProposedToolCall($call->getId(), $call->getName(), $call->getArguments()),
            array_filter($calls, static fn (mixed $call): bool => $call instanceof ToolCall),
        ));
    }

    /**
     * @param array<string, mixed>|null $parameters
     *
     * @return JsonSchema|null
     */
    private static function schema(?array $parameters): ?array
    {
        /** @var JsonSchema|null $schema the application's schema, sent as it wrote it */
        $schema = $parameters;

        return $schema;
    }

    private function text(mixed $content): string
    {
        if (!\is_string($content)) {
            throw new UnexpectedValueException(\sprintf('The platform answered %s where text was expected.', get_debug_type($content)));
        }

        return $content;
    }

    private function tokens(Metadata $metadata): Tokens
    {
        $usage = $metadata->get('token_usage');

        return $usage instanceof TokenUsageInterface ? new Tokens($usage->getPromptTokens() ?? 0, $usage->getCompletionTokens() ?? 0) : Tokens::none();
    }
}
