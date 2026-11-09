# AGENTS.md

This file provides guidance to Agents when working with code in this repository.

## Overview

`esliph/validator`: a Zod-inspired data validation library for PHP (>= 8.3, no runtime dependencies). Namespace `Esliph\Validator\` → `src/`; tests in `Esliph\Validator\Tests\` → `tests/`.

## Commands

PHP/Composer are not expected on the host — everything runs through Docker via the `Makefile` (extra arguments go in `ARGS`):

```sh
make deps                    # composer install (composer:latest container)
make test                    # full PHPUnit suite
make test ARGS="--filter test_min_WithValueGreaterThanMin_ShouldPass"   # a single test
make test ARGS="tests/Schemas/Primitive/IntegerSchemaTest.php"          # a single file
make analyse                 # PHPStan (config in phpstan.neon: level 8, src + tests + benchmarks)
make cov                     # text coverage (pcov)
make cov_html                # HTML coverage in cache/tests/coverage
make bench                   # PHPBench, aggregate report (config in phpbench.json)
make bench ARGS="--filter=UnionInvalid --tag=before"                    # save a run as a baseline
make bench BENCH_SRC=cache/bench/<sha>/src ARGS="--tag=base"            # measure another copy of src/
make cli                     # bash shell inside the container
```

`make test` runs `composer run test -- $(ARGS)` inside the container, so `ARGS` is forwarded to `vendor/bin/phpunit`. `phpunit.xml` uses `failOnDeprecation="true"` — PHP deprecations break the suite. pcov is disabled by default (`docker/php/conf.d/pcov.ini`) and is only enabled in the coverage scripts.

Static analysis via PHPStan (`make analyse`, extra options in `ARGS`, e.g. `ARGS="--level=max"`); cache in `cache/phpstan`. No formatter is configured.

Benchmarks via PHPBench (`make bench`, `ARGS` forwarded to `phpbench run`): subjects in `benchmarks/*Bench.php` (namespace `Esliph\Validator\Benchmarks\`), tagged runs stored in `cache/phpbench` (machine-local), compared with `--ref=<tag>`. `benchmarks/bootstrap.php` honors `BENCH_SRC`: when it points at a directory, library classes load from there instead of `src/`, so the same benchmarks measure another version of the library without touching the working tree (a class missing there is an error, never a fallback to `src/`). Composer's process timeout is disabled for this script. The container has no opcache/JIT — numbers are only comparable within the same session.

## Architecture

**Public entry point:** `V` (static factory: `V::string()`, `V::int()`, `V::object([...])`, `V::_array(...)`, `V::union(...)`...). Names prefixed with `_` (`_array`, `_default`) exist because `array`/`default` are reserved words.

**Schema hierarchy:**
- `Schema` (abstract) — parse pipeline, rules, transforms, `optional()`, `_default()`, `refine()`.
- `CoercibleSchema` — adds the `coerce()` flag; each subclass decides in `parseType` what to coerce.
- `Primitive/`: `NumberSchema` (base of `IntegerSchema` and `FloatSchema`), `StringSchema`, `BooleanSchema`, `DateSchema`.
- `Complex/`: `ObjectSchema`, `ArraySchema`, `UnionSchema` (`acceptsNull()` is true: `null` is handed to the members).
- `Schemas/` (top level): `MixedSchema` accepts any value. `NullSchema` accepts only `null` (`acceptsNull()` is true). `LiteralSchema` accepts only the given value (`===` comparison; accepts `null` when the literal is `null`). `EnumSchema` accepts a list of scalars (strict `in_array`) or a `class-string<BackedEnum>` (checks the backing type before `tryFrom` and returns the case).

**`Schema::_parse($value, $path)` pipeline** (order matters):
1. `null` → applies the default (value or `Closure`) if there is one; otherwise returns ok if `optional`; otherwise a `required` issue if `acceptsNull()` (default `false`) is false; otherwise continues to `parseType()` with `null`.
2. `parseType()` (abstract) — type check + coercion; a failure short-circuits and is returned as it came.
3. `validateRules()` — runs every accumulated `Rule`, collecting *all* issues (does not stop at the first one). A failure comes out as `ParseResult::dirty`.
4. `validateType()` — hook for validating children (e.g. `ObjectSchema` validates each shape field, strict/catchall/passthrough mode; `ArraySchema` validates elements). No-op by default. A failure comes out as `ParseResult::dirty` (kept as is if already dirty).
5. `applyTransforms()` — transforms only run if everything passed.

Composite schemas call `$child->_parse($v, [...$path, $key])` directly on their children, propagating the `path` that becomes `Issue::$path`.

**Union reporting** (`UnionSchema::parseType`): the first member that passes wins, and its `ParseResult` is returned as is. If every member fails: `null` → a single `required` issue; otherwise the result of the first *dirty* member (it recognized the type and failed a rule or a child), returned as is; otherwise a single `invalid_union` "Invalid input" at the union's path. Issues from different members are never concatenated. Known limitation: "first dirty wins" may pick a less relevant member (e.g. a wrong discriminant makes the first object member dirty) — there is no discriminated union; order the members accordingly. The union requires at least 2 options at construction (`InvalidArgumentException` otherwise).

**Immutability:** schemas are values. A schema returned by a fluent method is never modified afterwards: every fluent method (`min`, `optional`, `coerce`, `strict`...) writes only to the `$clone` it created itself, before returning it (`addRule()` does this for rules). PHP's shallow clone is enough: `Rule`s, transforms, the default, shape children, `catchall`, the array item, union options and `DateTimeZone` are shared between the original and the derived schema, so a new composite schema needs no `__clone`. An object default is cloned on every parse (`resolveDefault`), which keeps a caller's mutation of a result out of the next parse. `ObjectSchema::required()` clones only the fields that are optional.

**Rules and errors:**
- `Validation\Rule` (readonly): `name`, `code`, `check: Closure(mixed $value, array $params): bool`, `message: string|Closure(mixed, array): string`, `params`. New validations must be created as a `Rule` with parameterized values in `params` (not captured in the closure) and a default message via closure.
- `Results\ParseResult` (readonly, private constructor): `ok($data)`, `fail(Issue)` for a single issue, `fails(Issue[])` for several. `dirty(Issue[])` and the `$dirty` flag are `@internal`: `dirty` is `true` when the failure happened after the type check (rules or children), and it is what the union uses to pick the member it reports.
- `Errors\Issue`: `path`, `message`, `code`. Codes are free-form strings (no enum); reuse the existing ones (`required`, `invalid_type`, `not_finite` for `INF`/`NAN` in numeric schemas, `too_small`, `too_big`, `invalid_format`, `custom` from `refine`, `invalid_literal`, `invalid_enum_value`, `invalid_union`, `invalid_date`, `unrecognized_keys`, etc. — see `grep -rhoE "(code: |issue\()'[a-z_]+'" src | sort -u`) before inventing a new one.
- **Schema issues go through `Schema::issue($code, $default, $value, $path, $params = [])`**, the single place where a schema creates an `Issue` on its own — mandatory for any new schema issue (issues from `Rule`s stay in `validateRules`). `$value` is the original input, before coercion (keep a copy before reassigning `$value`). `invalid_type` in the "Expected X, received Y" pattern never calls it directly: it goes through `Schema::typeIssue(string $expected, mixed $input, array $path, ?string $receivedText = null)`, the mandatory gate for that pattern. `$received` is `get_debug_type($input)`; the default text `"Expected $expected, received " . ($receivedText ?? $received)` is built only when there is no custom message, and a Closure gets `['expected' => $expected, 'received' => $received]` through `issue()`. The only `invalid_type` outside it is `ObjectSchema`'s coerced list ("Expected object or associative array, received list"). The default text is built before the call, so the success path pays nothing.
- `messages(array $messages): static` (fluent, immutable, maps merge with `array_replace`) swaps the message of any code the schema emits on its own: a `string` is always literal, a `Closure` is called with `($originalValue, $params)` and its return is cast to `string`. Codes are validated at call time against `static::messageCodes()` (`InvalidArgumentException` for an unknown code or a value that is neither string nor Closure). `messageCodes()` is static, base `['required', 'invalid_type']`, and each subclass extends or redefines it to list only what that class emits (e.g. `NullSchema` → `['invalid_type']`, `NumberSchema` adds `not_finite`). In a union, `required`/`invalid_union` come from the union's map; a dirty member's issue keeps that member's messages.
- Type vocabulary: `get_debug_type`, applied by `typeIssue` (`int`, `float`, `bool`, `null`, class name, `resource (stream)`), and the pattern "Expected X, received Y" with the same vocabulary on both sides (`Expected int, received string`).
- `Schema::formatValue($value)` (protected static) formats an expected value for messages: `Class::Case` for enum cases, `var_export` otherwise (e.g. "Expected literal 'a'").
- `parse()` throws `ValidatorException` (with path-grouping helpers: `getMessagesByPath`, `getFlatMessages`, `toArray`, `toJson`); `safeParse()` returns `ParseResult`; `isValid()` returns bool.
- Argument validation in fluent methods throws at construction — `InvalidArgumentException` for bad arguments (non-finite bounds, `between` with min > max, negative counts, invalid regex, non-Schema shape values, unknown `pick`/`omit` keys, union with fewer than 2 options), `LogicException` for deriving an object (`pick`, `omit`, `extend`, `merge`, `partial`, `required`, `shape`) after `refine`/`transform`, or for `merge($other)` when `$other` has a `refine`/`transform` (it would be silently dropped) — never a rule that always fails. Exceptions thrown by rule checks, message Closures, transforms or defaults propagate out of `parse`/`safeParse`/`isValid`.

## Tests

- Hierarchy of base cases with abstract hooks: `BaseSchemaTestCase` (tests common to every schema: required/optional/default/transform/refine/clone; requires `createSchema`, `getValidValue`, `getInvalidValue`, `getDefaultValue`, `getTransformFunction`, `getRefineCheck`) → `BaseCoercibleSchemaTestCase` (swaps the hook for `createSchemaCoercible`) → `NumberSchemaTestCase` (numeric rule tests, hook `createNumberSchema`) → `IntegerSchemaTest`/`FloatSchemaTest`/`NumberSchemaTest`. A new schema must extend the appropriate base to inherit the common tests.
- `BaseSchemaTestCase::provideAllInputTypes()` is a data provider with every PHP type (including `INF`, `NAN`, resource, closure), used via the `#[DataProvider]` attribute.
- Test naming: `test_<method>_<Condition>_Should<Result>`.

## Conventions

- 2-space indentation, opening brace on the same line, blank line after the class opening.
- Overridden methods marked with `#[Override]`; `Issue`/`Rule` constructors called with named arguments.
- Commits in Brazilian Portuguese (pt-BR) using the `type: Description` format (types used: `feat`, `fix`, `refactor`, `test`, `config`, `build`, `remove`).
