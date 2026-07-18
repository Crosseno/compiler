# ADR 0001: Compiler boundaries and deterministic catalog output

Status: accepted

The compiler owns a copy of the versioned catalog schema and never imports a runtime reader to create output. Importers emit storage-neutral records. Language normalization and tokenization are injected. Optional complete-pack writers implement `ArtifactWriterInterface` at compilation time.

Catalog rows are sorted by stable keys, SQLite uses fixed page and journal settings, and artifacts exclude timestamps and absolute paths. Conflicting records sharing a stable key fail closed. Catalog-only packs keep the index compatibility tuple in metadata but omit `solver.idx` from the manifest.
