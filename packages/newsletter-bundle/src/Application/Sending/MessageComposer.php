<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Sending;

use Phil\BounceParser\Verp;
use Phil\NewsletterBundle\Application\Content\RenderedMessage;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use SensitiveParameter;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Builds the email of one delivery: the profile's sender and reply address, the one-click unsubscribe of RFC 8058
 * (`List-Unsubscribe` and `List-Unsubscribe-Post`) unless the message is a confirmation, the list's `List-Id`, `Precedence: bulk` for a campaign, the
 * profile's transport through Mailer's `X-Transport`, a `TrackingHeader` that turns off the provider's open and click
 * tracking — no pixel is added, and `ClickTracker` counts the clicks of a campaign that asks for it —, the delivery's
 * id as Mailer metadata, which a provider's webhook sends back, and, with a bounce mailbox, a signed VERP return path
 * that names the delivery.
 */
final readonly class MessageComposer
{
    public const string METADATA = 'newsletter-delivery';

    public function __construct(
        private ?Verp $verp = null,
        #[SensitiveParameter]
        private string $verpKey = '',
    ) {
    }

    public function compose(string $deliveryId, Subscriber $subscriber, SenderProfile $profile, RenderedMessage $message, string $unsubscribeUrl, ?MailingList $list = null, bool $bulk = true): Email
    {
        $email = new Email()
            ->from(new Address($profile->from, $profile->fromName))
            ->to($subscriber->email)
            ->subject($message->subject)
            ->html($message->html)
            ->text($message->text);

        if (null !== $profile->replyTo) {
            $email->replyTo($profile->replyTo);
        }

        $headers = $email->getHeaders();

        if ('' !== $unsubscribeUrl) {
            $unsubscribe = '<'.$unsubscribeUrl.'>';

            if (null !== $profile->unsubscribeMailbox) {
                $unsubscribe .= ', <mailto:'.$profile->unsubscribeMailbox.'?subject=unsubscribe>';
            }

            $headers->addTextHeader('List-Unsubscribe', $unsubscribe);
            $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }

        if ($list instanceof MailingList) {
            $headers->addTextHeader('List-Id', \sprintf('%s <%s.%s.%s>', $list->name, $list->key, $list->site->key, substr((string) strrchr($profile->from, '@'), 1)));
        }

        if ($bulk) {
            $headers->addTextHeader('Precedence', 'bulk');
        }

        if (null !== $profile->transport) {
            $headers->addTextHeader('X-Transport', $profile->transport);
        }

        $headers->add(new TrackingHeader(false, false));
        $headers->add(new MetadataHeader(self::METADATA, $deliveryId));

        if ($this->verp instanceof Verp) {
            $email->returnPath($this->verp->encodeSigned($deliveryId, $this->verpKey));
        }

        return $email;
    }
}
