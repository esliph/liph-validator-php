# How errors are produced

Every failure the library reports is an `Issue`: a path, a message and a code. This page explains the classes that create and carry issues, the one method every schema uses to create them, and the reasons for the rules that keep the messages consistent. It also holds the rules for adding a new `Rule` or a new `Issue`. The steps for doing so are in [extending.md](extending.md).

## The pieces

**`Issue`** is a readonly value with three fields: `path` (the keys from the root to the failing value), `message` and `code`. `Issue::pathString` joins the path with dots.

**`ParseResult`** is the readonly, final value a parse returns. Its constructor is private. A result is created by `ParseResult::ok` (with the data), `ParseResult::fail` (one issue) or `ParseResult::fails` (a list of issues). A fourth constructor, `ParseResult::dirty`, and the `ParseResult::$dirty` flag are `@internal`: `dirty` is true when the failure happened after the type check, in a rule or in a child. `UnionSchema` uses it to choose which member to report ([decisions.md](decisions.md)).

**`ValidatorException`** carries the list of issues when `Schema::parse` fails. It groups them for display: `getMessagesByPath` returns every message per path, `getFlatMessages` the first message per path, and `getIssues`, `getFirstIssue`, `getIssuesByPath`, `getIssuesAt` and `hasIssueAt` give access to the issues themselves. `toArray` and `toJson` serialize them.

**`Rule`** is a readonly, final value with `name`, `code`, `check`, `message` and `params`. `check` is a `Closure(mixed $value, array $params): bool`. `message` is a string, a `Closure(mixed, array): string`, or `null`, which resolves to an empty string.

## Rules

A validation that a user can add to a schema (`min`, `email`, `between`) is a `Rule`, created by `Schema::addRule`. Three conventions apply:

- **The parameters go in `Rule::$params`, not in the closure.** The check receives them as its second argument, and so does a message closure. The closure stays `static`, and the same data reaches the check and the message.
- **The default message is a string built when the rule is created.** A `Closure` is used only when the text depends on the value being checked. The text is then built once, when the schema is written, and the success path of a parse never builds a message. The failure path pays for the message only when the rule fails; this cost is measured by `RuleFailureBench`.
- **A bad argument throws when the schema is built, never when it is used.** `InvalidArgumentException` covers values like a non-finite bound, a negative length, an invalid regex, an unknown key, `between` with a minimum above its maximum, or a shape value that is not a schema; `LogicException` covers an operation that is not allowed in the current state: deriving an object schema (`pick`, `omit`, `extend`, `merge`, `partial`, `required`, `shape`) after `refine` or `transform`, or merging an object schema that has one of them, which `merge` would drop without a sound. The library never creates a rule that always fails, because that would hide the mistake until a value reaches it.

Issues from rules are created in `Schema::validateRules`, from `Rule::$code` and the resolved message. They are the only issues that do not pass through `Schema::issue`.

## Issues from the schema itself

A schema reports a problem of its own (a missing value, a wrong type, an unknown key) through `Schema::issue($code, $default, $value, $path, $params)`. It is the one gate for the issues a schema creates. Its only companion is `typeIssue`, below, which builds the issue itself in two cases. That makes the message map (see "Messages" below) work for every code and keeps `path` and `code` in one shape.

`$value` is the original input of the parse, before coercion. In `parseType`, it is the `$input` parameter. A message closure then receives what the caller sent and not the converted value. For a schema without coercion the two are the same value.

### The "Expected X, received Y" pattern

A wrong type uses the code `invalid_type` and goes through `Schema::typeIssue($expected, $input, $path, $receivedText)`. It builds `Expected int, received string`: `received` is `get_debug_type($input)`, which gives `int`, `float`, `bool`, `null`, the class name of an object and `resource (stream)`. The same vocabulary is used on both sides, so an expected type and a received type can be compared by eye. `$receivedText` replaces only the received side of the default message (an array that is not a list is reported as an associative array).

When the schema has a custom message for `invalid_type`, `typeIssue` does the least work it can. A string message returns at once, without calling `get_debug_type` and without building the default text. With no custom message, it builds the default text and creates the issue itself. A closure message needs the default text too, so it is built first, and the closure gets `['expected' => ..., 'received' => ...]` through `Schema::issue`.

There is one `invalid_type` issue that does not use `typeIssue`: `ObjectSchema` with `coerce()` rejects a list with `Expected object or associative array, received list`, because its text names two accepted types and the pattern names one. It is created with `Schema::issue`, with the original input.

`Schema::formatValue` formats an expected value for a message: an enum case as `<Enum>::<Case>` and everything else with `var_export`, as in `Expected literal 'a'`.

## Messages

`Schema::messages` replaces the default message of any code the schema emits itself. A string is literal text. A `Closure` receives the original input and the params of the code, and its return is cast to `string`. The call is immutable and merges with earlier maps with `array_replace`.

The codes are checked when `messages()` is called, against `Schema::messageCodes`, which is static and lists only the codes the class can emit. A code outside that list throws `InvalidArgumentException`, so a typo fails at once, not silently never applying. A value that is neither a string nor a closure throws too. The messages of `Rule`s are not in this map: they are set with the last argument of each rule method.

| Class | `messageCodes()` |
|---|---|
| `Schema` (base) | `required`, `invalid_type` |
| `ObjectSchema` | base, plus `unrecognized_keys` |
| `NumberSchema` (and `IntegerSchema`, `FloatSchema`) | base, plus `not_finite` |
| `DateSchema` | base, plus `invalid_date` |
| `UnionSchema` | `required`, `invalid_union` |
| `EnumSchema` | `required`, `invalid_enum_value` |
| `LiteralSchema` | `required`, `invalid_literal` |
| `MixedSchema` | `required` |
| `NullSchema` | `invalid_type` |

In a union, `required` and `invalid_union` come from the union's own map. When the union reports a member's issue, the issue keeps the messages of that member.

## Codes

A code is a free string, not an enum. The list grows with the library, and a new class can name its own. Before inventing one, reuse an existing code. The command lists all of them:

```sh
grep -rhoE "(code: |issue\()'[a-z_]+'" src | grep -oE "'[a-z_]+'" | sort -u
```

A code names the kind of failure, not the schema that failed: `too_small` is used by the minimum of a string, of a number and of an array.

## What propagates

Only validation failures become issues. An exception thrown by a rule check, a message closure, a transform, a default closure or the `__toString` of a `Stringable` (called when `StringSchema` coerces) is a bug in the caller's code, so it propagates out of `parse`, `safeParse` and `isValid` untouched.

## Related pages

- Why the pipeline creates issues in the order it does: [parse-pipeline.md](parse-pipeline.md).
- How a union picks the issues it reports: [decisions.md](decisions.md).
