# Testing

## The package's tests

`tests/Unit` covers the reader on a nested message of every common multipart and on a delivery report, digests, parts
without header lines or body, line breaks of both kinds, invalid types, unusable boundaries, delimiters that are not
alone on their line, missing closing lines, encoded containers, each limit at its bound and messages of hostile shape;
header fields and their unfolding, encoded words, parameters with sections and character sets, address lists; parts
built from values, bodies decoded, converted and streamed, dispositions, file names and attachments; and the decoders
across their chunk boundaries.

From the monorepo root:

```sh
vendor/bin/phpunit packages/mime-reader/tests
```

In the monorepo, Infection holds the package at 100 % of covered mutants.

## The fuzz target

`tests/Fuzz/mime-reader.php` fuzzes the reader with the seeds of `tests/Fuzz/corpus/mime-reader`: whatever it reads,
it returns a part and never throws nor warns; the parts it reads stay within the limits, every text it gives is valid
UTF-8, and no file name it proposes holds a directory.

```sh
composer fuzz -- mime-reader
```

## The benchmarks

`tests/Benchmark/MimeReaderBench.php` reads a delivery report, a nested message, a message with a 4 MB attachment
written to a stream, and messages of hostile shape: a megabyte of text, fifty thousand parts, a thousand levels of
nesting and two hundred boundaries never closed. Each costs a time that grows with the octets, bounded by the limits.

```sh
vendor/bin/phil-dev bench -- --filter=MimeReaderBench packages/mime-reader/tests/Benchmark
```
