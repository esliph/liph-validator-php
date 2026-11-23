# Test base classes

Every schema test class extends one of three abstract base classes in `tests/Schemas/`. The base class holds the tests that every schema of its kind must pass, and it asks the subclass for the few values it needs through abstract hooks. A new schema extends the matching base so that it inherits those tests. The steps to create the test class of a new schema are part of the recipe in [extending.md](extending.md).

## `BaseSchemaTestCase`

- **Class:** `BaseSchemaTestCase`
- **Extends:** PHPUnit's `TestCase`
- **Abstract hooks:** `createSchema`, `getValidValue`, `getInvalidValue`, `getDefaultValue`, `getTransformFunction`, `getRefineCheck`
- **Optional hooks:** `messageProbe` (a code the schema emits and an input that triggers it, for the `messages()` tests that do not depend on `required`) and `emitsInvalidType` (return `false` for schemas that never emit `invalid_type`)
- **Inherited tests:** required, optional, default, transform, refine and clone independence; `parse`, `safeParse` and `isValid` with valid and invalid values; the `messages()` group (custom string and closure, unknown code, immutability, the debug type of every input type)
- **Extend when:** the schema extends `Schema` and has no `coerce()`: `UnionSchema`, `EnumSchema`, `LiteralSchema`, `MixedSchema`, `NullSchema`

## `BaseCoercibleSchemaTestCase`

- **Class:** `BaseCoercibleSchemaTestCase`
- **Extends:** `BaseSchemaTestCase`
- **Abstract hooks:** `createSchemaCoercible`, which replaces `createSchema` (the base implements `createSchema` by calling it), plus the value hooks of `BaseSchemaTestCase`
- **Optional hooks:** `blankCoercesToNull` (return `false` when a blank string is a valid value, as in `StringSchema`)
- **Inherited tests:** the blank-string group with `coerce()`: a blank string is required, passes as `null` with `optional()`, takes the default, reaches a `required` closure unchanged, and is an `invalid_type` without `coerce()`
- **Extend when:** the schema extends `CoercibleSchema` and is not a number: `StringSchema`, `BooleanSchema`, `DateSchema`, `ObjectSchema`, `ArraySchema`

## `NumberSchemaTestCase`

- **Class:** `NumberSchemaTestCase`
- **Extends:** `BaseCoercibleSchemaTestCase`
- **Abstract hooks:** `createNumberSchema` and `expectedTypeLabel` (the type name as it appears in the message and in the `expected` param), plus `getValidValue` and `getInvalidValue`. The base already implements `createSchemaCoercible`, `getDefaultValue`, `getTransformFunction` and `getRefineCheck`.
- **Inherited tests:** the numeric rules (`min`, `max`, `gt`, `lt`, `between`, `positive`, `multipleOf` and the rest), the bound and divisor checks, `INF` and `NAN`, and the coercion of numeric strings
- **Extend when:** the schema is a `NumberSchema` or a subclass of it: `IntegerSchema`, `FloatSchema`, `NumberSchema`

## `BaseSchemaTestCase::provideAllInputTypes`

A static data provider with one case for every kind of PHP value: `null`, booleans, integers (including `PHP_INT_MAX`), floats (including `INF`, `-INF` and `NAN`), strings, arrays, `stdClass`, a closure and a resource. Each case is a pair of the value and the type name. A test uses it with the attribute `#[DataProvider('provideAllInputTypes')]`, and `BaseSchemaTestCase` uses it to check that the message of `invalid_type` receives the debug type of every input.

## Test names

A test is named `test_<method>_<Condition>_Should<Result>`: the method under test, the condition in CamelCase, and the expected result. An example is `test_min_WithValueGreaterThanMin_ShouldPass`.
