# Architecture

These pages explain how `esliph/validator` is built, why it is built that way, and how to change it. They are for people and agents who change the library. If you only use it, read the [guide](../guide/README.md).

## Reading order

Start with `overview.md`, then `parse-pipeline.md`, `schemas.md`, `errors.md` and `decisions.md`. Read `extending.md`, `testing.md` and `tooling.md` when the task needs them.

| Page | Answers |
|---|---|
| [overview.md](overview.md) | What are the pieces, where do they live and what happens in one parse? |
| [parse-pipeline.md](parse-pipeline.md) | What are the steps of a parse and why are they in this order? |
| [schemas.md](schemas.md) | What does each level of the schema hierarchy add? |
| [errors.md](errors.md) | How are issues, rules and messages created, and by which rules? |
| [decisions.md](decisions.md) | Why is it designed this way, and what does each choice cost? |
| [extending.md](extending.md) | How do I add a rule, an issue code or a schema? |
| [testing.md](testing.md) | Which test base class do I extend, and what does it require? |
| [tooling.md](tooling.md) | Which command runs the tests, the analysis and the benchmarks? |
