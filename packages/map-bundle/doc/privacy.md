# Privacy

The neighbourhood layer answers a question such as "are there members near me?" without telling where any member is.

## What it does

`PrivacyGrid` divides the world into cells of `neighbourhood.cell_metres` — rows from the equator, columns from the prime
meridian, a column's width following its row's latitude so that cells stay about square. `Neighbourhood` counts the
positions it is given by cell, and keeps the cells that hold at least `neighbourhood.minimum` of them. A kept cell is
drawn as its rectangle, with its count; its list entry names its middle to two decimals.

## What it never shows

- A position: neither the positions given nor anything derived from one alone comes out — only a cell's corners, its
  middle and its count.
- A cell of fewer than the minimum: with the default of 3, nobody can be found as the only person in an area.
- Who is in a cell: the layer counts, it does not name.

## Why a fixed grid

- Rounding coordinates is a grid too, but its cells shrink towards the poles and change size with the number of decimals;
  `PrivacyGrid` keeps them about as wide as they are high, in metres, everywhere below 89.4°.
- Random noise added to each position averages out: asking for the same map often enough finds the true position. A
  fixed cell answers the same thing every time.
- A grid anchored on the equator and the prime meridian puts the same position in the same cell on every site and every
  request, so that two maps cannot be combined to narrow it.

## What the application still decides

- Whose positions it gives: the members who agreed to appear, for instance.
- How precise the positions it stores are: the grid hides what it is given, the storage keeps it.
- The cell and the minimum: a sparse region needs larger cells for a cell to reach the minimum; a dense city can use
  smaller ones. A cell is from 100 m to 50 km.

A person near the edge of a cell is in that cell, not in the next: someone who knows a cell holds exactly the minimum,
and knows all but one of the people in it, learns that the last one is in that cell — never more precisely.
