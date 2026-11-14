<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\Repository;

use DateTimeImmutable;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\FormsBundle\Domain\Model\Form;

interface FormRepository
{
    public function save(Form|Entry ...$objects): void;

    public function remove(Form|Entry ...$objects): void;

    public function get(string $id): ?Form;

    public function byKey(string $key): ?Form;

    /**
     * @return list<Form> by key
     */
    public function forms(): array;

    /**
     * @return list<Entry> newest first
     */
    public function entries(Form $form, int $limit = 50, int $offset = 0): array;

    public function entry(string $id): ?Entry;

    /**
     * @return list<Entry> the entries whose time is over, oldest first
     */
    public function expired(DateTimeImmutable $now, int $limit): array;

    /**
     * @return list<Entry> the entries about a person — by their identifier, or by the digest of their address —,
     *                     oldest first
     */
    public function about(string $subject, string $emailDigest): array;
}
