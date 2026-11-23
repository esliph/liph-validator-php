# How to extend the library

This page has three recipes: add a rule to an existing schema, add an issue code, and create a new schema. It also lists the code conventions. The recipes assume that you read [overview.md](overview.md) and that the container works (`make test` passes). The reasons behind the steps are in [errors.md](errors.md), [schemas.md](schemas.md) and [parse-pipeline.md](parse-pipeline.md).

## Add a rule to an existing schema

You need a new check on a schema that exists, such as a method `hasDigit()` on `StringSchema`.

1. Add a public method to the schema class. It returns `static` and takes the message as its last argument, `string|Closure|null $message = null`.
2. Validate the arguments in the method. A bad argument throws `InvalidArgumentException` when the schema is built.
3. Return `$this->addRule(new Rule(...))` with named arguments. Create the `Rule` as described in the "Rules" section of [errors.md](errors.md): the parameters in `params`, a `static` closure, a default message built as a string.
4. Use an existing code if one fits. See "Codes" in [errors.md](errors.md).

```php
public function hasDigit(string|Closure|null $message = null): static {
  return $this->addRule(new Rule(
    name: 'hasDigit',
    code: 'invalid_format',
    check: static fn(string $value): bool => preg_match('/\d/', $value) === 1,
    message: $message ?? 'Must contain a digit'
  ));
}
```

5. Add tests to the test class of the schema, named as in [testing.md](testing.md): one with a value that passes, one with a value that fails and one with a custom message.
6. Add a row for the method in `docs/guide/reference.md` and a line under `## [Unreleased]` in `CHANGELOG.md`.

**Expected result:** `make test` and `make analyse` pass, and the new method appears in the reference.

## Add an issue code

You need a failure that no existing code describes.

1. Check that no existing code fits, with the command in "Codes" in [errors.md](errors.md). Reuse a code when you can.
2. Create the issue with `Schema::issue`, and for "Expected X, received Y" with `Schema::typeIssue`. Never create an `Issue` any other way in a schema. The rules are in [errors.md](errors.md).
3. Add the code to the `messageCodes()` of the class that emits it, so that `messages()` accepts it.
4. Add the code to the table "Issue codes" in `docs/guide/reference.md`.
5. Test it through a user message: override `messageProbe` in the test class with the code and an input that triggers it (see [testing.md](testing.md)).
6. Add a line to `CHANGELOG.md`.

**Expected result:** `messages(['<new_code>' => '...'])` changes the message, and a code that the class does not list is still refused.

## Create a new schema

You need a type that the library does not have, such as a hex color.

1. Choose the base. `Schema` for a type that never coerces. `CoercibleSchema` for a type that reads strings from forms or JSON. `NumberSchema` only for a numeric type. See [schemas.md](schemas.md).
2. Implement `parseType`. It checks only the type, and returns `ParseResult::ok` or a failure. Build the wrong-type issue with `Schema::typeIssue` and any other issue with `Schema::issue`, passing the original `$input`.
3. If the base is `CoercibleSchema`, override `coerceInput` as the contract in [schemas.md](schemas.md) says: it never throws and never creates an issue.
4. Override `messageCodes()` to list the codes the class emits, and mark every overridden method with `#[Override]`.
5. Add a factory method to `V`.
6. Create the test class on the base that fits, using the field "Extend when" in [testing.md](testing.md).
7. Add a section to `docs/guide/reference.md` and a line to `CHANGELOG.md`.

The class below follows steps 1 to 4 for a hex color. It runs as it is:

```php
use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Schemas\Schema;

final class HexColorSchema extends Schema {

  #[Override]
  protected static function messageCodes(): array {
    return [...parent::messageCodes(), 'invalid_format'];
  }

  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    if (!is_string($value)) {
      return ParseResult::fail($this->typeIssue('string', $input, $path));
    }

    if (preg_match('/^#[0-9a-f]{6}\z/i', $value) !== 1) {
      return ParseResult::fail($this->issue('invalid_format', 'Invalid hex color', $input, $path));
    }

    return ParseResult::ok($value);
  }
}

$schema = new HexColorSchema();

echo $schema->parse('#ff0000'), "\n"; // prints: #ff0000
echo $schema->safeParse('red')->issues[0]->message, "\n"; // prints: Invalid hex color
echo $schema->safeParse(5)->issues[0]->message, "\n"; // prints: Expected string, received int
```

**Expected result:** the three lines above, `make test` and `make analyse` green.

## Code conventions

- Indent with two spaces and put the opening brace on the same line.
- Leave a blank line after the opening of a class.
- Mark every overridden method with `#[Override]`.
- Call the constructors of `Issue` and `Rule` with named arguments.
- Write commit messages in English, in the format `type: Description`. The types are in [CONTRIBUTING.md](../../CONTRIBUTING.md).
