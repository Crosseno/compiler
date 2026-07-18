# Repository guidance

- Keep importers, language transformation, merge policy, scoring, and artifact writing behind explicit boundaries.
- Never fetch remote sources implicitly; V1 accepts local regular files only.
- Apply byte, record, field, and text limits before allocating or decoding untrusted input.
- Preserve stable-key and row ordering; compiler output must not contain timestamps or absolute paths.
- Build in a temporary sibling directory and publish only after all integrity checks succeed.
- Keep runtime readers out of production dependencies; `lexicon-sqlite` is validation-only.
- Run `composer check` before handoff.
