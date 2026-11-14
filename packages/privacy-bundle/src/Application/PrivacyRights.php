<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application;

/**
 * Which rights a member exercises from the preferences page.
 *
 * Both are on by default. **Turning one off is a destructive gesture**, so it asks for what a code host asks before
 * deleting a repository: the full sentence below, typed in the configuration. It then raises a warning at every container
 * build and before every console command.
 */
final readonly class PrivacyRights
{
    public const string DISABLE_EXPORT_SENTENCE = 'I understand that members of this site will no longer be able to export their personal data from their preferences';

    public const string DISABLE_ERASURE_SENTENCE = 'I understand that members of this site will no longer be able to erase their account and personal data from their preferences';

    public function __construct(
        public bool $export = true,
        public bool $erasure = true,
    ) {
    }

    /**
     * The warning for what is turned off, one line each, or nothing.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $warnings = [];

        if (!$this->export) {
            $warnings[] = 'phil_privacy.rights.export is DISABLED: members can NOT export their personal data from their preferences. Privacy laws give them that right; you now answer every request by hand.';
        }

        if (!$this->erasure) {
            $warnings[] = 'phil_privacy.rights.erasure is DISABLED: members can NOT erase their account from their preferences. Privacy laws give them that right; you now answer every request by hand.';
        }

        return $warnings;
    }
}
