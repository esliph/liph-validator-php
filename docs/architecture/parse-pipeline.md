# How a value is parsed

Every schema parses a value with the same sequence of steps. The sequence lives in `Schema::_parse`, and each schema class changes only the hooks the sequence calls. The order of the steps is not arbitrary: each step makes an assumption that the steps before it have already guaranteed. This page lists the steps and the reason for their order.

## The steps

0. **Coercion.** Only when the schema has `coerce()` turned on and the value is not `null`, `coerceInput` converts the value. It returns the converted value, the original value when it does not convert, or `null` when the field is blank. The original input is kept apart as `$input`.
1. **Absent value.** If the value is `null`, the schema looks for an answer without checking the type: it applies the default if there is one (a plain value or a `Closure`); otherwise it returns success with `null` as the data when the schema is `optional()`; otherwise it returns a `required` issue, unless `acceptsNull` is true for the class, in which case the `null` goes on to step 2.
2. **Type check.** `parseType` checks only the type and fails with an issue if the type is wrong. A failure here ends the parse and is returned as it came.
3. **Children and normalizers.** `validateType` is the hook for work that needs the type to be right but runs before the rules. `ObjectSchema::validateType` parses each field of the shape and applies the unknown-key mode. `ArraySchema::validateType` parses each element. `StringSchema::validateType` applies the normalizers. A failure becomes a `dirty` result, and one that is already `dirty` is kept as it is.
4. **Rules.** `validateRules` runs every accumulated `Rule` over the output of step 3, and collects all the issues, not only the first. A failure becomes a `dirty` result.
5. **Transforms.** `applyTransforms` runs the transforms in order. They run only when everything before passed.

## Why this order

**Coercion comes before the `null` check.** A blank form field (`''` or only spaces) becomes `null` during coercion. After that, the default, `optional` and `required` decide what an absent value means, with one rule instead of one rule for `null` and another for blank text. If the check for `null` came first, a blank string would not count as absent.

**The default is resolved after coercion and is not coerced.** The default becomes the new `$input`, so any issue it causes quotes the default and not the `null` that was sent. The cost is that, on a schema with `coerce()`, the default must already have the output type. A default written like the raw input fails the type check:

```php
use Esliph\Validator\V;

$count = V::int()->coerce()->_default('5')->safeParse(null);

echo $count->issues[0]->code, "\n"; // prints: invalid_type
echo $count->issues[0]->message, "\n"; // prints: Expected int, received string
echo V::int()->coerce()->_default(5)->parse(null), "\n"; // prints: 5
```

The same trap exists for `DateSchema` and `ObjectSchema`, where the default is a string or an array that the coercion would have converted:

```php
use Esliph\Validator\V;

$date = V::date()->coerce()->_default('2024-01-15')->safeParse(null);
$object = V::object(['a' => V::int()])->coerce()->_default(['a' => 1])->safeParse(null);

echo $date->issues[0]->code, "\n"; // prints: invalid_date
echo $object->issues[0]->message, "\n"; // prints: Expected object, received array
echo V::object(['a' => V::int()])->coerce()->_default((object) ['a' => 1])->parse(null)->a, "\n"; // prints: 1
```

**The type check short-circuits.** Steps 3 to 5 read the value as the right type: the rules are closures typed for it, and the children are read with `get_object_vars` or `foreach`. Running them on a value of the wrong type would produce noise at best and a `TypeError` at worst. So a wrong type returns the one issue and nothing else.

**The rules run only after every child passed.** `validateRules` receives the output of `validateType`, which is the normalized string or the object with the unknown keys already applied. A `refine` on an object reads fields. If a field failed, the rule would read a value that the schema has already declared invalid, and its issue would be a second, misleading report of the same problem. So the rules run only when `validateType` returned success, and they see the cleaned value, not the raw input.

**The rules collect every issue.** A caller that shows the errors of a form needs all of them at once. The cost is that every rule runs even after one failed; this cost is measured by `IssueAccumulationBench`.

**Transforms run last, and only on success.** A transform may change the type or the shape of the value, and the rules are written for the type the schema validates. Running a transform before the rules would hand them a value they were not written for. Running it after a failure would transform a value that is going to be rejected.

## Paths

`Schema::safeParse` starts with an empty `path`. A composite schema calls the `_parse` of each child with the path extended by the key of the field or the index of the element. The path in the child's issues is the path the caller sees in `Issue::$path`, so an error inside a list of objects reads `['items', 0, 'name']`.

## What "dirty" means

A failure at step 2 is a plain failure: the schema did not recognize the type. A failure at step 3 or 4 is a `dirty` failure: the schema recognized the type, and the value failed a rule or a child. The flag is internal (`ParseResult::dirty` is marked `@internal`). Its only reader is `UnionSchema`, which uses it to decide which member to report when every member fails. That behavior, and why the union never mixes the issues of different members, is in [decisions.md](decisions.md).

## Related pages

- The classes that implement each hook: [schemas.md](schemas.md).
- How issues are built and why they pass through one method: [errors.md](errors.md).
