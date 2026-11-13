<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application\Render;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Dom\Element;
use Dom\XMLDocument;
use LogicException;
use Phil\StatusPageBundle\Domain\ValueObject\Incident;
use Phil\StatusPageBundle\Domain\ValueObject\ScheduledMaintenance;
use Phil\StatusPageBundle\Domain\ValueObject\StatusPage;

/**
 * The page as an Atom feed (RFC 4287), for feed readers: one entry per incident going on or in the history, and per
 * maintenance in progress or upcoming. An entry's id is the address of its part of the page, so it stays the same
 * while its content changes; its content is plain text, every update with its date and state.
 */
final readonly class AtomFeed
{
    private const string ATOM_NAMESPACE = 'http://www.w3.org/2005/Atom';

    /**
     * @param PageLinks $links absolute addresses: an Atom id and its links are
     */
    public function render(StatusPage $page, PageLinks $links): string
    {
        $document = XMLDocument::createEmpty();
        $document->formatOutput = true;

        $feed = $document->createElementNS(self::ATOM_NAMESPACE, 'feed');
        $document->appendChild($feed);

        $entries = [];
        $updated = null;

        foreach ([...$page->activeIncidents, ...$page->history] as $incident) {
            $entries[] = $this->incident($document, $incident, $links->page);
            $updated = $this->later($updated, $incident->updatedAt());
        }

        foreach ([...$page->maintenancesInProgress, ...$page->upcomingMaintenances] as $maintenance) {
            $entries[] = $this->maintenance($document, $page, $maintenance, $links->page);
            $updated = $this->later($updated, $maintenance->startsAt);
        }

        $this->text($document, $feed, 'id', $links->page);
        $this->text($document, $feed, 'title', $page->title);
        $this->text($document, $feed, 'updated', $this->date($updated ?? $page->generatedAt));
        $this->link($document, $feed, 'self', $links->atom, 'application/atom+xml');
        $this->link($document, $feed, 'alternate', $links->page, 'text/html');
        $this->link($document, $feed, 'alternate', $links->json, 'application/json');
        $author = $document->createElementNS(self::ATOM_NAMESPACE, 'author');
        $this->text($document, $author, 'name', $page->title);
        $feed->appendChild($author);

        foreach ($entries as $entry) {
            $feed->appendChild($entry);
        }

        $xml = $document->saveXml();

        return false === $xml ? throw new LogicException('The feed could not be written.') : $xml;
    }

    private function incident(XMLDocument $document, Incident $incident, string $page): Element
    {
        $entry = $document->createElementNS(self::ATOM_NAMESPACE, 'entry');
        $address = $page.'#incident-'.$incident->id;
        $this->text($document, $entry, 'id', $address);
        $this->text($document, $entry, 'title', $incident->title);
        $this->text($document, $entry, 'published', $this->date($incident->startedAt()));
        $this->text($document, $entry, 'updated', $this->date($incident->updatedAt()));
        $this->link($document, $entry, 'alternate', $address, 'text/html');
        $category = $document->createElementNS(self::ATOM_NAMESPACE, 'category');
        $category->setAttribute('term', $incident->state()->value);

        $entry->appendChild($category);

        $lines = [];

        foreach ($incident->updates as $update) {
            $lines[] = \sprintf("%s — %s\n%s", $this->date($update->at), $update->state->value, $update->message);
        }

        $this->text($document, $entry, 'content', implode("\n\n", $lines))->setAttribute('type', 'text');

        return $entry;
    }

    private function maintenance(XMLDocument $document, StatusPage $page, ScheduledMaintenance $maintenance, string $address): Element
    {
        $entry = $document->createElementNS(self::ATOM_NAMESPACE, 'entry');
        $address .= '#maintenance-'.$maintenance->id;
        $this->text($document, $entry, 'id', $address);
        $this->text($document, $entry, 'title', $maintenance->title);
        $this->text($document, $entry, 'updated', $this->date($maintenance->startsAt));
        $this->link($document, $entry, 'alternate', $address, 'text/html');
        $category = $document->createElementNS(self::ATOM_NAMESPACE, 'category');
        $category->setAttribute('term', 'maintenance');

        $entry->appendChild($category);

        $components = implode(', ', array_map($page->componentName(...), $maintenance->components));
        $content = \sprintf("%s — %s\n%s", $this->date($maintenance->startsAt), $this->date($maintenance->endsAt), $components);
        $this->text($document, $entry, 'content', null === $maintenance->description ? $content : $content."\n\n".$maintenance->description)->setAttribute('type', 'text');

        return $entry;
    }

    private function text(XMLDocument $document, Element $parent, string $name, string $text): Element
    {
        $element = $document->createElementNS(self::ATOM_NAMESPACE, $name);
        $element->appendChild($document->createTextNode($text));

        $parent->appendChild($element);

        return $element;
    }

    private function link(XMLDocument $document, Element $parent, string $rel, string $href, string $type): void
    {
        $link = $document->createElementNS(self::ATOM_NAMESPACE, 'link');
        $link->setAttribute('rel', $rel);
        $link->setAttribute('href', $href);
        $link->setAttribute('type', $type);

        $parent->appendChild($link);
    }

    private function later(?DateTimeImmutable $current, DateTimeImmutable $candidate): DateTimeImmutable
    {
        return !$current instanceof DateTimeImmutable || $candidate > $current ? $candidate : $current;
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
    }
}
