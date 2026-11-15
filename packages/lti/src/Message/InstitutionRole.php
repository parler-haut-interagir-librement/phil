<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

/**
 * The roles of a person in the institution of the platform, by the vocabulary of LIS.
 */
enum InstitutionRole: string
{
    case Administrator = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Administrator';
    case Faculty = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Faculty';
    case Guest = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Guest';
    case Instructor = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Instructor';
    case Learner = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Learner';
    case Member = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Member';
    case Mentor = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Mentor';
    case Staff = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Staff';
    case Student = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Student';
}
