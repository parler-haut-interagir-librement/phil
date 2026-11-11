# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Read another configuration format | Build a `Chart` from `ChartType`, labels and `Dataset` values |
| Use the colours of a brand | Pass a palette to `SvgChartWriter` |
| Draw another kind of chart | Read the `Chart` and write the image yourself; `SvgChartWriter` is final |

The classes under `Internal` are not extension points.
