<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Internal;

use Override;
use php_user_filter;
use StreamBucket;

/**
 * A read filter that counts the bytes coming out of a stream and fails it once they pass its ByteBudget: the size an
 * archive declares for an entry is the archive's word, the bytes counted are not.
 *
 * @internal
 */
final class BudgetFilter extends php_user_filter
{
    public const string NAME = 'phil.spreadsheet.budget';

    /**
     * @param resource $in
     * @param resource $out
     * @param int      $consumed
     */
    #[Override]
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        $budget = $this->params;
        \assert($budget instanceof ByteBudget);

        while (($bucket = stream_bucket_make_writeable($in)) instanceof StreamBucket) {
            $consumed += $bucket->datalen;

            if (!$budget->spend($bucket->datalen)) {
                return \PSFS_ERR_FATAL;
            }

            stream_bucket_append($out, $bucket);
        }

        return \PSFS_PASS_ON;
    }

    /**
     * @param resource $stream
     */
    public static function attach(mixed $stream, ByteBudget $budget): void
    {
        if (!\in_array(self::NAME, stream_get_filters(), true)) {
            stream_filter_register(self::NAME, self::class);
        }

        stream_filter_append($stream, self::NAME, \STREAM_FILTER_READ, $budget);
    }
}
