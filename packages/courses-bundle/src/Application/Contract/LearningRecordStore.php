<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Contract;

/**
 * Where the cmi5 statements and the state documents of the assignable units are kept: the store inside the bundle,
 * on DBAL, the remote store of the bundle, which sends the statements to an xAPI learning record store and keeps the
 * state documents local, or an adapter of the application. The bundle has checked every statement against the cmi5
 * rules before it reaches the store. The port names no agent: a state document is found by its activity, its
 * registration and its identifier.
 */
interface LearningRecordStore
{
    /**
     * Keeps a statement, given as the JSON object the assignable unit sent or the platform issued, its `id` set, with
     * the registration it belongs to.
     */
    public function store(string $statementId, string $statement, string $registration): void;

    /**
     * The state document of that activity, registration and identifier, or null.
     */
    public function state(string $activityId, string $registration, string $stateId): ?string;

    public function saveState(string $activityId, string $registration, string $stateId, string $document): void;

    public function deleteState(string $activityId, string $registration, string $stateId): void;
}
