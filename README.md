# Crosseno Compiler

Deterministic offline compilation of raw lexical data into immutable Crosseno language packs.

## Install and check

```bash
composer install
composer check
```

## CLI

```bash
vendor/bin/crosseno-compiler import csv words.csv canonical.ndjson
vendor/bin/crosseno-compiler compile compiler.json build/pack
vendor/bin/crosseno-compiler validate build/pack
vendor/bin/crosseno-compiler inspect build/pack
vendor/bin/crosseno-compiler stats build/pack
```

`compile` accepts local CSV, TSV, JSON, NDJSON, SQLite, and WordNet-like JSON sources. The configuration format is documented by `resources/schema/compiler-config-v1.schema.json`; `examples/compiler.json` is a minimal template. V1 intentionally performs no remote fetching.

Catalog-only artifacts are development outputs. Publishable packs additionally install an implementation of `ArtifactWriterInterface` from `crosseno/lexicon-index` to emit `solver.idx`.
