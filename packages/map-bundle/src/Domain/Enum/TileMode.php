<?php

declare(strict_types=1);

namespace Phil\MapBundle\Domain\Enum;

/**
 * How the visitor's browser gets the tiles of a geographic map.
 *
 * - `Proxy`: from the site itself, which fetches them from the tile source and keeps them; the source never sees the
 *   visitor. The default.
 * - `Direct`: from the tile source, without referrer and without cookies; the source sees the visitor's address.
 * - `Pmtiles`: from one PMTiles archive the site serves, read by range requests in the browser.
 * - `None`: no tiles; the markers and shapes alone.
 */
enum TileMode: string
{
    case Proxy = 'proxy';
    case Direct = 'direct';
    case Pmtiles = 'pmtiles';
    case None = 'none';
}
