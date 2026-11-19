<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Contract;

use Phil\GuardianConsent\ConfirmationToken;
use Phil\GuardianConsent\ConsentRequest;
use Phil\GuardianConsent\MemberId;

/**
 * What sends the library's two messages. The application writes them, in its words and languages, and builds the
 * confirmation link from the token: the library knows neither the site's addresses nor its mailer.
 */
interface Notifier
{
    /**
     * Sends the guardian, at `$request->guardian->email`, the link that opens the request. The link carries the
     * token's text and leads to a page that shows the request, the features and the text, and asks for the answer
     * by a form: following the link alone decides nothing, so that a mail scanner that opens it changes nothing.
     */
    public function sendConfirmation(ConsentRequest $request, ConfirmationToken $token): void;

    /**
     * Tells the member they have reached the age of consent: the consents their guardian gave no longer apply, and
     * they confirm their own choices.
     */
    public function sendHandoverNotice(MemberId $member): void;
}
