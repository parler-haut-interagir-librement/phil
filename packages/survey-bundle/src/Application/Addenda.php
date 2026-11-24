<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

use Phil\SurveyBundle\Application\Exception\SurveyRefused;
use Phil\SurveyBundle\Domain\Contract\ResponseRepository;
use Phil\SurveyBundle\Domain\ValueObject\Addendum;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Psr\Clock\ClockInterface;

/**
 * Adds to a response after it was sent, by the token its receipt gave: what a respondent thought of afterwards, as a
 * player adds to a letter written after a game. The token, 64 hexadecimal characters, is checked by its SHA-256, so
 * that anonymous responses take addenda without naming anyone; a response takes 10 at most, 5000 characters each.
 */
final readonly class Addenda
{
    public function __construct(
        private ResponseRepository $responses,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws SurveyRefused when the questionnaire takes no addendum, the text is out of bounds, or no response of it
     *                       takes this token
     */
    public function add(Questionnaire $questionnaire, string $token, string $text): void
    {
        $text = trim($text);

        if (!$questionnaire->addenda || '' === $text || !mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > Addendum::MAX_LENGTH) {
            throw SurveyRefused::addendaClosed();
        }

        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $token) || !$this->responses->addAddendum($questionnaire->key, hash('sha256', $token), $text, $this->clock->now(), Submissions::MAX_ADDENDA)) {
            throw SurveyRefused::unknownReceipt();
        }
    }
}
