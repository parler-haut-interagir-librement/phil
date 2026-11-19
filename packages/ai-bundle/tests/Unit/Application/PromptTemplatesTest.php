<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Application;

use Phil\AiBundle\Application\Exception\MissingPrompt;
use Phil\AiBundle\Application\Prompt;
use Phil\AiBundle\Application\PromptTemplates;
use Phil\AiBundle\Domain\ValueObject\TaskKind;
use Phil\AiBundle\Tests\Unit\Fixture\Prompts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PromptTemplates::class)]
#[CoversClass(Prompt::class)]
#[CoversClass(MissingPrompt::class)]
final class PromptTemplatesTest extends TestCase
{
    public function testThePromptsAreTranslatedAndFilled(): void
    {
        $templates = Prompts::templates();
        $kind = TaskKind::fromString('draft.rewrite');

        $english = $templates->render($kind, 'en', ['genre' => 'fantasy', 'text' => 'Once upon a time']);
        self::assertSame(['You edit fantasy prose.', 'Rewrite: Once upon a time'], [$english->system, $english->user]);

        // The French catalogue has the user prompt only: the system prompt falls back to English.
        $french = $templates->render($kind, 'fr', ['genre' => 'fantasy', 'text' => 'Il était une fois']);
        self::assertSame(['You edit fantasy prose.', 'Réécris : Il était une fois'], [$french->system, $french->user]);

        $bare = $templates->render(TaskKind::fromString('title.suggest'), 'en', ['text' => 'a dragon']);
        self::assertSame([null, 'Suggest a title for: a dragon'], [$bare->system, $bare->user]);
        self::assertSame('Suggest a title for: {text}', $templates->render(TaskKind::fromString('title.suggest'), 'en')->user);
    }

    public function testAnIcuCatalogueAndAnotherDomain(): void
    {
        $translator = Prompts::translator();
        $translator->addResource('array', ['names.invent.user' => 'Invent {count, plural, one {one name} other {# names}} for {people}.'], 'en', 'prompts+intl-icu');

        $templates = new PromptTemplates($translator, 'prompts');

        self::assertSame('Invent 3 names for dwarves.', $templates->render(TaskKind::fromString('names.invent'), 'en', ['count' => '3', 'people' => 'dwarves'])->user);

        try {
            $templates->render(TaskKind::fromString('draft.rewrite'), 'fr');
            self::fail('A prompt of another domain was read.');
        } catch (MissingPrompt $missingPrompt) {
            self::assertSame('The task "draft.rewrite" has no prompt "draft.rewrite.user" in the translation domain "prompts" for the locale "fr".', $missingPrompt->getMessage());
        }
    }

    public function testAMissingUserPromptIsRefused(): void
    {
        $this->expectExceptionObject(MissingPrompt::of('draft.expand', 'en', 'phil_ai'));
        $this->expectExceptionMessageIsOrContains('The task "draft.expand" has no prompt "draft.expand.user" in the translation domain "phil_ai" for the locale "en".');

        Prompts::templates()->render(TaskKind::fromString('draft.expand'), 'en');
    }
}
