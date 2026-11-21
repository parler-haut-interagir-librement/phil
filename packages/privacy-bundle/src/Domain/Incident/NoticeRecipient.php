<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Incident;

/**
 * Who a notice of a confidentiality incident is written for, under the Québec *Regulation respecting confidentiality
 * incidents* (chapter A-2.1, r. 3.1): the Commission d'accès à l'information (section 3), each person concerned
 * (section 5), or the public, in the cases of section 6.
 */
enum NoticeRecipient: string
{
    case Commission = 'commission';

    case Subject = 'subject';

    case Public = 'public';

    /**
     * The section of the regulation that lists what the notice contains: 3 for the Commission, 5 for the people, whether
     * the notice reaches them one by one or in public.
     */
    public function section(): int
    {
        return self::Commission === $this ? 3 : 5;
    }

    /**
     * The rubrics of the notice, in the order of its section.
     *
     * @return list<NoticeRubric>
     */
    public function rubrics(): array
    {
        return array_values(array_filter(NoticeRubric::cases(), fn (NoticeRubric $rubric): bool => null !== $rubric->paragraph($this)));
    }
}
