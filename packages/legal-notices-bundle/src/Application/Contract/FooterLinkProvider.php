<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Application\Contract;

/**
 * Links another package adds to the legal links of a footer, after the documents and the policies. A service tagged
 * `phil_legal_notices.footer_link` is asked each time the links are shown.
 */
interface FooterLinkProvider
{
    /**
     * Each link with its address and the translation key and domain of its label; none when there is nothing to link to.
     *
     * @return list<array{url: string, label: string, domain: string}>
     */
    public function footerLinks(): array;
}
