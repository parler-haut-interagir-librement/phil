<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Infrastructure\Console;

use Override;
use php_user_filter;
use StreamBucket;

/**
 * The stream filter that hands what is written to a console stream to its line stamper.
 *
 * @internal
 */
final class TimestampFilter extends php_user_filter
{
    public const string NAME = 'phil_console.timestamps';

    /**
     * @param resource $in
     * @param resource $out
     * @param int      $consumed
     */
    #[Override]
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        $stamper = $this->params;

        while (($bucket = stream_bucket_make_writeable($in)) instanceof StreamBucket) {
            $consumed += $bucket->datalen;

            if ($stamper instanceof LineStamper) {
                $bucket->data = $stamper->stamp($bucket->data);
            }

            stream_bucket_append($out, $bucket);
        }

        return \PSFS_PASS_ON;
    }
}
