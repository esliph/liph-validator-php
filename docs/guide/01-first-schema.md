# Validate your first object

In this tutorial you build the validation of a sign-up form: a name, an email and an optional age. You start with a single string and end with a schema that accepts good data, explains bad data and returns a clean object. Each step runs on its own, so you can copy any block into a file and run it.

## Before you start

You need PHP 8.3 or newer and Composer. In a project folder, install the library:

```sh
composer require esliph/validator
```

Put each example below in a PHP file that starts with `require 'vendor/autoload.php';` and run it with `php <file>`.

## 1. Validate a string

A schema describes what a valid value looks like. `V::string()` describes a string. `parse` checks a value against it and returns the value when it is valid.

```php
use Esliph\Validator\V;

$name = V::string()->parse('Ada');

echo $name, "\n"; // prints: Ada
```

## 2. Validate an object

`V::object` takes a map from each key to the schema of its value. The schema below describes a user with a `name` and an `email`. `parse` returns a new object that holds the validated fields.

```php
use Esliph\Validator\V;

$schema = V::object([
  'name' => V::string(),
  'email' => V::string(),
]);

$user = $schema->parse((object) ['name' => 'Ada', 'email' => 'ada@example.com']);

echo $user->name, "\n"; // prints: Ada
echo $user->email, "\n"; // prints: ada@example.com
```

The input here is an object. If your data arrives as an associative array, as in `$_POST`, you need `coerce()`. [04-coercion.md](04-coercion.md) shows it.

## 3. Add rules

A string is not yet a good name or a good email. You add rules by chaining methods on the schema of the field: `min(2)` requires at least two characters, and `email()` requires an email address.

```php
use Esliph\Validator\V;

$schema = V::object([
  'name' => V::string()->min(2),
  'email' => V::string()->email(),
]);

$user = $schema->parse((object) ['name' => 'Ada', 'email' => 'ada@example.com']);

echo $user->name, "\n"; // prints: Ada
```

The valid data still passes. The next step shows what happens to bad data.

## 4. See a failure

`parse` throws an exception when the value is not valid. When you want to look at the failure instead, use `safeParse`. It never throws for invalid data, and it returns a result with a `success` flag and a list of `issues`. Each issue has a `path`, which tells you the field, and a `message`.

```php
use Esliph\Validator\V;

$schema = V::object([
  'name' => V::string()->min(2),
  'email' => V::string()->email(),
]);

$result = $schema->safeParse((object) ['name' => 'A', 'email' => 'ada@example.com']);

echo $result->success ? 'valid' : 'invalid', "\n"; // prints: invalid
echo $result->issues[0]->pathString(), "\n"; // prints: name
echo $result->issues[0]->message, "\n"; // prints: Must be at least 2 characters
```

## 5. Add an optional field

Every field is required until you say otherwise. Add an `age` that is optional, and that must be at least 18 when it is present. A field that is missing from the input is also missing from the result.

```php
use Esliph\Validator\V;

$schema = V::object([
  'name' => V::string()->min(2),
  'email' => V::string()->email(),
  'age' => V::int()->min(18)->optional(),
]);

$without = $schema->parse((object) ['name' => 'Ada', 'email' => 'ada@example.com']);
$with = $schema->parse((object) ['name' => 'Ada', 'email' => 'ada@example.com', 'age' => 36]);

echo property_exists($without, 'age') ? 'has age' : 'no age', "\n"; // prints: no age
echo $with->age, "\n"; // prints: 36
```

## 6. Report every error

A form usually has more than one mistake. A schema collects all of them in one parse. When you use `parse`, the exception is a `ValidatorException`, and `getFlatMessages` gives one message per field, keyed by the field name.

```php
use Esliph\Validator\Errors\ValidatorException;
use Esliph\Validator\V;

$schema = V::object([
  'name' => V::string()->min(2),
  'email' => V::string()->email(),
  'age' => V::int()->min(18)->optional(),
]);

try {
  $schema->parse((object) ['name' => 'A', 'email' => 'not-an-email']);
} catch (ValidatorException $e) {
  echo json_encode($e->getFlatMessages()), "\n"; // prints: {"name":"Must be at least 2 characters","email":"Invalid email address"}
}
```

## What you built

You built a schema that accepts a valid sign-up, returns a clean object and lists every mistake by field name.

To go further:

- [02-primitives.md](02-primitives.md): the rules for strings, numbers, dates and more.
- [03-objects-arrays.md](03-objects-arrays.md): objects with extra keys, arrays and unions.
- [04-coercion.md](04-coercion.md): data from a form, a query string or JSON.
- [05-rules-transforms.md](05-rules-transforms.md): your own rules, transforms and defaults.
- [06-errors.md](06-errors.md): everything about errors.
- [reference.md](reference.md): every method and issue code.

To see why a parse runs its steps in the order it does, read [parse-pipeline.md](../architecture/parse-pipeline.md).
