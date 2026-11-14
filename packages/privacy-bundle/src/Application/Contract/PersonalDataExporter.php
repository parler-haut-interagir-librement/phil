<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * One module's part of an access or portability answer.
 *
 * Each module that holds personal information implements this; `SubjectExport` assembles them. The module
 * returns its own records indexed as it sees fit, **built from the same read models the module already queries**,
 * so the export stays correct as the model changes instead of becoming a second view maintained by hand.
 */
interface PersonalDataExporter
{
    public function module(): ModuleName;

    /**
     * @return array<string, mixed> JSON-encodable data; an empty array when the module holds nothing on the person
     */
    public function export(DataSubject $subject): array;
}
