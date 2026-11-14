<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional\Sites;

use Phil\TenancyBundle\Application\Shop\ShopContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class SitesController
{
    public function __construct(
        private Security $security,
        private ShopContext $shop,
        private MailerInterface $mailer,
    ) {
    }

    public function whoami(): JsonResponse
    {
        return new JsonResponse(['account' => $this->security->getUser()?->getUserIdentifier(), 'shop' => $this->shop->current()?->value]);
    }

    public function catalogues(): JsonResponse
    {
        return new JsonResponse(array_map(static fn (TenantId $catalogue): string => $catalogue->value, $this->shop->catalogues()));
    }

    public function mail(): Response
    {
        $this->mailer->send(new Email()->to('ana@mail.test')->subject('Your order')->text('Thank you.'));

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
