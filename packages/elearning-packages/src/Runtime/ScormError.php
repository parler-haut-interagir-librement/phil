<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * The error codes of the run-time API, as each version numbers them, and the message `GetErrorString()` gives. The
 * same mistake has another number in each version: an element the data model does not define is `401` in SCORM 1.2
 * ("Not implemented error") and in SCORM 2004 ("Undefined Data Model Element"), a value of the wrong type `405` in SCORM
 * 1.2 and `406` in SCORM 2004. `ScormError::for()` gives the number of a mistake in a version.
 */
final class ScormError
{
    public const int NONE = 0;

    public const int GENERAL = 101;

    /** SCORM 1.2 */
    public const int INVALID_ARGUMENT_12 = 201;

    /** SCORM 1.2 */
    public const int CANNOT_HAVE_CHILDREN_12 = 202;

    /** SCORM 1.2 */
    public const int CANNOT_HAVE_COUNT_12 = 203;

    /** SCORM 1.2 */
    public const int NOT_INITIALIZED_12 = 301;

    /** SCORM 1.2 */
    public const int NOT_IMPLEMENTED_12 = 401;

    /** SCORM 1.2 */
    public const int KEYWORD_12 = 402;

    /** SCORM 1.2 */
    public const int READ_ONLY_12 = 403;

    /** SCORM 1.2 */
    public const int WRITE_ONLY_12 = 404;

    /** SCORM 1.2 */
    public const int TYPE_MISMATCH_12 = 405;

    public const int INITIALIZATION_FAILED = 102;

    public const int ALREADY_INITIALIZED = 103;

    public const int TERMINATED = 104;

    public const int TERMINATION_FAILED = 111;

    public const int TERMINATION_BEFORE_INITIALIZATION = 112;

    public const int TERMINATION_AFTER_TERMINATION = 113;

    public const int RETRIEVE_BEFORE_INITIALIZATION = 122;

    public const int RETRIEVE_AFTER_TERMINATION = 123;

    public const int STORE_BEFORE_INITIALIZATION = 132;

    public const int STORE_AFTER_TERMINATION = 133;

    public const int COMMIT_BEFORE_INITIALIZATION = 142;

    public const int COMMIT_AFTER_TERMINATION = 143;

    public const int GENERAL_ARGUMENT = 201;

    public const int GENERAL_GET = 301;

    public const int GENERAL_SET = 351;

    public const int GENERAL_COMMIT = 391;

    public const int UNDEFINED_ELEMENT = 401;

    public const int UNIMPLEMENTED_ELEMENT = 402;

    public const int NOT_INITIALIZED_VALUE = 403;

    public const int READ_ONLY = 404;

    public const int WRITE_ONLY = 405;

    public const int TYPE_MISMATCH = 406;

    public const int OUT_OF_RANGE = 407;

    public const int DEPENDENCY_NOT_ESTABLISHED = 408;

    private const array MESSAGES_12 = [
        0 => 'No error',
        101 => 'General exception',
        201 => 'Invalid argument error',
        202 => 'Element cannot have children',
        203 => 'Element cannot have count',
        301 => 'Not initialized',
        401 => 'Not implemented error',
        402 => 'Invalid set value, element is a keyword',
        403 => 'Element is read only',
        404 => 'Element is write only',
        405 => 'Incorrect data type',
    ];

    private const array MESSAGES_2004 = [
        0 => 'No Error',
        101 => 'General Exception',
        102 => 'General Initialization Failure',
        103 => 'Already Initialized',
        104 => 'Content Instance Terminated',
        111 => 'General Termination Failure',
        112 => 'Termination Before Initialization',
        113 => 'Termination After Termination',
        122 => 'Retrieve Data Before Initialization',
        123 => 'Retrieve Data After Termination',
        132 => 'Store Data Before Initialization',
        133 => 'Store Data After Termination',
        142 => 'Commit Before Initialization',
        143 => 'Commit After Termination',
        201 => 'General Argument Error',
        301 => 'General Get Failure',
        351 => 'General Set Failure',
        391 => 'General Commit Failure',
        401 => 'Undefined Data Model Element',
        402 => 'Unimplemented Data Model Element',
        403 => 'Data Model Element Value Not Initialized',
        404 => 'Data Model Element Is Read Only',
        405 => 'Data Model Element Is Write Only',
        406 => 'Data Model Element Type Mismatch',
        407 => 'Data Model Element Value Out Of Range',
        408 => 'Data Model Dependency Not Established',
    ];

    private function __construct()
    {
    }

    /**
     * The codes of a version, each with its message.
     *
     * @return array<int, string>
     */
    public static function codes(ScormVersion $version): array
    {
        return ScormVersion::Scorm12 === $version ? self::MESSAGES_12 : self::MESSAGES_2004;
    }

    /**
     * The message of a code in a version; the empty string for a code the version does not define, as
     * `GetErrorString()` answers it.
     */
    public static function message(ScormVersion $version, int $code): string
    {
        return self::codes($version)[$code] ?? '';
    }

    /**
     * The code of a mistake in a version.
     */
    public static function for(ScormVersion $version, Mistake $mistake): int
    {
        if (ScormVersion::Scorm12 === $version) {
            return match ($mistake) {
                Mistake::Undefined, Mistake::Unimplemented => self::NOT_IMPLEMENTED_12,
                Mistake::NotInitialized => self::NONE,
                Mistake::ReadOnly => self::READ_ONLY_12,
                Mistake::WriteOnly => self::WRITE_ONLY_12,
                Mistake::TypeMismatch, Mistake::OutOfRange, Mistake::Dependency => self::TYPE_MISMATCH_12,
                Mistake::OutOfOrder, Mistake::GetOutOfRange => self::INVALID_ARGUMENT_12,
                Mistake::NoChildren => self::CANNOT_HAVE_CHILDREN_12,
                Mistake::NoCount => self::CANNOT_HAVE_COUNT_12,
                Mistake::Keyword => self::KEYWORD_12,
                Mistake::SetFailed => self::GENERAL,
                Mistake::GetFailed => self::GENERAL,
            };
        }

        return match ($mistake) {
            Mistake::Undefined => self::UNDEFINED_ELEMENT,
            Mistake::Unimplemented => self::UNIMPLEMENTED_ELEMENT,
            Mistake::NotInitialized => self::NOT_INITIALIZED_VALUE,
            Mistake::ReadOnly, Mistake::Keyword => self::READ_ONLY,
            Mistake::WriteOnly => self::WRITE_ONLY,
            Mistake::TypeMismatch => self::TYPE_MISMATCH,
            Mistake::OutOfRange => self::OUT_OF_RANGE,
            Mistake::Dependency => self::DEPENDENCY_NOT_ESTABLISHED,
            Mistake::OutOfOrder, Mistake::SetFailed => self::GENERAL_SET,
            Mistake::GetOutOfRange, Mistake::NoChildren, Mistake::NoCount, Mistake::GetFailed => self::GENERAL_GET,
        };
    }
}
