# AGENTS.md

This file provides guidance to Agents when working with code in this repository.

`esliph/validator` is a Zod-inspired data validation library for PHP (>= 8.3, no runtime dependencies). PHP and Composer are not expected on the host: everything runs through Docker via the `Makefile`. Use `make test` for the PHPUnit suite and `make analyse` for PHPStan. The other targets are in [docs/architecture/tooling.md](docs/architecture/tooling.md).

## Read before you change

| If you are… | Read |
|---|---|
| finding your way around the code | [docs/architecture/overview.md](docs/architecture/overview.md) |
| changing how a value is parsed or the order of the steps | [docs/architecture/parse-pipeline.md](docs/architecture/parse-pipeline.md) |
| working on a schema class or on coercion | [docs/architecture/schemas.md](docs/architecture/schemas.md) |
| creating an `Issue` or a `Rule`, or changing a message | [docs/architecture/errors.md](docs/architecture/errors.md) |
| touching immutability, the union report or `strip` | [docs/architecture/decisions.md](docs/architecture/decisions.md) |
| adding a rule, an issue code or a schema | [docs/architecture/extending.md](docs/architecture/extending.md) |
| writing or changing tests | [docs/architecture/testing.md](docs/architecture/testing.md) |
| running tests, static analysis, coverage or benchmarks | [docs/architecture/tooling.md](docs/architecture/tooling.md) |
| using the public API in an example | [docs/guide/README.md](docs/guide/README.md) |
