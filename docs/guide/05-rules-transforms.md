# How to add rules, transforms and defaults

This page shows how to add your own rule, change a value after it passes, and fill a missing value. It assumes you finished [the first tutorial](01-first-schema.md). For the built-in rules, see the [reference](reference.md).

## Add your own rule

`refine` takes a function that returns `true` (or any truthy value) when the value is valid. A failure has the code `custom`. The second argument is the message.

```php
use Esliph\Validator\V;

$schema = V::int()->refine(fn(int $n): bool => $n % 2 === 0, 'Must be even');

$result = $schema->safeParse(3);

echo $result->issues[0]->message, "\n"; // prints: Must be even
echo $result->issues[0]->code, "\n"; // prints: custom
```

Without a message, the issue says `Invalid input`. A schema can have many rules. A parse runs all of them and reports every failure.

## Change a value after it passes

`transform` takes a function that receives the valid value and returns a new one. It runs only when the type and every rule passed. A failed parse never calls it.

```php
use Esliph\Validator\V;

$schema = V::string()->min(2)->transform(fn(string $s): string => strtoupper($s));

echo $schema->parse('ada'), "\n"; // prints: ADA
echo json_encode($schema->isValid('a')), "\n"; // prints: false
```

## Fill a missing value

`optional()` lets the value be missing. `_default` fills it. A missing value is `null`. In an object, it is also an absent key.

```php
use Esliph\Validator\V;

$optional = V::object(['nickname' => V::string()->optional()]);
$filled = V::object(['role' => V::string()->_default('member')]);

echo json_encode($optional->parse((object) [])), "\n"; // prints: {}
echo json_encode($filled->parse((object) [])), "\n"; // prints: {"role":"member"}
```

`_default` also takes a closure. The library calls it on each parse, so every result gets a fresh value.

```php
use Esliph\Validator\V;

$schema = V::object(['createdAt' => V::string()->_default(fn() => 'now')]);

echo $schema->parse((object) [])->createdAt, "\n"; // prints: now
```

## Clean a string before its rules

`trim`, `toLowerCase` and `toUpperCase` run in the order you call them, before every rule. The rules, and the messages that use the value, see the clean string. The issue that reports a wrong type shows the original input.

```php
use Esliph\Validator\V;

$schema = V::string()->trim()->min(3);

echo json_encode($schema->isValid('  ab  ')), "\n"; // prints: false
echo json_encode($schema->isValid('  abc  ')), "\n"; // prints: true
```

The default of the schema is cleaned in the same way.

## Write your own message

Every rule method takes the message as its last argument. It can be a string or a closure that receives the value and the parameters of the rule.

```php
use Esliph\Validator\V;

$fixed = V::string()->min(3, 'Too short');
$dynamic = V::string()->min(3, fn($value, array $params): string => "Needs {$params['length']} characters");

echo $fixed->safeParse('ab')->issues[0]->message, "\n"; // prints: Too short
echo $dynamic->safeParse('ab')->issues[0]->message, "\n"; // prints: Needs 3 characters
```

A rule is a method you chain to check the value, such as `min` or `email`. Its message goes in the method call, as above. `messages()` is for the codes the schema emits without any rule, such as `required` and `invalid_type`. [06-errors.md](06-errors.md) shows how.
