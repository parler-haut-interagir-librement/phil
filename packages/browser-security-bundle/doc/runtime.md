# Worker mode and profiler

## What lives where

| What | Where | Lifetime |
|---|---|---|
| The named policies, their header strings | `PolicyRegistry`, built from the configuration | the process; never changed |
| The overrides, nonces and hashes of a request, the headers written | `RequestState`, in the attributes of the main request | the request |
| The throttle's counters, the window of deduplication | the PSR-6 pools of the configuration | shared by every worker |
| The violations | the table `phil_csp_report` | until purged |

No service keeps anything between two requests: a worker of FrankenPHP or RoadRunner serves each request with its own
nonces and overrides, and needs no `kernel.reset` for the bundle. The profiler's collector empties itself on
`reset()`, as Symfony expects.

## Sub-requests

Only the main request gets headers. A fragment rendered in a sub-request — `render(controller())`, ESI rendered
inline — shares the nonces and overrides of its page, since `csp_nonce()` and `ContentSecurityPolicyContext` write to
the main request.

## The profiler

With WebProfilerBundle, the panel *Content Security Policy* shows the named policy enforced, the headers written —
one directive per line —, the number of overrides and the kinds of nonce the templates asked for. The paths of the
profiler and of its toolbar are in `exclude_paths`: the toolbar brings its own nonces.
