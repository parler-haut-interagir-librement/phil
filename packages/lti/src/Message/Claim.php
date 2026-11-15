<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

/**
 * The names of the claims of LTI 1.3 and of its services.
 */
final class Claim
{
    public const string PREFIX = 'https://purl.imsglobal.org/spec/lti/claim/';

    public const string MESSAGE_TYPE = self::PREFIX.'message_type';

    public const string VERSION = self::PREFIX.'version';

    public const string DEPLOYMENT_ID = self::PREFIX.'deployment_id';

    public const string TARGET_LINK_URI = self::PREFIX.'target_link_uri';

    public const string RESOURCE_LINK = self::PREFIX.'resource_link';

    public const string ROLES = self::PREFIX.'roles';

    public const string ROLE_SCOPE_MENTOR = self::PREFIX.'role_scope_mentor';

    public const string CONTEXT = self::PREFIX.'context';

    public const string TOOL_PLATFORM = self::PREFIX.'tool_platform';

    public const string LAUNCH_PRESENTATION = self::PREFIX.'launch_presentation';

    public const string LIS = self::PREFIX.'lis';

    public const string CUSTOM = self::PREFIX.'custom';

    public const string ENDPOINT = 'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint';

    public const string NAMES_ROLE_SERVICE = 'https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice';

    public const string DEEP_LINKING_SETTINGS = 'https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings';

    public const string CONTENT_ITEMS = 'https://purl.imsglobal.org/spec/lti-dl/claim/content_items';

    public const string DATA = 'https://purl.imsglobal.org/spec/lti-dl/claim/data';

    public const string MESSAGE = 'https://purl.imsglobal.org/spec/lti-dl/claim/msg';

    public const string LOG = 'https://purl.imsglobal.org/spec/lti-dl/claim/log';

    public const string ERROR_MESSAGE = 'https://purl.imsglobal.org/spec/lti-dl/claim/errormsg';

    public const string ERROR_LOG = 'https://purl.imsglobal.org/spec/lti-dl/claim/errorlog';

    /**
     * The version every message of LTI 1.3 carries.
     */
    public const string LTI_VERSION = '1.3.0';
}
