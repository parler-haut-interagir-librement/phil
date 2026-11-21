<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Handler;

use Phil\EmailAddress\Contract\MailDomainResolver;
use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\EmailAddress\MailDomain;
use Phil\NewsletterBundle\Application\Content\ContentRenderer;
use Phil\NewsletterBundle\Application\Content\RenderedMessage;
use Phil\NewsletterBundle\Application\Message\SendConfirmation;
use Phil\NewsletterBundle\Application\NewsletterUrls;
use Phil\NewsletterBundle\Application\Sending\MessageComposer;
use Phil\NewsletterBundle\Application\Sending\SenderProfiles;
use Phil\NewsletterBundle\Application\SignedLinks;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Contract\SubscriberRepository;
use Phil\NewsletterBundle\Domain\Contract\TemplateRepository;
use Phil\NewsletterBundle\Domain\Enum\LinkPurpose;
use Phil\NewsletterBundle\Domain\Enum\MembershipStatus;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Membership;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\Model\Template;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Twig\Environment;

/**
 * Sends the confirmation of a double opt-in, with its signed link, valid seven days. The site's template
 * `confirmation`, or the shared one, is rendered in the sandbox; without one, the bundle's own template. A
 * subscription confirmed or left meanwhile gets nothing.
 *
 * With a resolver of mail domains, the domain of the address is asked first, here in the worker rather than during the
 * request that subscribed: a domain that receives no mail — no MX, A nor AAAA record, or a null MX — gets no
 * confirmation, its subscription is marked refused, and a notice is logged with the identifiers of the subscriber and
 * the list, never the address. A lookup that fails says nothing, and the confirmation goes out.
 *
 * The email goes through Symfony Mailer's transports, never `MailerInterface`: with `SendEmailMessage` routed to a
 * Messenger transport, the address and the signed link would wait in a second queue. A refusal of the transport is
 * thrown, and Messenger retries the `SendConfirmation` message, which carries identifiers only.
 */
#[AsMessageHandler]
final readonly class SendConfirmationHandler
{
    public const string TEMPLATE = 'confirmation';

    public const string BUILT_IN = '@PhilNewsletter/email/confirmation.html.twig';

    public const int LIFETIME = 604_800;

    private LoggerInterface $logger;

    public function __construct(
        private SubscriberRepository $subscribers,
        private MailingListRepository $lists,
        private TemplateRepository $templates,
        private SenderProfiles $profiles,
        private SignedLinks $links,
        private NewsletterUrls $urls,
        private ContentRenderer $renderer,
        private MessageComposer $composer,
        private Environment $twig,
        private TransportInterface $transport,
        private ClockInterface $clock,
        private ?MailDomainResolver $mailDomains = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(SendConfirmation $message): void
    {
        $subscriber = $this->subscribers->get($message->subscriberId);
        $list = $this->lists->get($message->listId);
        $membership = $this->subscribers->membership($message->subscriberId, $message->listId);

        if (!$subscriber instanceof Subscriber || !$list instanceof MailingList || !$membership instanceof Membership || MembershipStatus::Pending !== $membership->status || $subscriber->isSuppressed()) {
            return;
        }

        $mailDomain = $this->mailDomain($subscriber);

        if (false === $mailDomain?->acceptsMail()) {
            $membership->refuse($this->clock->now());
            $this->subscribers->saveMembership($membership);
            $this->logger->notice('The confirmation of subscriber {subscriber} to list {list} was not sent: the domain of the address receives no mail ({mail_domain}).', [
                'subscriber' => $subscriber->id,
                'list' => $list->id,
                'mail_domain' => $mailDomain->value,
            ]);

            return;
        }

        $context = [
            'list_name' => $list->name,
            'site' => $list->site->key,
            'confirm_url' => $this->urls->to(NewsletterUrls::CONFIRM, $this->links->sign(LinkPurpose::Confirm, $subscriber->id, $list->id, self::LIFETIME), $list->site),
        ];
        $template = $this->templates->find($list->site, self::TEMPLATE);

        if ($template instanceof Template) {
            $rendered = $this->renderer->template($template, $context);
        } else {
            $page = $this->twig->load(self::BUILT_IN);
            $html = $page->renderBlock('html', $context);
            $rendered = new RenderedMessage(trim($page->renderBlock('subject', $context)), $html, $this->renderer->text($html));
        }

        $this->transport->send($this->composer->compose('confirm-'.substr(hash('sha256', $subscriber->id.$list->id), 0, 24), $subscriber, $this->profiles->get($list->senderProfile), $rendered, '', $list, false));
    }

    /**
     * What the DNS says of the domain of the address; nothing without a resolver, or for an address at an IP literal.
     */
    private function mailDomain(Subscriber $subscriber): ?MailDomain
    {
        if (!$this->mailDomains instanceof MailDomainResolver) {
            return null;
        }

        try {
            $address = EmailAddress::parse($subscriber->email);
        } catch (InvalidEmailAddress) {
            return null;
        }

        return $address->isIpLiteral() ? null : $this->mailDomains->resolve($address->asciiDomain);
    }
}
