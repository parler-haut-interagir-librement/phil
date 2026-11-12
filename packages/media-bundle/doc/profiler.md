# The profiler panel

In debug, the bundle wraps the storage and the image processor in `TraceableMediaStorage` and
`TraceableImageProcessor`, which note in a `MediaTrace` what a request did, and registers `MediaDataCollector` with its
template, `@PhilMedia/profiler/collector.html.twig`. With WebProfilerBundle, the toolbar shows how many images the
request rendered, and the panel lists:

- each rendering: its format, its file type, its size in bytes and the time it took;
- each storage operation: a read — found or missing —, a write, a deletion, with its key and size.

A rendering read back from the storage is a hit: the toolbar counts them, so a page that renders its formats on every
request stands out. Outside debug, nothing is wrapped and nothing is noted.
