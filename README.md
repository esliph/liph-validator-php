# esliph/validator

[![CI](https://github.com/esliph/liph-validator-php/actions/workflows/ci.yml/badge.svg)](https://github.com/esliph/liph-validator-php/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/esliph/validator)](https://packagist.org/packages/esliph/validator)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.3-777bb4)](composer.json)
[![License](https://img.shields.io/github/license/esliph/liph-validator-php)](LICENSE)

A data validation library for PHP, inspired by [Zod](https://zod.dev). You describe the shape of your data once, and the schema validates it, converts it and tells you exactly what is wrong.

It needs PHP 8.3 or newer and has no runtime dependencies. The test suite runs on PHP 8.3, 8.4 and 8.5.

## Installation

```sh
composer require esliph/validator
```

## Quick example

```php
use Esliph\Validator\V;

$schema = V::object([
  'name' => V::string()->min(2),
  'email' => V::string()->email(),
  'age' => V::int()->min(18)->optional(),
]);

$user = $schema->parse((object) ['name' => 'Ana', 'email' => 'ana@example.com']);

echo json_encode($user), "\n"; // prints: {"name":"Ana","email":"ana@example.com"}
```

`parse()` returns the validated value, or throws a `ValidatorException` that lists every problem. Schemas are immutable: each method returns a new schema.

## Primitives

```php
use Esliph\Validator\V;

echo V::string()->trim()->toLowerCase()->parse('  HeLLo '), "\n"; // prints: hello
echo V::int()->between(1, 10)->parse(7), "\n"; // prints: 7
echo V::float()->positive()->parse(1.5), "\n"; // prints: 1.5
echo V::literal('admin')->parse('admin'), "\n"; // prints: admin
echo V::enum(['draft', 'published'])->parse('draft'), "\n"; // prints: draft
```

Available factories: `V::string()`, `V::int()`, `V::float()`, `V::number()`, `V::boolean()`, `V::date()`, `V::literal()`, `V::enum()`, `V::null()` and `V::mixed()`. String schemas have `min`, `max`, `length`, `email`, `url`, `uuid`, `regex` and more. Number schemas have `min`, `max`, `gt`, `lt`, `between`, `positive`, `multipleOf` and more. Each rule accepts a custom message as its last argument.

`trim()`, `toLowerCase()` and `toUpperCase()` normalize the string before the rules run, in the order you call them.

## Objects

```php
use Esliph\Validator\V;

$base = V::object(['id' => V::int(), 'name' => V::string()]);
$input = (object) ['id' => 1, 'name' => 'Ana', 'extra' => true];

echo json_encode($base->parse($input)), "\n"; // prints: {"id":1,"name":"Ana"}
echo json_encode($base->passthrough()->parse($input)), "\n"; // prints: {"id":1,"name":"Ana","extra":true}
echo $base->strict()->safeParse($input)->issues[0]->message, "\n"; // prints: Unrecognized key in object
echo json_encode($base->partial()->parse((object) ['id' => 1])), "\n"; // prints: {"id":1}
echo json_encode(V::object(['id' => V::int()])->coerce()->asArray()->parse('{"id": 7}')), "\n"; // prints: {"id":7}
```

By default an object schema drops unknown keys (`strip`). `strict()` reports them, `passthrough()` keeps them and `catchall($schema)` validates them. `pick`, `omit`, `extend`, `merge`, `partial` and `required` derive a new object schema.

The input of an object schema is an object. With `coerce()` it also accepts an associative array or a JSON string, and `asArray()` returns an array instead of a `stdClass`. Coercion is not inherited: each field converts its own value only if that field also calls `coerce()`.

## Arrays

```php
use Esliph\Validator\V;

echo json_encode(V::_array(V::int())->min(1)->parse([1, 2, 3])), "\n"; // prints: [1,2,3]

$result = V::_array(V::int())->safeParse([1, 'x', 3]);

echo $result->issues[0]->pathString(), ' ', $result->issues[0]->message, "\n"; // prints: 1 Expected int, received string
```

`V::_array()` is spelled with an underscore because `array` is a reserved word in PHP. The same goes for `_default()`. An array schema has `min`, `max`, `length` and `nonempty`.

## Unions

```php
use Esliph\Validator\V;

$id = V::union(V::string(), V::int());

echo $id->parse(5), "\n"; // prints: 5
echo $id->safeParse(true)->issues[0]->message, "\n"; // prints: Invalid input
```

The first option that accepts the value wins, so list the most specific options first.

## Coercion

```php
use Esliph\Validator\V;

echo var_export(V::int()->coerce()->parse('42'), true), "\n"; // prints: 42
echo var_export(V::boolean()->coerce()->parse('true'), true), "\n"; // prints: true
echo V::date()->coerce()->parse('2024-01-15')->format('Y-m-d'), "\n"; // prints: 2024-01-15
echo json_encode(V::_array(V::int())->coerce()->parse('[1,2]')), "\n"; // prints: [1,2]
```

`coerce()` converts strings that carry another type, such as form fields and query parameters, before validating. A value that cannot be converted fails with an `invalid_type` issue (`invalid_date` for dates).

## Custom rules and transforms

```php
use Esliph\Validator\V;

$palindrome = V::string()->refine(fn ($value) => $value === strrev($value), 'Not a palindrome');

echo $palindrome->parse('level'), "\n"; // prints: level
echo $palindrome->safeParse('abc')->issues[0]->message, "\n"; // prints: Not a palindrome
echo V::string()->transform(fn ($value) => strlen($value))->parse('abcd'), "\n"; // prints: 4
echo V::string()->_default('n/a')->parse(null), "\n"; // prints: n/a
```

`refine()` adds a rule with the code `custom`. `transform()` changes the value after every rule passed. `optional()` accepts `null`, and `_default()` replaces it.

## Errors

```php
use Esliph\Validator\Errors\ValidatorException;
use Esliph\Validator\V;

$user = V::object([
  'name' => V::string()->min(2),
  'tags' => V::_array(V::string()),
]);

try {
  $user->parse((object) ['name' => 'A', 'tags' => ['ok', 5]]);
} catch (ValidatorException $e) {
  echo json_encode($e->getFlatMessages()), "\n"; // prints: {"name":"Must be at least 2 characters","tags.1":"Expected string, received int"}
}

echo var_export($user->safeParse((object) ['name' => 'Ana', 'tags' => []])->success, true), "\n"; // prints: true
echo var_export($user->isValid((object) ['name' => 'A']), true), "\n"; // prints: false
echo V::string()->min(3, 'Too short')->safeParse('ab')->issues[0]->message, "\n"; // prints: Too short
echo V::string()->messages(['invalid_type' => 'Must be text'])->safeParse(5)->issues[0]->message, "\n"; // prints: Must be text
```

- `parse()` returns the value or throws `ValidatorException`. The exception also has `getMessagesByPath()`, `getIssues()`, `toArray()` and `toJson()`.
- `safeParse()` never throws for invalid data. It returns a `ParseResult` with `success`, `data` and `issues`.
- `isValid()` returns a `bool`.

Every `Issue` has a `path`, a `message` and a `code` such as `required`, `invalid_type`, `too_small`, `too_big`, `invalid_format`, `invalid_union` or `custom`. Exceptions thrown by your own `refine`, `transform` or message closures are not caught.

## Documentation

- [Guide](https://github.com/esliph/liph-validator-php/blob/master/docs/guide/README.md): a tutorial, how-to pages and a reference of every method and issue code.
- [Architecture](https://github.com/esliph/liph-validator-php/blob/master/docs/architecture/README.md): how the library is built and why, for contributors.

## Contributing

Pull requests are welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) first: it explains the branches, the commit format and how to run the checks. Please follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

[MIT](LICENSE)
