# Reference

Every schema factory of `V`, the methods of each schema and the issue codes they produce. Each section has the same columns: the method, its parameters, the issue code it can produce, and a one-line example.

Every rule method takes an extra optional last argument `$message`: a string or a closure that replaces the default message of that rule. It is left out of the tables. A method with no issue code in the table (`trim`, `optional`...) never fails by itself.

For instructions, see the [guide](README.md). For how the library works inside, see the [architecture](../architecture/README.md).

## Common to every schema

| Method | Parameters | Issue code | Example |
|---|---|---|---|
| `optional` | none | none | `V::string()->optional()` |
| `_default` | `$value`: a value or a closure that returns one | none | `V::int()->_default(0)` |
| `refine` | `$callable`: receives the value, returns a truthy value when valid | `custom` | `V::int()->refine(fn(int $n): bool => $n % 2 === 0)` |
| `transform` | `$callable`: receives the value, returns the new value | none | `V::string()->transform(fn(string $s): string => strtoupper($s))` |
| `messages` | `$messages`: a map from issue code to a string or a closure | none | `V::string()->messages(['required' => 'Name is required'])` |
| `apply` | `$callable`: receives the schema, returns a schema | none | `V::string()->apply(fn($schema) => $schema->min(1))` |
| `coerce` | none (only on the schemas that can coerce) | none | `V::int()->coerce()` |
| `parse` | `$value` | none (throws `ValidatorException`) | `V::string()->parse('a')` |
| `safeParse` | `$value` | none (returns a `ParseResult`) | `V::string()->safeParse('a')` |
| `isValid` | `$value` | none (returns a `bool`) | `V::string()->isValid('a')` |

## `V::string()`

| Method | Parameters | Issue code | Example |
|---|---|---|---|
| `V::string` | none | `invalid_type`, `required` | `V::string()` |
| `min` | `int $length` | `too_small` | `V::string()->min(2)` |
| `max` | `int $length` | `too_big` | `V::string()->max(10)` |
| `length` | `int $length` | `invalid_length` | `V::string()->length(5)` |
| `nonempty` | none | `too_small` | `V::string()->nonempty()` |
| `email` | none | `invalid_format` | `V::string()->email()` |
| `url` | none | `invalid_format` | `V::string()->url()` |
| `uuid` | none | `invalid_format` | `V::string()->uuid()` |
| `regex` | `string $pattern` | `invalid_format` | `V::string()->regex('/^[a-z]+$/')` |
| `startsWith` | `string $prefix` | `invalid_format` | `V::string()->startsWith('ab')` |
| `endsWith` | `string $suffix` | `invalid_format` | `V::string()->endsWith('yz')` |
| `includes` | `string $substring` | `invalid_format` | `V::string()->includes('mid')` |
| `lowercase` | none | `invalid_case` | `V::string()->lowercase()` |
| `uppercase` | none | `invalid_case` | `V::string()->uppercase()` |
| `trim` | none | none | `V::string()->trim()` |
| `toLowerCase` | none | none | `V::string()->toLowerCase()` |
| `toUpperCase` | none | none | `V::string()->toUpperCase()` |

## `V::int()`, `V::float()` and `V::number()`

`V::int()` accepts only integers, `V::float()` accepts floats and integers (it returns a float), and `V::number()` accepts both and keeps the type. The three have the same methods.

| Method | Parameters | Issue code | Example |
|---|---|---|---|
| `V::int` | none | `invalid_type`, `not_finite`, `required` | `V::int()` |
| `V::float` | none | `invalid_type`, `not_finite`, `required` | `V::float()` |
| `V::number` | none | `invalid_type`, `not_finite`, `required` | `V::number()` |
| `min` | `int or float $min` | `too_small` | `V::int()->min(1)` |
| `max` | `int or float $max` | `too_big` | `V::int()->max(10)` |
| `gt` | `int or float $min` | `too_small` | `V::int()->gt(0)` |
| `gte` | `int or float $min` | `too_small` | `V::int()->gte(0)` |
| `lt` | `int or float $max` | `too_big` | `V::int()->lt(100)` |
| `lte` | `int or float $max` | `too_big` | `V::int()->lte(100)` |
| `positive` | none | `too_small` | `V::int()->positive()` |
| `negative` | none | `too_big` | `V::int()->negative()` |
| `nonnegative` | none | `too_small` | `V::int()->nonnegative()` |
| `nonpositive` | none | `too_big` | `V::int()->nonpositive()` |
| `between` | `int or float $min`, `int or float $max` | `too_small`, `too_big` | `V::int()->between(1, 10)` |
| `multipleOf` | `int or float $divisor` | `not_multiple` | `V::int()->multipleOf(5)` |

## `V::boolean()`

| Method | Parameters | Issue code | Example |
|---|---|---|---|
| `V::boolean` | none | `invalid_type`, `required` | `V::boolean()` |

## `V::date()`

The schema accepts a `DateTimeInterface` and returns a `DateTimeImmutable`. With `coerce()` it also accepts a string and an integer timestamp.

| Method | Parameters | Issue code | Example |
|---|---|---|---|
| `V::date` | none | `invalid_type`, `invalid_date`, `required` | `V::date()` |
| `format` | `string $format`: the format to read strings with | none | `V::date()->coerce()->format('Y-m-d')` |
| `timezone` | `string or DateTimeZone $timezone` | none | `V::date()->coerce()->timezone('UTC')` |
| `min` | `DateTimeInterface, string or int $min`: inclusive | `too_early` | `V::date()->min('2024-01-01')` |
| `max` | `DateTimeInterface, string or int $max`: inclusive | `too_late` | `V::date()->max('2024-12-31')` |
| `after` | `DateTimeInterface, string or int $min`: exclusive | `too_early` | `V::date()->after('2024-01-01')` |
| `before` | `DateTimeInterface, string or int $max`: exclusive | `too_late` | `V::date()->before('2024-12-31')` |
| `between` | `$min`, `$max`: both inclusive | `out_of_range` | `V::date()->between('2024-01-01', '2024-12-31')` |

