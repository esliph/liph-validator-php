# How to validate objects, arrays and unions

This page shows how to handle extra keys, optional fields, derived schemas, arrays and unions. It assumes you finished [the first tutorial](01-first-schema.md). For the full list of methods, see the [reference](reference.md).

## Drop extra keys

By default an object schema drops the keys that are not in its shape. The method `strip` does the same, and you rarely call it.

```php
use Esliph\Validator\V;

$schema = V::object(['name' => V::string()]);

$user = $schema->parse((object) ['name' => 'Ada', 'role' => 'admin']);

echo json_encode($user), "\n"; // prints: {"name":"Ada"}
```

## Reject extra keys

Use `strict`. Each extra key produces one `unrecognized_keys` issue, and its path is the key.

```php
use Esliph\Validator\V;

$schema = V::object(['name' => V::string()])->strict();

$result = $schema->safeParse((object) ['name' => 'Ada', 'role' => 'admin', 'age' => 36]);

echo count($result->issues), "\n"; // prints: 2
echo $result->issues[0]->code, ' ', $result->issues[0]->pathString(), "\n"; // prints: unrecognized_keys role
```

## Keep extra keys

Use `passthrough` to copy the extra keys to the result, after the fields of the shape. Use `catchall` to validate each extra key with a schema.

```php
use Esliph\Validator\V;

$open = V::object(['name' => V::string()])->passthrough();
$typed = V::object(['name' => V::string()])->catchall(V::int());

echo json_encode($open->parse((object) ['name' => 'Ada', 'role' => 'admin'])), "\n"; // prints: {"name":"Ada","role":"admin"}
echo json_encode($typed->isValid((object) ['name' => 'Ada', 'score' => 'high'])), "\n"; // prints: false
```

The four modes exclude each other. When you call more than one, the last call wins.

## Make a field optional

An optional field that is missing from the input is also missing from the result. When you read the result inside `refine` or `transform` of the object, use `??`, because the key may not exist.

```php
use Esliph\Validator\V;

$schema = V::object([
  'name' => V::string(),
  'nickname' => V::string()->optional(),
])->transform(fn($user) => $user->nickname ?? $user->name);

echo $schema->parse((object) ['name' => 'Ada']), "\n"; // prints: Ada
echo $schema->parse((object) ['name' => 'Ada', 'nickname' => 'Countess']), "\n"; // prints: Countess
```

## Derive a schema from another

`pick` and `omit` choose fields. `extend` adds or replaces fields. `merge` joins two object schemas. `partial` makes every field optional, and `required` makes every optional field required again. Each method returns a new schema and leaves the original as it was.

```php
use Esliph\Validator\V;

$user = V::object([
  'name' => V::string(),
  'email' => V::string()->email(),
]);

$login = $user->pick(['email'])->extend(['password' => V::string()->min(8)]);
$patch = $user->partial();

echo json_encode(array_keys((array) $login->parse((object) ['email' => 'ada@example.com', 'password' => 'secret123']))), "\n"; // prints: ["email","password"]
echo json_encode($patch->isValid((object) [])), "\n"; // prints: true
```

You must derive before you add `refine` or `transform`. After them, `pick`, `omit`, `extend`, `merge`, `partial`, `required` and `shape` throw a `LogicException`, because the new shape could break the function you added.

```php
use Esliph\Validator\V;

$schema = V::object(['a' => V::int()])->refine(fn($value) => true);

try {
  $schema->pick(['a']);
} catch (LogicException $e) {
  echo 'LogicException', "\n"; // prints: LogicException
}
```

## Validate an array

`V::_array` takes the schema of each element. `min`, `max`, `length` and `nonempty` check the number of elements. The path of an issue holds the position of the element, and `pathString()` joins the parts with dots.

```php
use Esliph\Validator\V;

$schema = V::object(['tags' => V::_array(V::string())->min(1)]);

$result = $schema->safeParse((object) ['tags' => ['php', 5]]);

echo $result->issues[0]->pathString(), "\n"; // prints: tags.1
echo $result->issues[0]->message, "\n"; // prints: Expected string, received int
```

## Accept one of several schemas

`V::union` needs at least two schemas. The first one that accepts the value wins, so the order matters.

```php
use Esliph\Validator\V;

$schema = V::union(V::string(), V::int());

echo json_encode($schema->parse(5)), "\n"; // prints: 5
echo $schema->safeParse(true)->issues[0]->code, "\n"; // prints: invalid_union
```

When you join object schemas, `strip` drops the keys that the winning member does not know. A member with fewer fields that comes first wins and drops the keys of the larger ones. Put the object with the most fields first.

```php
use Esliph\Validator\V;

$small = V::object(['id' => V::int()]);
$large = V::object(['id' => V::int(), 'name' => V::string()]);

$input = (object) ['id' => 1, 'name' => 'Ada'];

echo json_encode(V::union($small, $large)->parse($input)), "\n"; // prints: {"id":1}
echo json_encode(V::union($large, $small)->parse($input)), "\n"; // prints: {"id":1,"name":"Ada"}
```

To see which issues a union reports when every member fails, and why, read [decisions.md](../architecture/decisions.md).
