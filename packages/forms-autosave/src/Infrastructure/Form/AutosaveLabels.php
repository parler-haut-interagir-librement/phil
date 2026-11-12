<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Infrastructure\Form;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the controller says in the live region and its notice, translated in the domain `phil_forms_autosave` when the
 * application has a translator, in English otherwise.
 */
final readonly class AutosaveLabels
{
    public const string DOMAIN = 'phil_forms_autosave';

    /** The labels in English, by their name for the controller. */
    public const array ENGLISH = [
        'saved' => 'Draft saved on this device.',
        'savedServer' => 'Draft saved.',
        'restored' => 'Your unsaved input was restored.',
        'discarded' => 'The draft was discarded.',
        'conflict' => 'A newer draft was saved from another device or tab.',
        'tooLarge' => 'This form is too large to be kept as a draft.',
        'unavailable' => 'Drafts cannot be kept in this browser.',
        'discard' => 'Discard the draft',
        'dismiss' => 'Keep it',
        'useServer' => 'Use the newer draft',
    ];

    public function __construct(
        private ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        if (!$this->translator instanceof TranslatorInterface) {
            return self::ENGLISH;
        }

        $labels = [];

        foreach (array_keys(self::ENGLISH) as $name) {
            $labels[$name] = $this->translator->trans('phil_forms_autosave.'.$name, [], self::DOMAIN);
        }

        return $labels;
    }
}