## `V::literal()`

| Method | Parameters | Issue code | Example |
|---|---|---|---|
| `V::literal` | `$value`: a string, int, float, bool, `null` or an enum case | `invalid_literal`, `required` | `V::literal('admin')` |

## `V::enum()`

In the example below, `Status` is a backed enum.

| Method | Parameters | Issue code | Example |
|---|---|---|---|
| `V::enum` | `$values`: a non-empty list of strings, ints, bools or finite floats | `invalid_enum_value`, `required` | `V::enum(['draft', 'sent'])` |
| `V::enum` | the class name of a backed enum | `invalid_enum_value`, `required` | `V::enum(Status::class)` |

## `V::null()` and `V::mixed()`

| Method | Parameters | Issue code | Example |
|---|---|---|---|
| `V::null` | none: accepts only `null` | `invalid_type` | `V::null()` |
| `V::mixed` | none: accepts any value except `null`, which counts as missing | `required` | `V::mixed()` |

## `V::object()`

| Method | Parameters | Issue code | Example |
|---|---|---|---|
| `V::object` | `array $shape`: a map from key to schema | `invalid_type`, `required` | `V::object(['name' => V::string()])` |
| `strip` | none: extra keys are dropped (the default) | none | `V::object(['a' => V::int()])->strip()` |
| `strict` | none: an extra key is an error | `unrecognized_keys` | `V::object(['a' => V::int()])->strict()` |
| `passthrough` | none: extra keys are copied | none | `V::object(['a' => V::int()])->passthrough()` |
| `catchall` | `Schema $schema`: parses every extra key | the codes of the given schema | `V::object(['a' => V::int()])->catchall(V::string())` |
| `pick` | `array $keys` | none | `V::object(['a' => V::int(), 'b' => V::int()])->pick(['a'])` |
| `omit` | `array $keys` | none | `V::object(['a' => V::int(), 'b' => V::int()])->omit(['b'])` |
| `extend` | `array $shape`: fields to add or replace | none | `V::object(['a' => V::int()])->extend(['b' => V::string()])` |
| `merge` | `ObjectSchema $other` | none | `V::object(['a' => V::int()])->merge(V::object(['b' => V::int()]))` |
| `shape` | `array $shape`: replaces the whole shape | none | `V::object(['a' => V::int()])->shape(['b' => V::int()])` |
| `partial` | none: every field becomes optional | none | `V::object(['a' => V::int()])->partial()` |
| `required` | none: every optional field becomes required | none | `V::object(['a' => V::int()->optional()])->required()` |
| `asArray` | none: the result is an array, not an object | none | `V::object(['a' => V::int()])->asArray()` |

## `V::_array()`

| Method | Parameters | Issue code | Example |
|---|---|---|---|
| `V::_array` | `Schema $schema`: the schema of each element (optional) | `invalid_type`, `required` | `V::_array(V::int())` |
| `of` | `Schema $schema` | none | `V::_array()->of(V::int())` |
| `min` | `int $length` | `too_small` | `V::_array(V::int())->min(1)` |
| `max` | `int $length` | `too_big` | `V::_array(V::int())->max(5)` |
| `length` | `int $length` | `invalid_length` | `V::_array(V::int())->length(3)` |
| `nonempty` | none | `too_small` | `V::_array(V::int())->nonempty()` |

## `V::union()`

| Method | Parameters | Issue code | Example |
|---|---|---|---|
| `V::union` | `Schema ...$schemas`: at least two | `invalid_union`, `required` | `V::union(V::string(), V::int())` |

## Issue codes

| Code | Emitted by | When |
|---|---|---|
| `required` | every schema that does not accept `null` | the value is `null` (or a blank string with `coerce()`), with no default and without `optional()` |
| `invalid_type` | most schemas | the value has the wrong type |
| `too_small` | `min`, `gt`, `gte`, `positive`, `nonnegative`, `between` (numbers), `nonempty` | the value, the length or the count is below the limit |
| `too_big` | `max`, `lt`, `lte`, `negative`, `nonpositive`, `between` (numbers) | the value, the length or the count is above the limit |
| `invalid_length` | `length` on strings and arrays | the length is not the exact number |
| `invalid_format` | `email`, `url`, `uuid`, `regex`, `startsWith`, `endsWith`, `includes` | the string does not match |
| `invalid_case` | `lowercase`, `uppercase` | the string has the wrong case |
| `not_multiple` | `multipleOf` | the number is not a multiple of the divisor |
| `not_finite` | number schemas | the value is `INF`, `-INF` or `NAN` |
| `invalid_date` | `V::date()` with `coerce()` | a string cannot be read as a date |
| `too_early` | `min`, `after` on dates | the date is before the limit |
| `too_late` | `max`, `before` on dates | the date is after the limit |
| `out_of_range` | `between` on dates | the date is outside the range |
| `invalid_literal` | `V::literal()` | the value is not the given one |
| `invalid_enum_value` | `V::enum()` | the value is not one of the options |
| `invalid_union` | `V::union()` | no member accepts the value and no member recognized the type |
| `unrecognized_keys` | `strict()` | the object has a key that is not in the shape |
| `custom` | `refine` | the function returned a falsy value |
