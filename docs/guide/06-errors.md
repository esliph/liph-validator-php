# How to handle validation errors

This page shows how to choose how a parse reports a failure, how to read the issues, and how to change the messages. It assumes you finished [the first tutorial](01-first-schema.md). For the list of issue codes, see the [reference](reference.md).

## Choose between `parse`, `safeParse` and `isValid`

- `parse` returns the value, or throws a `ValidatorException`. Use it when invalid data is an exceptional case.
- `safeParse` returns a result and never throws for invalid data. The result has `success`, `data` and `issues`. Use it when you handle the failure in place.
- `isValid` returns `true` or `false`. Use it when you only need the answer.

```php
use Esliph\Validator\V;

$schema = V::int()->min(18);

echo json_encode($schema->isValid(20)), "\n"; // prints: true

$result = $schema->safeParse(10);

echo json_encode($result->success), "\n"; // prints: false
echo count($result->issues), "\n"; // prints: 1
```

## Read an issue

Each issue has four things: `path`, an array with the keys that lead to the value; `pathString()`, the same path joined with dots; `message`; and `code`. The path of a value at the root is empty.

```php
use Esliph\Validator\V;

$schema = V::object(['users' => V::_array(V::object(['age' => V::int()]))]);

$issue = $schema->safeParse((object) ['users' => [(object) ['age' => 'x']]])->issues[0];

echo json_encode($issue->path), "\n"; // prints: ["users",0,"age"]
echo $issue->pathString(), "\n"; // prints: users.0.age
echo $issue->code, "\n"; // prints: invalid_type
echo $issue->message, "\n"; // prints: Expected int, received string
```

## Group the errors of a form

`parse` throws a `ValidatorException` that holds every issue. These methods group them:

- `getIssues()` returns the list of `Issue` objects.
- `getFlatMessages()` returns one message for each path, the first one.
- `getMessagesByPath()` returns every message for each path.
- `toArray()` and `toJson()` return code, message and path of each issue, ready for an API response.

```php
use Esliph\Validator\Errors\ValidatorException;
use Esliph\Validator\V;

$schema = V::object([
  'name' => V::string()->min(2)->regex('/^[A-Z]/'),
  'email' => V::string()->email(),
]);

try {
  $schema->parse((object) ['name' => 'a', 'email' => 'x']);
} catch (ValidatorException $e) {
  echo json_encode($e->getMessagesByPath()), "\n"; // prints: {"name":["Must be at least 2 characters","Invalid format"],"email":["Invalid email address"]}
  echo $e->toJson(), "\n"; // prints: [{"path":["name"],"message":"Must be at least 2 characters","code":"too_small"},{"path":["name"],"message":"Invalid format","code":"invalid_format"},{"path":["email"],"message":"Invalid email address","code":"invalid_format"}]
}
```

## Change a message

For a rule, pass the message as the last argument of the method: `V::string()->min(3, 'Too short')`. [05-rules-transforms.md](05-rules-transforms.md) shows it.

For a code that the schema emits on its own, such as `required` or `invalid_type`, use `messages()`. It takes a map from code to a string or a closure. A closure receives the original value and the parameters, and its result becomes the message.

```php
use Esliph\Validator\V;

$schema = V::string()->messages([
  'required' => 'Name is required',
  'invalid_type' => fn($value): string => 'Name must be text, got ' . get_debug_type($value),
]);

echo $schema->safeParse(null)->issues[0]->message, "\n"; // prints: Name is required
echo $schema->safeParse(['a'])->issues[0]->message, "\n"; // prints: Name must be text, got array
```

`messages()` accepts only the codes that the schema itself emits. A code it does not know, such as `too_small` on a string schema, throws an `InvalidArgumentException` when you build the schema.

```php
use Esliph\Validator\V;

try {
  V::string()->messages(['too_small' => 'Too short']);
} catch (InvalidArgumentException $e) {
  echo 'InvalidArgumentException', "\n"; // prints: InvalidArgumentException
}
```

## Know which errors are not issues

Two kinds of failure are not validation issues:

- A wrong argument fails when you build the schema, not when you parse. `V::string()->min(-1)` and `V::union(V::string())` throw an `InvalidArgumentException`. Deriving an object schema after `refine` or `transform` throws a `LogicException`.
- An exception thrown by your own code, such as a `refine` function, a `transform`, a default closure or a message closure, is not caught. It leaves `parse`, `safeParse` and `isValid` as it is.
