<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application\Exception;

use Phil\SurveyBundle\Domain\Exception\SurveyException;
use RuntimeException;

/**
 * What a respondent asked is refused: the reason says why, as a key the templates translate.
 */
final class SurveyRefused extends RuntimeException implements SurveyException
{
    public const string CLOSED = 'closed';

    public const string ALREADY_ANSWERED = 'already_answered';

    public const string MEMBER_REQUIRED = 'member_required';

    public const string SUBJECT_REQUIRED = 'subject_required';

    public const string RESULTS_PRIVATE = 'results_private';

    public const string ADDENDA_CLOSED = 'addenda_closed';

    public const string UNKNOWN_RECEIPT = 'unknown_receipt';

    public const string UNTRUSTED_REQUEST = 'untrusted_request';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function closed(): self
    {
        return new self(self::CLOSED, 'The questionnaire is not open now.');
    }

    public static function alreadyAnswered(): self
    {
        return new self(self::ALREADY_ANSWERED, 'The respondent answered the questionnaire already.');
    }

    public static function memberRequired(): self
    {
        return new self(self::MEMBER_REQUIRED, 'Only a signed-in member answers this questionnaire.');
    }

    public static function subjectRequired(): self
    {
        return new self(self::SUBJECT_REQUIRED, 'A response to this questionnaire is about a subject the application knows.');
    }

    public static function resultsPrivate(): self
    {
        return new self(self::RESULTS_PRIVATE, 'The results of this questionnaire are not shown to respondents.');
    }

    public static function addendaClosed(): self
    {
        return new self(self::ADDENDA_CLOSED, 'This questionnaire takes no addendum, or this one is out of bounds.');
    }

    public static function unknownReceipt(): self
    {
        return new self(self::UNKNOWN_RECEIPT, 'No response of this questionnaire takes this addendum token, or it took all it takes.');
    }

    public static function untrustedRequest(): self
    {
        return new self(self::UNTRUSTED_REQUEST, 'The form was not sent by the page the bundle gave.');
    }
}
