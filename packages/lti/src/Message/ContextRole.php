<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

/**
 * The principal roles of a person in a context — a course, a group —, by the vocabulary of LIS that LTI 1.3 uses.
 */
enum ContextRole: string
{
    case Administrator = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Administrator';
    case ContentDeveloper = 'http://purl.imsglobal.org/vocab/lis/v2/membership#ContentDeveloper';
    case Instructor = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor';
    case Learner = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner';
    case Mentor = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Mentor';
    case Manager = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Manager';
    case Member = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Member';
    case Officer = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Officer';

    /**
     * The sub-role of teaching assistant, which comes with the principal role `Instructor`.
     */
    public const string TEACHING_ASSISTANT = 'http://purl.imsglobal.org/vocab/lis/v2/membership/Instructor#TeachingAssistant';
}
