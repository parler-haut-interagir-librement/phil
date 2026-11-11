<?php

declare(strict_types=1);

namespace Phil\BounceParser;

/**
 * How the parser recognised a message.
 */
enum ReportFormat: string
{
    /** A delivery status notification, `multipart/report; report-type=delivery-status` (RFC 3464, RFC 6533). */
    case DeliveryStatus = 'delivery_status';
    /** An abuse feedback report, `multipart/report; report-type=feedback-report` (RFC 5965). */
    case FeedbackReport = 'feedback_report';
    /** A bounce written as text by a mail server that sends no notification of RFC 3464. */
    case PlainText = 'plain_text';
    /** An automatic reply (RFC 3834 and the fields mail programs add). */
    case AutoReply = 'auto_reply';
    /** None of these: only a rule can say what it is. */
    case Unrecognised = 'unrecognised';
}
