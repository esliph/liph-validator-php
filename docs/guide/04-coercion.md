# How to validate form, query string and JSON input

This page shows how to validate data that arrives as text: a form, a query string or a JSON body. It assumes you finished [the first tutorial](01-first-schema.md). Text input needs `coerce()`, which converts a value to the type the schema expects before the schema checks it. `coerce()` exists on `V::int()`, `V::float()`, `V::number()`, `V::boolean()`, `V::date()`, `V::string()`, `V::object()` and `V::_array()`.

## Validate a form

A form such as `$_POST` is an associative array, and every value is a string. Call `coerce()` on the object, so it reads an array. Call it on each field that is not a string, so it reads a string.

```php
use Esliph\Validator\V;

$schema = V::object([
  'name' => V::string()->min(2),
  'age' => V::int()->coerce(),
  'newsletter' => V::boolean()->coerce(),
])->coerce();

$form = ['name' => 'Ada', 'age' => '36', 'newsletter' => 'true'];

$user = $schema->parse($form);

echo $user->age + 1, "\n"; // prints: 37
echo json_encode($user->newsletter), "\n"; // prints: true
```

## Treat a blank field as a missing value

A browser sends `''` for a field the user left empty. With `coerce()`, an empty or whitespace-only string counts as a missing value on a number, boolean, date, object or array schema. Then three things can happen:

- With `optional()`, the field passes. The key existed, so the result holds it with the value `null`.
- With `_default`, the field gets the default.
- With neither, the field fails with the code `required`.

```php
use Esliph\Validator\V;

$schema = V::object([
  'name' => V::string(),
  'age' => V::int()->coerce()->optional(),
  'seats' => V::int()->coerce()->_default(1),
])->coerce();

$user = $schema->parse(['name' => 'Ada', 'age' => '', 'seats' => '']);

echo json_encode($user), "\n"; // prints: {"name":"Ada","age":null,"seats":1}
```

This does not apply to `V::string()`: for a string schema, `''` is a valid string, and `V::string()->min(1)` is how you reject it.

```php
use Esliph\Validator\V;

$schema = V::object(['age' => V::int()->coerce()])->coerce();

echo $schema->safeParse(['age' => ''])->issues[0]->code, "\n"; // prints: required
```

## Validate a query string

Every value of a query string is text. `V::int()->coerce()` reads `'42'` as `42`, and `V::boolean()->coerce()` reads `'true'` and `'false'`. A text that is not a number stays as it is, and the schema reports `invalid_type`.

```php
use Esliph\Validator\V;

$schema = V::object([
  'page' => V::int()->coerce()->min(1)->_default(1),
  'active' => V::boolean()->coerce()->optional(),
])->coerce();

$query = $schema->parse(['page' => '3', 'active' => 'false']);

echo $query->page, "\n"; // prints: 3
echo json_encode($query->active), "\n"; // prints: false
echo $schema->safeParse(['page' => 'abc'])->issues[0]->code, "\n"; // prints: invalid_type
```

## Validate a JSON body

An object or an array schema with `coerce()` accepts a JSON string and decodes it. The decoded value then goes through the same checks as any other input. A JSON text that is not valid stays a string, and the schema reports `invalid_type`.

```php
use Esliph\Validator\V;

$schema = V::object([
  'name' => V::string(),
  'tags' => V::_array(V::string()),
])->coerce();

$body = '{"name": "Ada", "tags": ["math", "code"]}';

$data = $schema->parse($body);

echo $data->name, "\n"; // prints: Ada
echo count($data->tags), "\n"; // prints: 2
echo $schema->safeParse('{not json')->issues[0]->code, "\n"; // prints: invalid_type
```

The fields of a JSON object keep their JSON types, so they need no `coerce()`. The nested schemas read the decoded value as it is.

To get an array instead of an object, add `asArray()`:

```php
use Esliph\Validator\V;

$schema = V::object(['name' => V::string()])->coerce()->asArray();

$data = $schema->parse('{"name": "Ada"}');

echo $data['name'], "\n"; // prints: Ada
```

## Give a default the final type

A default skips coercion. With `coerce()`, it must already have the type the schema returns. A text default on a number fails:

```php
use Esliph\Validator\V;

echo V::int()->coerce()->safeParse(null)->issues[0]->code, "\n"; // prints: required
echo V::int()->coerce()->_default('5')->safeParse(null)->issues[0]->code, "\n"; // prints: invalid_type
echo V::int()->coerce()->_default(5)->parse(null), "\n"; // prints: 5
```

The same holds for a date (`_default('2024-01-15')` fails with `invalid_date`) and an object (`_default(['a' => 1])` fails, because the schema expects an object). To see why, read [parse-pipeline.md](../architecture/parse-pipeline.md).
