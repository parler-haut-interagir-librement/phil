<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

/**
 * The roles of a person on the platform itself, by the vocabulary of LIS.
 */
enum SystemRole: string
{
    case Administrator = 'http://purl.imsglobal.org/vocab/lis/v2/system/person#Administrator';
    case None = 'http://purl.imsglobal.org/vocab/lis/v2/system/person#None';
    case SysAdmin = 'http://purl.imsglobal.org/vocab/lis/v2/system/person#SysAdmin';
    case SysSupport = 'http://purl.imsglobal.org/vocab/lis/v2/system/person#SysSupport';
    case User = 'http://purl.imsglobal.org/vocab/lis/v2/system/person#User';
    case TestUser = 'http://purl.imsglobal.org/vocab/lti/system/person#TestUser';
}
