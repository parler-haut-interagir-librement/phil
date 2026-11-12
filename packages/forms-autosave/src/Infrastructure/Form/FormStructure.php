<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Infrastructure\Form;

use Symfony\Component\Form\FormInterface;

/**
 * The version of a form's structure: a short hash of the path and type of each of its fields, so that a draft taken
 * from another structure — a field added, removed, renamed or of another type — is not put back into this one. A
 * collection counts by the type of its entries, not by how many it has, so that adding an entry keeps the version.
 */
final class FormStructure
{
    /** The length of a version: 16 hexadecimal digits. */
    public const int LENGTH = 16;

    private function __construct()
    {
    }

    /**
     * @param FormInterface<mixed> $form
     */
    public static function version(FormInterface $form): string
    {
        $lines = [];
        self::describe($form, '', $lines);

        return substr(hash('xxh128', implode("\n", $lines)), 0, self::LENGTH);
    }

    /**
     * @param FormInterface<mixed> $form
     * @param list<string>         $lines
     */
    private static function describe(FormInterface $form, string $path, array &$lines): void
    {
        $config = $form->getConfig();
        $lines[] = $path.' '.$config->getType()->getInnerType()::class;

        if ($config->hasOption('entry_type')) {
            $entry = $config->getOption('entry_type');
            $lines[] = $path.'[] '.(\is_string($entry) ? $entry : get_debug_type($entry));

            return;
        }

        foreach ($form as $name => $child) {
            self::describe($child, $path.'/'.$name, $lines);
        }
    }
}
