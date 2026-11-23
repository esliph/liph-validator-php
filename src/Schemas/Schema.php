<?php

namespace Esliph\Validator\Schemas;

use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Errors\Issue;
use Esliph\Validator\Errors\ValidatorException;
use Esliph\Validator\Validation\Rule;
use Closure;
use InvalidArgumentException;
use UnitEnum;

abstract class Schema {

  /** @var Rule[] */
  protected array $rules = [];
  /** @var Closure(mixed)[] */
  protected array $transforms = [];
  protected bool $isOptional = false;
  protected mixed $default = null;
  protected bool $hasDefault = false;
  protected bool $coerce = false;
  /** @var array<string, string|Closure> */
  protected array $messages = [];

  /**
   * @param string[] $path Keys from the root to the current value; becomes the `Issue::$path` of
   *                       every issue. `safeParse()` starts with `[]`, and each composite
   *                       schema adds the child's key when calling it.
   */
  protected function _parse(mixed $value, array $path = []): ParseResult {
    $input = $value;

    if ($this->coerce && $value !== null) {
      $value = $this->coerceInput($value);
    }

    if ($value === null) {
      if ($this->hasDefault) {
        $value = $input = $this->resolveDefault();
      } elseif ($this->isOptional) {
        return ParseResult::ok();
      } elseif (!$this->acceptsNull()) {
        return ParseResult::fail($this->issue('required', 'Value is required', $input, $path));
      }
    }

    $typeResult = $this->parseType($value, $path, $input);

    if (!$typeResult->success) {
      return $typeResult;
    }

    $childResult = $this->validateType($typeResult->data, $path);

    if (!$childResult->success) {
      return $childResult->dirty ? $childResult : ParseResult::dirty($childResult->issues);
    }

    $value = $childResult->data;
    $issues = $this->validateRules($value, $path);

    if (!empty($issues)) {
      return ParseResult::dirty($issues);
    }

    // The rules do not change the value: without a transform, validateType's ok is already the final result.
    if ($this->transforms === []) {
      return $childResult;
    }

    $value = $this->applyTransforms($value);

    return ParseResult::ok($value);
  }

  /**
   * Without a default or `optional`, `null` only goes on to `parseType` when the
   * schema accepts it as a value.
   */
  protected function acceptsNull(): bool {
    return false;
  }

  /**
   * Step 0 of `_parse`, only with `coerce()` and a non-null value. Returns the
   * converted value, `null` (blank field) or the original itself when it does not
   * convert. Never throws nor creates an issue.
   */
  protected function coerceInput(mixed $value): mixed {
    return $value;
  }

  /**
   * `$input` is the original value, before coercion; it is the one that goes into the issues.
   *
   * @param string[] $path
   */
  abstract protected function parseType(mixed $value, array $path, mixed $input): ParseResult;

  /**
   * Hook between the type check and the rules; its output feeds the rules.
   * Composites validate the children here, and String applies the normalizers.
   * A failure becomes `dirty`.
   *
   * @param string[] $path
   */
  protected function validateType(mixed $value, array $path = []): ParseResult {
    return ParseResult::ok($value);
  }

  /**
   * @param string[] $path
   * @return Issue[]
   */
  protected function validateRules(mixed $value, array $path = []): array {
    $issues = [];

    foreach ($this->rules as $rule) {
      if ($rule->validate($value) === false) {
        $issues[] = new Issue(
          path: $path,
          message: $rule->resolveMessage($value),
          code: $rule->code
        );
      }
    }

    return $issues;
  }

  /**
   * Only validation failures become ValidatorException. Exceptions thrown by a rule check,
   * message Closure, transform or default propagate untouched.
   *
   * @throws ValidatorException if the value fails validation
   */
  public function parse(mixed $value): mixed {
    $result = $this->safeParse($value);

    if (!$result->success) {
      throw new ValidatorException($result->issues);
    }

    return $result->data;
  }

  /**
   * Only validation failures become `false`. Exceptions thrown by a rule check,
   * message Closure, transform or default propagate untouched.
   */
  public function isValid(mixed $value): bool {
    return $this->safeParse($value)->success;
  }

  /**
   * Only validation failures become a ParseResult with issues. Exceptions thrown by
   * a rule check, message Closure, transform or default propagate
   * untouched.
   */
  public function safeParse(mixed $value): ParseResult {
    return $this->_parse($value, []);
  }

  public function apply(Closure $callable): static {
    return $callable($this);
  }

