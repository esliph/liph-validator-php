# The schema hierarchy

The schema classes form a small tree. `Schema` owns the pipeline, and each subclass supplies the type check and, where needed, the hooks that run around it. This page explains what each level adds and the behavior that is easy to miss. The order of the steps that use these hooks is in [parse-pipeline.md](parse-pipeline.md).

```text
Schema (abstract)
├── CoercibleSchema (abstract): adds coerce()
│   ├── NumberSchema
│   │   ├── IntegerSchema
│   │   └── FloatSchema
│   ├── StringSchema
│   ├── BooleanSchema
│   ├── DateSchema
│   ├── ObjectSchema
│   └── ArraySchema
├── UnionSchema
├── MixedSchema
├── NullSchema
├── LiteralSchema
└── EnumSchema
```

## `Schema`

`Schema` is abstract. It holds the accumulated rules, transforms, the `optional` flag, the default and the message map. It implements `Schema::_parse`, the public `parse`, `safeParse` and `isValid`, and the fluent methods `refine`, `transform`, `optional`, `_default` and `messages`. A subclass implements `parseType` and nothing else is mandatory.

## `CoercibleSchema`

The `coerce` flag is stored in `Schema`, but only `CoercibleSchema` exposes `coerce()`. A class that should never coerce (`UnionSchema`, `EnumSchema`, `LiteralSchema`, `NullSchema`, `MixedSchema`) therefore has no way to turn it on.

A subclass overrides `coerceInput`. The contract of that method is strict:

- It receives only non-null values.
- It returns the converted value, `null` when the field is blank, or the original value unchanged when it cannot convert.
- It never throws and never creates an issue. A value that does not convert is left alone, and the type check reports it, with the original value in the message.

`CoercibleSchema` also brings two helpers. `isBlank` is true for a string that is empty or only whitespace. `decodeJson($value, $opening)` trims the string, decodes it only when the trimmed text starts with the expected opening character (`[` or `{`), and returns the original string when the JSON is invalid, so the type check can report it. JSON objects come out as `stdClass`, in `ObjectSchema` and in `ArraySchema`. The children of a decoded object do not need `coerce()` to accept those objects.

A blank value becomes `null` in `ObjectSchema`, `ArraySchema`, `BooleanSchema`, `DateSchema`, `NumberSchema`, `IntegerSchema` and `FloatSchema`. `StringSchema` does not do this: it only turns integers, finite floats and `Stringable` objects into strings, and an empty string stays an empty string.

## `Primitive/`

**`NumberSchema`** is the base of `IntegerSchema` and `FloatSchema`. It owns the numeric rules (`min`, `max`, `between` and the rest), the coercion from numeric strings, and the `not_finite` issue for `INF` and `NAN`. The subclasses override `coerceInput` and `parseType` to accept only integers or floats.

**`StringSchema`** has two kinds of fluent methods. The rules (`min`, `email`, `regex`...) are `Rule`s. `trim()`, `toLowerCase()` and `toUpperCase()` are normalizers: they are applied in registration order in `StringSchema::validateType`, before the rules, so the rules and their message closures see the normalized value, while the issues the schema itself creates quote the original input. The default is normalized too, because it goes through the same pipeline. With invalid UTF-8, the case normalizers return the string intact (the mbstring functions would replace the bad bytes). `min`, `max` and `length` then fail with their own codes, `lowercase` and `uppercase` fail with `invalid_case`, and a plain `V::string()` accepts the string. `uuid()` checks the 8-4-4-4-12 hexadecimal format, for any version and the nil UUID, and fails with `invalid_format`.

**`BooleanSchema`** and **`DateSchema`** follow the same pattern with their own vocabulary and parsing.

## `Complex/`

**`ObjectSchema`** parses a shape of named schemas. It has four modes for keys that are not in the shape, and the last method called wins:

| Mode | Behavior |
|---|---|
| `strip` (default) | extra keys are left out of the output |
| `strict` | one `unrecognized_keys` issue per extra key, at the path of the key |
| `passthrough` | extra keys are copied after the shape fields, in input order |
| `catchall` | each extra key goes through the schema given to `catchall()` |

The presence of a key is decided by `get_object_vars`. Only public, initialized properties count. A property that exists only through `__get` is not read. A `Traversable` object, such as `ArrayObject`, is not iterated: its entries are neither fields nor unknown keys, so `strict` does not reject them. An uninitialized typed property counts as missing.

A shape field enters the output when its key existed in the input or when the child produced a value that is not `null`. A field that is absent and `optional()`, `V::null()` or a nullable union is therefore left out of the output. The `refine` and `transform` of the object receive that output, so they must read absent optional fields with `??`.

**`ArraySchema`** accepts a list, not an associative array, and parses each element with the element schema when there is one.

**`UnionSchema`** accepts a value if any of its members does. `UnionSchema::acceptsNull` is true: a `null` is handed to the members, and each member decides with its own `optional()`, default or `V::null()`. The rules for which failure it reports are in [decisions.md](decisions.md).

## Schemas at the top level

- **`MixedSchema`** accepts any value.
- **`NullSchema`** accepts only `null`. `acceptsNull` is true.
- **`LiteralSchema`** accepts only the given value, compared with `===`. It accepts `null` when the literal is `null`.
- **`EnumSchema`** accepts a list of scalars or a `BackedEnum` class. The comparison is strict, as with `===`; the class implements it with lookups by type instead of one `in_array`. With an enum class, it checks the backing type of the input before calling `tryFrom` and returns the case. An instance of the enum is also accepted.

## Related pages

- How the issues of each class are built: [errors.md](errors.md).
- How to add a new class to the tree: [extending.md](extending.md).
