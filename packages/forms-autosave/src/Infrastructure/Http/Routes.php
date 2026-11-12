<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Infrastructure\Http;

/**
 * The names of the routes of `config/routes.php`: one path, a draft by its key, read, saved or discarded by method.
 */
final class Routes
{
    public const string DRAFT = 'phil_forms_autosave_draft';

    public const string SAVE = 'phil_forms_autosave_draft_save';

    public const string DISCARD = 'phil_forms_autosave_draft_discard';

    private function __construct()
    {
    }
}
