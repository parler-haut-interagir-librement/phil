<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Domain\EmailTracking\EmailTrackingChoice;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * Where each member's choice about tracked emails is kept: one choice per member, the last one replacing the one
 * before.
 */
interface EmailTrackingStore
{
    /**
     * The member's last choice, or null when they never made one.
     */
    public function find(DataSubject $subject): ?EmailTrackingChoice;

    /**
     * Keeps the choice in place of the member's earlier one.
     */
    public function save(EmailTrackingChoice $choice): void;

    /**
     * Removes the member's choice; a member without one is left as they are.
     */
    public function forget(DataSubject $subject): void;

    /**
     * The name of the store, as an erasure report names it.
     */
    public function store(): string;
}
