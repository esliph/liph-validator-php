# Guide

This guide is for developers who use `esliph/validator` in a PHP application. It starts from zero and ends with handling errors in a real form. It uses only the public API: the `V` factory and the schemas it returns.

Install the library with Composer. It needs PHP 8.3 or newer and has no other dependencies:

```sh
composer require esliph/validator
```

## Reading order

Read `01` first. Then read the pages for the task you have in front of you.

| Page | You will learn to |
|---|---|
| [01-first-schema.md](01-first-schema.md) | validate a sign-up form, from one string to a whole object (tutorial) |
| [02-primitives.md](02-primitives.md) | validate strings, numbers, booleans, dates, fixed values and enums |
| [03-objects-arrays.md](03-objects-arrays.md) | validate objects, arrays and unions, and derive one schema from another |
| [04-coercion.md](04-coercion.md) | validate form, query string and JSON input |
| [05-rules-transforms.md](05-rules-transforms.md) | add your own rules, transforms and defaults |
| [06-errors.md](06-errors.md) | read and show validation errors |

[reference.md](reference.md) lists every method of every schema and every issue code.

To learn how the library works inside, read the [architecture notes](../architecture/README.md).
