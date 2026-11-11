# Testing

| Area | What is covered |
|---|---|
| `ResourceReference`, `Usage`, `StillReferenced` | Every check and bound; the message with more than three sources |
| `References` | Refresh and forget, every link a collector yields, the deletion check with sources leaving, broken links |
| `DbalUsageRepository`, `UsageTable`, the schema listener | Replace with duplicates, usages, the usages of many targets in their order and by chunks, targets, forget by kind and identifier; the table |
| `ReferenceTrackingListener`, `CheckReferencesCommand` | Refresh and forget in the order of the flushes; deletions refused and allowed; the command |
| `PhilReferencesBundle` | Services, autoconfiguration, configuration |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, pages whose JSON blocks point to
images, and a collector and target: `ReferencesBundleTest` flushes, lists usages and broken links, and sees a flush
that deletes an image in use rolled back.

```sh
vendor/bin/phpunit packages/references-bundle/tests
```

## Benchmarks

The phpbench subjects in `tests/Benchmark` measure the hot paths at several sizes. From the monorepo root:

```sh
composer bench -- packages/references-bundle/tests/Benchmark
```
