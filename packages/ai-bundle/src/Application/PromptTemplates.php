<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

use Phil\AiBundle\Application\Exception\MissingPrompt;
use Phil\AiBundle\Domain\ValueObject\TaskKind;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The prompts of every task, as translation messages: `<kind>.system`, optional, and `<kind>.user` in one domain,
 * `phil_ai` unless configured, read in the locale the task asks for with the translator's fallbacks.
 *
 * The task's parameters fill the placeholders written `{name}`, in a plain catalogue as in an ICU one.
 */
final readonly class PromptTemplates
{
    public const string DEFAULT_DOMAIN = 'phil_ai';

    public function __construct(
        private TranslatorInterface $translator,
        private string $domain = self::DEFAULT_DOMAIN,
    ) {
    }

    /**
     * @param array<string, string> $parameters
     *
     * @throws MissingPrompt when the user prompt has no translation
     */
    public function render(TaskKind $kind, string $locale, array $parameters = []): Prompt
    {
        $values = [];

        foreach ($parameters as $name => $value) {
            $values['{'.$name.'}'] = $value;
        }

        return new Prompt(
            $this->message($kind->value.'.system', $values, $locale),
            $this->message($kind->value.'.user', $values, $locale) ?? throw MissingPrompt::of($kind->value, $locale, $this->domain),
        );
    }

    /**
     * @param array<string, string> $values
     *
     * @return string|null null when the translator has no such message and answers with its identifier
     */
    private function message(string $id, array $values, string $locale): ?string
    {
        $text = $this->translator->trans($id, $values, $this->domain, $locale);

        return $id === $text ? null : $text;
    }
}
