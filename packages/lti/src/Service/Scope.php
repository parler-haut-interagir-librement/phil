<?php

declare(strict_types=1);

namespace Phil\Lti\Service;

/**
 * The scopes of LTI Advantage that a tool asks the token endpoint for.
 */
final class Scope
{
    /** Read, create, change and delete the line items of the context. */
    public const string LINE_ITEM = 'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem';

    /** Read the line items of the context. */
    public const string LINE_ITEM_READ_ONLY = 'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem.readonly';

    /** Read the results of a line item. */
    public const string RESULT_READ_ONLY = 'https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly';

    /** Send scores. */
    public const string SCORE = 'https://purl.imsglobal.org/spec/lti-ags/scope/score';

    /** Read the members of the context. */
    public const string MEMBERSHIP_READ_ONLY = 'https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly';

    public const array ALL = [self::LINE_ITEM, self::LINE_ITEM_READ_ONLY, self::RESULT_READ_ONLY, self::SCORE, self::MEMBERSHIP_READ_ONLY];
}
