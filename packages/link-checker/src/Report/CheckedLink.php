<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Report;

use JsonSerializable;
use Override;
use Phil\LinkChecker\Extraction\Link;
use Phil\LinkChecker\LinkResult;
use Phil\Url\Url;

/**
 * One link of a content and what its check found.
 */
final readonly class CheckedLink implements JsonSerializable
{
    public function __construct(
        public Link $link,
        public LinkResult $result,
    ) {
    }

    /**
     * One line of a report: the status and the code, the address, where the link stands, its target and its reason.
     */
    public function toText(): string
    {
        $link = $this->link;
        $result = $this->result;

        return $result->status->value
            .(null === $result->code ? '' : ' '.$result->code)
            .': '.($link->url?->toString() ?? $link->href)
            .' ('.$link->element.' '.$link->attribute.('' === $link->text ? '' : ' "'.$link->text.'"').')'
            .($result->target instanceof Url ? ' -> '.$result->target->toString() : '')
            .(null === $result->reason ? '' : ', '.$result->reason);
    }

    /**
     * @return array{url: string|null, href: string, element: string, attribute: string, text: string, status: string, code: int|null, target: string|null, reason: string|null}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'url' => $this->link->url?->toString(),
            'href' => $this->link->href,
            'element' => $this->link->element,
            'attribute' => $this->link->attribute,
            'text' => $this->link->text,
            'status' => $this->result->status->value,
            'code' => $this->result->code,
            'target' => $this->result->target?->toString(),
            'reason' => $this->result->reason,
        ];
    }
}
