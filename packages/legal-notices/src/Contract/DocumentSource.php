<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Contract;

use Phil\LegalNotices\DocumentHistory;
use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\Exception\InvalidLegalNotice;

/**
 * Where the versions of the documents are kept: the configuration, a database, files.
 */
interface DocumentSource
{
    /**
     * Every version of a document, drafts included; an empty history when it has none.
     *
     * @throws InvalidLegalNotice when a version kept breaks a rule
     */
    public function history(DocumentType $type): DocumentHistory;
}
