# How to validate primitive values

This page shows how to validate a string, a number, a boolean, a date, a fixed value, a value from a set, `null` and any value. It assumes you finished [the first tutorial](01-first-schema.md). The examples use `parse`, which returns the value, `isValid`, which returns `true` or `false`, and `safeParse`, which returns the issues. For the full list of methods, see the [reference](reference.md).

## Check the length of a string

Use `min`, `max` or `length`. `nonempty` is the same as `min(1)`.

```php
use Esliph\Validator\V;

$schema = V::string()->min(3)->max(10);

echo json_encode($schema->isValid('abcd')), "\n"; // prints: true
echo json_encode($schema->isValid('ab')), "\n"; // prints: false
```

## Check the format of a string

Use `email`, `url`, `uuid` or `regex`. Each one fails with the code `invalid_format`.

```php
use Esliph\Validator\V;

$email = V::string()->email();
$uuid = V::string()->uuid();
$slug = V::string()->regex('/^[a-z0-9-]+$/');

echo json_encode($email->isValid('ada@example.com')), "\n"; // prints: true
echo json_encode($uuid->isValid('123e4567-e89b-12d3-a456-426614174000')), "\n"; // prints: true
echo json_encode($slug->isValid('Not A Slug')), "\n"; // prints: false
```

`uuid` accepts any version. `startsWith`, `endsWith` and `includes` check a part of the string in the same way.

## Clean a string before the rules run

`trim`, `toLowerCase` and `toUpperCase` change the string first. The rules then see the clean value. [05-rules-transforms.md](05-rules-transforms.md) explains the order.

```php
use Esliph\Validator\V;

$schema = V::string()->trim()->toLowerCase()->email();

echo $schema->parse('  Ada@Example.COM '), "\n"; // prints: ada@example.com
```

## Validate a number

Use `V::int()` for whole numbers, `V::float()` for decimals (it also accepts whole numbers) and `V::number()` for both. They share the same limits: `min`, `max`, `gt`, `gte`, `lt`, `lte`, `between`, `positive`, `negative`, `nonnegative`, `nonpositive` and `multipleOf`.

```php
use Esliph\Validator\V;

$quantity = V::int()->between(1, 10)->multipleOf(5);

echo json_encode($quantity->isValid(5)), "\n"; // prints: true
echo json_encode($quantity->isValid(7)), "\n"; // prints: false
echo $quantity->safeParse(15)->issues[0]->code, "\n"; // prints: too_big
```

`V::int()` rejects a float, and the message names both types:

```php
use Esliph\Validator\V;

echo V::int()->safeParse(1.5)->issues[0]->message, "\n"; // prints: Expected int, received float
```

`INF` and `NAN` are never valid numbers. They fail with the code `not_finite`.

```php
use Esliph\Validator\V;

echo V::float()->safeParse(INF)->issues[0]->code, "\n"; // prints: not_finite
```

## Validate a boolean

`V::boolean()` accepts only `true` and `false`. The string `'true'` is not a boolean, unless you call `coerce()` ([04-coercion.md](04-coercion.md)).

```php
use Esliph\Validator\V;

$schema = V::boolean();

echo json_encode($schema->isValid(true)), "\n"; // prints: true
echo json_encode($schema->isValid('true')), "\n"; // prints: false
```

## Validate a date

`V::date()` accepts a `DateTimeInterface` and returns a `DateTimeImmutable`. The limits `min` and `max` include the given date. `after` and `before` do not.

```php
use Esliph\Validator\V;

$schema = V::date()->min('2024-01-01');

$date = $schema->parse(new DateTimeImmutable('2024-03-01'));

echo $date->format('Y-m-d'), "\n"; // prints: 2024-03-01
echo $schema->safeParse(new DateTimeImmutable('2023-12-31'))->issues[0]->code, "\n"; // prints: too_early
```

To read a date from a string or a timestamp, add `coerce()`:

```php
use Esliph\Validator\V;

$schema = V::date()->coerce();

echo $schema->parse('2024-03-01')->format('Y-m-d'), "\n"; // prints: 2024-03-01
echo $schema->safeParse('not a date')->issues[0]->code, "\n"; // prints: invalid_date
```

`format` and `timezone` set how a string is read. [reference.md](reference.md) lists them.

## Accept only one value

`V::literal` accepts the given value and nothing else. The comparison is strict (`===`).

```php
use Esliph\Validator\V;

$schema = V::literal('admin');

echo $schema->parse('admin'), "\n"; // prints: admin
echo $schema->safeParse('user')->issues[0]->code, "\n"; // prints: invalid_literal
```

## Accept a value from a set

`V::enum` takes a list of values, or the name of a backed enum. With a list, it returns the value. With a backed enum, it returns the case.

```php
use Esliph\Validator\V;

enum Status: string {
  case Draft = 'draft';
  case Sent = 'sent';
}

$list = V::enum(['draft', 'sent']);
$cases = V::enum(Status::class);

echo $list->parse('draft'), "\n"; // prints: draft
echo $cases->parse('sent')->name, "\n"; // prints: Sent
echo $cases->safeParse('lost')->issues[0]->code, "\n"; // prints: invalid_enum_value
```

## Accept `null` or any value

`V::null()` accepts only `null`. `V::mixed()` accepts every value except `null`, which counts as missing. To let a field be missing or `null`, make it optional.

```php
use Esliph\Validator\V;

echo json_encode(V::null()->isValid(null)), "\n"; // prints: true
echo json_encode(V::mixed()->isValid([1, 2])), "\n"; // prints: true
echo json_encode(V::mixed()->isValid(null)), "\n"; // prints: false
```

To see how these schemas share code, read [schemas.md](../architecture/schemas.md).
