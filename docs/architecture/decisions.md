# Design decisions and their costs

This page records the choices that shape how the library behaves, the alternative each one gave up, and what each one costs. When a cost has a benchmark, the page names it. It gives no numbers: the benchmarks run in a container without opcache or JIT, and their results compare only inside one session. To measure a change, see [tooling.md](tooling.md).

## Schemas are immutable

A schema is a value. Every fluent method (`min`, `optional`, `coerce`, `strict`, `transform`...) writes only to the clone it creates itself, before it returns it. A schema that a caller holds is never modified afterwards, so a schema can be shared: a base schema can be built once and extended in several places.

```php
use Esliph\Validator\V;

$base = V::string();
$short = $base->min(3);

echo $base->isValid('a') ? 'valid' : 'invalid', "\n"; // prints: valid
echo $short->isValid('a') ? 'valid' : 'invalid', "\n"; // prints: invalid
```

PHP's shallow `clone` is enough. The `Rule`s, the transforms, the default, the shape children of an object, the `catchall` schema, the element schema of an array, the members of a union and a `DateTimeZone` are shared between the original and the derived schema. They are never written to after they are created, so a new composite schema does not need a `__clone` method. `Schema::addRule` follows the same rule for the list of rules.

Two details complete the picture:

- An object used as a default is cloned on every parse (`Schema::_default` stores it, the parse clones it). Without that, a caller that changes the returned object would change the default of the next parse. An enum case is the exception, because it is a singleton and PHP forbids cloning it.
- `ObjectSchema::required` clones only the fields that are optional. The other fields enter the new shape as the same instance.

```php
use Esliph\Validator\V;

$schema = V::mixed()->_default(new stdClass());
$first = $schema->parse(null);
$first->touched = true;

echo isset($schema->parse(null)->touched) ? 'leaked' : 'clean', "\n"; // prints: clean
```

**Cost.** Each fluent call copies the arrays of the schema it starts from. A chain of N rules copies the rules collected so far on every call, so building a schema in a loop grows faster than linearly in the number of calls. `FluentCloneBench` measures it. The cost falls on the moment the schema is built, not on the parse. A schema is built once and parsed many times, so the cost sits where it hurts least.

**Alternative given up.** Mutable schemas whose fluent methods change the object they are called on. It saves the copies, but a schema that was handed to another piece of code can then change under it.

## A union reports one failure, not all of them

`UnionSchema` tries its members in order. The first member that passes wins, and its `ParseResult` is returned as it came. If every member fails, the union reports one of three things:

1. For a `null` input, a single `required` issue.
2. Otherwise, the result of the first member that is *dirty*: a member that recognized the type of the value and failed on a rule or on a child. That result is returned as it came.
3. Otherwise, a single `invalid_union` issue with the text "Invalid input", at the path of the union.

```php
use Esliph\Validator\V;

$dirty = V::union(V::int()->min(5), V::string())->safeParse(3);
$other = V::union(V::int(), V::string())->safeParse([]);

echo count($dirty->issues), "\n"; // prints: 1
echo $dirty->issues[0]->code, "\n"; // prints: too_small
echo $other->issues[0]->code, "\n"; // prints: invalid_union
```

The union never concatenates the issues of different members.

**Alternative given up: report every member.** A union of `string` and `int` that receives an array would then report "Expected string, received array" and "Expected int, received array" together. Both are true and the caller can fix neither one by itself: the value has to satisfy one of them, not both. The issues of members that did not recognize the type tell the caller nothing about the value. The issues of a dirty member do: they are the closest the value came to a match.

**Cost.** Every member that rejects the type still creates its issue, with the message and the params, before the union moves to the next member, and then throws it away. `UnionMemberFailureBench` measures that, with the default message, with a string message and with a closure message. `IssueAccumulationBench` shows that the union's own cost grows only with the number of members it walks, not with the issues it throws away.

**Known limitations.**

- "First dirty wins" can pick a member that is not the most relevant one. A wrong discriminant field, for example, makes the first object member dirty. There is no discriminated union, so a caller orders the members to get the report they want.
- With `strip`, an object member with fewer fields placed first wins and drops the keys of the larger members. Order object members from the largest to the smallest.
- A blank string (empty or whitespace only) is absent for a member that has `coerce()`, with the exception of `StringSchema`. Without `optional()`, the member gives `required`, which is not dirty, and the next member decides. A `V::string()` placed after it accepts the blank string.

```php
use Esliph\Validator\V;

$small = V::object(['id' => V::int()]);
$large = V::object(['id' => V::int(), 'name' => V::string()]);
$input = (object) ['id' => 1, 'name' => 'a'];

echo json_encode(V::union($small, $large)->parse($input)), "\n"; // prints: {"id":1}
echo json_encode(V::union($large, $small)->parse($input)), "\n"; // prints: {"id":1,"name":"a"}
echo json_encode(V::union(V::int()->coerce(), V::string())->parse('')), "\n"; // prints: ""
```

`UnionSchema` requires at least two members and throws `InvalidArgumentException` otherwise: a union of one member is the member itself.

## `strip` is the default for unknown keys

`ObjectSchema` ignores the keys that are not in its shape and leaves them out of the output. The four modes (`strip`, `strict`, `passthrough`, `catchall`) are described in [schemas.md](schemas.md). The default is the one that cannot leak input: the result holds the fields the schema declared and nothing else, so a caller that passes the result onward does not pass along keys it did not validate.

**Cost.** The object schema always reads the input with `get_object_vars`, and `strip` has to compare the keys with the shape. The other modes pay for the extra keys they copy, check or parse. `ObjectKeysBench` measures the default mode, `passthrough` and the case of an absent optional field.

**Alternative given up.** `passthrough` as the default, which is cheaper because it copies without checking, but returns data nobody validated. The default was `passthrough` in an earlier version and is `strip` now.

## Collecting every issue

The children of an object and the elements of an array are all parsed, even after one fails, and the rules of a schema all run, as long as its children passed. The caller gets the whole list in one parse. The composite schemas append the issues of each child with `array_push`, not `array_merge`: `array_merge` copies the whole accumulated list for every child and makes the total cost quadratic in the number of issues. `IssueAccumulationBench` measures it with arrays and objects of many failing children, and a twin that parses the same shape without failures to isolate the cost of accumulating.

## Related pages

- The steps that decide when each kind of failure happens: [parse-pipeline.md](parse-pipeline.md).
- How an issue is created: [errors.md](errors.md).
