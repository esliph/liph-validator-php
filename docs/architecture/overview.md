# How esliph/validator is organized

`esliph/validator` is a data validation library for PHP 8.3 and later, inspired by Zod. It has no runtime dependencies. You describe the shape of the data once, as a schema, and the schema checks a value against that shape and returns the cleaned result or the list of problems.

This page gives the map: where each piece lives, how you reach it, and what happens during one parse. Each step of the parse has its own page, linked below.

## Namespaces and folders

`Esliph\Validator\` maps to `src/`. The tests live in `Esliph\Validator\Tests\` (`tests/`) and the benchmarks in `Esliph\Validator\Benchmarks\` (`benchmarks/`). Only `src/` ships in the Composer package.

| Folder | Holds |
|---|---|
| `src/V.php` | `V`, the static factory that is the public entry point |
| `src/Schemas/` | `Schema`, `CoercibleSchema` and the schemas that have no group: `MixedSchema`, `NullSchema`, `LiteralSchema`, `EnumSchema` |
| `src/Schemas/Primitive/` | `StringSchema`, `NumberSchema`, `IntegerSchema`, `FloatSchema`, `BooleanSchema`, `DateSchema` |
| `src/Schemas/Complex/` | `ObjectSchema`, `ArraySchema`, `UnionSchema` |
| `src/Validation/` | `Rule`, one check plus its message |
| `src/Results/` | `ParseResult`, the value a parse returns internally |
| `src/Errors/` | `Issue`, one problem, and `ValidatorException`, which carries a list of them |

The hierarchy of the schema classes is explained in [schemas.md](schemas.md).

## `V`, the entry point

You build every schema through a static method of `V`: `V::string()`, `V::int()`, `V::object([...])`, `V::union(...)` and so on. `V` is the only class a user needs to import to start.

Two names start with an underscore: the factory `V::_array()` and the method `Schema::_default()`. `array` and `default` are reserved words in PHP, so the plain names are not available for these methods.

## The life of one parse

A schema offers three ways to run a parse. They differ only in how they report the outcome:

- `Schema::parse` returns the cleaned value, or throws `ValidatorException`.
- `Schema::safeParse` returns a `ParseResult` and never throws for a validation failure.
- `Schema::isValid` returns a `bool`.

All three call `Schema::_parse` with an empty path. `_parse` runs the pipeline of steps and returns a `ParseResult`: `success`, the `data`, and the `issues`. `parse` turns a failed result into a `ValidatorException`, and `isValid` reads only `success`.

```php
use Esliph\Validator\V;

$schema = V::string()->min(3);
$result = $schema->safeParse('ab');

echo $result->success ? 'ok' : 'failed', "\n"; // prints: failed
echo $result->issues[0]->code, "\n"; // prints: too_small
```

The steps inside `_parse` are explained, with the reason for their order, in [parse-pipeline.md](parse-pipeline.md). The classes that produce the issues, and the rule that every issue goes through one method, are in [errors.md](errors.md). The costs and limits that follow from the design are in [decisions.md](decisions.md).

## Where to go next

- To change how a value is parsed or the order of the steps: [parse-pipeline.md](parse-pipeline.md).
- To add or change a schema class: [schemas.md](schemas.md), then [extending.md](extending.md).
- To use the library without changing it: the [guide](../guide/README.md).