  /**
   * The check receives only the value, so it accepts native one-argument callables
   * (`is_numeric(...)`). The message Closure still receives `($value, [])`.
   *
   * @param Closure(mixed): mixed $callable
   */
  public function refine(Closure $callable, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'refinement',
      code: 'custom',
      check: static fn(mixed $value): bool => (bool) $callable($value),
      message: $message ?? 'Invalid value'
    ));
  }

  protected function addRule(Rule $rule): static {
    $clone = clone $this;
    $clone->rules[] = $rule;

    return $clone;
  }

  public function transform(Closure $callable): static {
    $clone = clone $this;
    $clone->transforms[] = $callable;

    return $clone;
  }

  public function _default(mixed $value): static {
    $clone = clone $this;
    $clone->default = $value;
    $clone->hasDefault = true;

    return $clone;
  }

  /**
   * An object default is cloned on every use so that a mutation made by whoever
   * receives the result does not leak into the next parse.
   */
  private function resolveDefault(): mixed {
    if ($this->default instanceof Closure) {
      return ($this->default)();
    }

    if (self::isCloneableDefault($this->default)) {
      return clone $this->default;
    }

    return $this->default;
  }

  /**
   * An enum case is a singleton and PHP forbids cloning it.
   *
   * @phpstan-assert-if-true object $value
   */
  private static function isCloneableDefault(mixed $value): bool {
    return is_object($value) && !$value instanceof UnitEnum;
  }

  protected function applyTransforms(mixed $value): mixed {
    foreach ($this->transforms as $fn) {
      $value = $fn($value);
    }

    return $value;
  }

  public function optional(): static {
    $clone = clone $this;
    $clone->isOptional = true;

    return $clone;
  }

  /**
   * Replaces, by code, the messages of the issues the schema emits itself. A
   * string is literal text; a Closure receives the original input value and the
   * code's params. The messages of `Rule`s are left out of this map.
   *
   * @param array<string, string|Closure(mixed, array<string, mixed>): string> $messages
   */
  public function messages(array $messages): static {
    foreach ($messages as $code => $message) {
      if (!in_array($code, static::messageCodes(), true)) {
        throw new InvalidArgumentException(
          "Unknown message code '$code'; allowed: " . implode(', ', static::messageCodes())
        );
      }

      if (!self::isMessage($message)) {
        throw new InvalidArgumentException("Message for code '$code' must be a string or Closure");
      }
    }

    $clone = clone $this;
    $clone->messages = array_replace($this->messages, $messages);

    return $clone;
  }

  /**
   * Takes `mixed` because the PHPDoc type of `messages()` does not hold at runtime
   * for callers without static analysis.
   */
  private static function isMessage(mixed $message): bool {
    return is_string($message) || $message instanceof Closure;
  }

  /**
   * Codes the class emits on its own. Each subclass extends or
   * redefines the list, which holds only what it can emit.
   *
   * @return list<string>
   */
  protected static function messageCodes(): array {
    return ['required', 'invalid_type'];
  }

  /**
   * Single point where schema issues are created. `$value` is the original input
   * value, before coercion.
   *
   * @param string[] $path
   * @param array<string, mixed> $params
   */
  protected function issue(string $code, string $default, mixed $value, array $path, array $params = []): Issue {
    $message = $this->messages[$code] ?? $default;

    if ($message instanceof Closure) {
      $message = (string) $message($value, $params);
    }

    return new Issue(path: $path, message: $message, code: $code);
  }

  /**
   * Mandatory gate for every `invalid_type` in the "Expected X, received Y" pattern.
   * `$input` is the original value, before coercion; `received` is
   * `get_debug_type($input)` (`int`, `float`, `bool`, `null`, class name,
   * `resource (stream)`), and `$receivedText` only replaces the default text.
   *
   * A custom string does not pay for `get_debug_type`, and without customization the
   * params are not built. The Closure is still resolved only in `issue()`.
   *
   * @param string[] $path
   */
  protected function typeIssue(string $expected, mixed $input, array $path, ?string $receivedText = null): Issue {
    $custom = $this->messages['invalid_type'] ?? null;

    if (is_string($custom)) {
      return new Issue(path: $path, message: $custom, code: 'invalid_type');
    }

    $received = get_debug_type($input);
    $default = "Expected $expected, received " . ($receivedText ?? $received);

    if ($custom === null) {
      return new Issue(path: $path, message: $default, code: 'invalid_type');
    }

    return $this->issue('invalid_type', $default, $input, $path, ['expected' => $expected, 'received' => $received]);
  }

  /**
   * @throws InvalidArgumentException if the count is negative
   */
  protected static function assertCount(int $n, string $method): void {
    if ($n < 0) {
      throw new InvalidArgumentException("$method length must be a non-negative int");
    }
  }

  /**
   * Representation of an expected value in messages: an enum case comes out as
   * `Class::Case`, and the rest as `var_export`.
   */
  protected static function formatValue(mixed $value): string {
    if ($value instanceof UnitEnum) {
      return $value::class . '::' . $value->name;
    }

    return var_export($value, true);
  }

  protected function isAssociativeArray(array $array): bool {
    return $array !== [] && !array_is_list($array);
  }
}
