<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\Http;

/**
 * The names of the translation screen's routes, and the shapes of their parameters.
 */
final class Routes
{
    public const string PAGE = 'phil_translation_page';

    public const string SAVE = 'phil_translation_save';

    public const string EXPORT = 'phil_translation_export';

    public const string IMPORT = 'phil_translation_import';

    public const string COMMENT = 'phil_translation_comment';

    public const string MACHINE = 'phil_translation_machine';

    public const string GLOSSARY = 'phil_translation_glossary';

    public const string GLOSSARY_SAVE = 'phil_translation_glossary_save';

    public const string GLOSSARY_EXPORT = 'phil_translation_glossary_export';

    public const string GLOSSARY_IMPORT = 'phil_translation_glossary_import';

    public const string DOCUMENT = '[A-Za-z0-9_.:-]{1,255}';

    public const string LANGUAGE = '[A-Za-z0-9-]{2,35}';

    private function __construct()
    {
    }
}
