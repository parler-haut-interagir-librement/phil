<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheck\Tests\Unit\Fixture;

use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Checks that vote on an argument Symfony maps from the request and validates first, with and without a check
 * before them.
 */
final class PayloadController
{
    /**
     * @param array<string, mixed> $order
     */
    #[IsGranted('ORDER_EDIT', 'order')]
    public function update(#[MapRequestPayload] array $order): string
    {
        return 'update';
    }

    /**
     * @param array<string, mixed> $order
     */
    #[IsGranted('IS_AUTHENTICATED')]
    #[IsGranted('ORDER_EDIT', subject: 'order')]
    public function guarded(#[MapRequestPayload] array $order): string
    {
        return 'guarded';
    }

    /**
     * @param array<string, mixed> $order
     */
    #[IsGranted('ORDER_EDIT', subject: 'order')]
    #[IsGranted('IS_AUTHENTICATED')]
    public function guardedTooLate(#[MapRequestPayload] array $order): string
    {
        return 'guarded too late';
    }

    /**
     * @param array<string, mixed> $query
     */
    #[IsGranted('SEARCH', ['other', 'query'])]
    public function search(int $other, #[MapQueryString] array $query): string
    {
        return 'search';
    }

    #[IsGranted('UPLOAD', 'file')]
    public function upload(#[MapUploadedFile] mixed $file): string
    {
        return 'upload';
    }

    #[IsGranted('VIEW', 'id')]
    public function show(int $id): string
    {
        return 'show';
    }

    #[IsGranted('VIEW', 'missing')]
    public function unknownSubject(int $id): string
    {
        return 'unknown subject';
    }
}
