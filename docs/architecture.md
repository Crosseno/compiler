# Architecture

`Import` adapters produce bounded `RawLexicalRecord` streams. `Pipeline` validates, NFC-normalizes, invokes language hooks, tokenizes, creates stable keys, rejects or merges records, and calculates deterministic scores. `Artifact` writers serialize the immutable model. `Application` coordinates temporary sibling builds, integrity checks, the canonical manifest, and atomic publication.

The public API has no Drupal or CMS coupling. Language-pack repositories provide concrete language services and may register a Step 06 solver-index writer through `ArtifactWriterInterface`.
