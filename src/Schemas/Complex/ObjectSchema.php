<?php

namespace Esliph\Validator\Schemas\Complex;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Schemas\CoercibleSchema;
use InvalidArgumentException;
use LogicException;
use Override;

/**
 * The object's `refine`/`transform` receive the output `stdClass`: with defaults,
 * coercion and field transforms applied, without the keys dropped by
 * `strip` and without the absent optionals, which must be read with `??`.
 *
 * Fields are read through `get_object_vars`: only initialized public
 * properties count, and whatever exists only through `__get` is not read.
 * `Traversable` objects (`ArrayObject`...) are not iterated: the iterator's entries
 * count neither as fields nor as unknown keys, so `strict` does not
 * reject them and `passthrough` does not copy them.
 */
final class ObjectSchema extends CoercibleSchema {

  /** @var 'strip'|'strict'|'passthrough'|'catchall' */
  protected string $unknownKeys = 'strip';
  protected ?Schema $catchall = null;

  /**
   * @param array<string|int, Schema> $shape
   *
   * @throws InvalidArgumentException if any shape value is not a Schema
   */
  public function __construct(
    protected array $shape = []
  ) {
    self::assertShape($shape);
  }

  #[Override]
  protected static function messageCodes(): array {
    return [...parent::messageCodes(), 'unrecognized_keys'];
  }

  #[Override]
  protected function coerceInput(mixed $value): mixed {
    if (self::isBlank($value)) {
      return null;
    }

    if (is_string($value)) {
      return self::decodeJson($value, '{');
    }

    if (is_array($value) && $this->isAssociativeArray($value)) {
      return (object) $value;
    }

    return $value;
  }

  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    if ($this->coerce && is_array($value) && !$this->isAssociativeArray($value)) {
      return ParseResult::fail(
        $this->issue('invalid_type', 'Expected object or associative array, received list', $input, $path, [
          'expected' => 'object',
          'received' => 'list',
        ])
      );
    }

    if (!is_object($value)) {
      return ParseResult::fail($this->typeIssue('object', $input, $path));
    }

    return ParseResult::ok($value);
  }

  #[Override]
  protected function validateType(mixed $value, array $path = []): ParseResult {
    $props = get_object_vars($value);
    $parsedValue = new \stdClass();
    $issues = [];

    foreach ($this->shape as $key => $schema) {
      $exists = array_key_exists($key, $props);
      $result = $schema->_parse($exists ? $props[$key] : null, array_merge($path, [$key]));

      if (!$result->success) {
        // No array_merge: it copies the accumulated list for every child and becomes quadratic in the total number of issues.
        array_push($issues, ...$result->issues);
      } elseif ($exists || $result->data !== null) {
        $parsedValue->$key = $result->data;
      }
    }

    if ($this->unknownKeys !== 'strip') {
      foreach ($props as $key => $val) {
        if (isset($this->shape[$key])) {
          continue;
        }

        if ($this->unknownKeys === 'passthrough') {
          $parsedValue->$key = $val;
        } elseif ($this->unknownKeys === 'strict') {
          $fieldPath = array_merge($path, [$key]);
          $issues[] = $this->issue('unrecognized_keys', 'Unrecognized key in object', $val, $fieldPath, ['key' => $key]);
        } elseif ($this->catchall !== null) {
          $fieldPath = array_merge($path, [$key]);
          $result = $this->catchall->_parse($val, $fieldPath);

          if (!$result->success) {
            array_push($issues, ...$result->issues);
          } else {
            $parsedValue->$key = $result->data;
          }
        }
      }
    }

    if (!empty($issues)) {
      return ParseResult::fails($issues);
    }

    return ParseResult::ok($parsedValue);
  }

  /**
   * @return array<string|int, Schema>
   */
  public function getShape(): array {
    return $this->shape;
  }

  /**
   * @param array<string|int, Schema> $shape
   *
   * @throws InvalidArgumentException if any shape value is not a Schema
   * @throws LogicException after refine() or transform()
   */
  public function shape(array $shape): static {
    self::assertShape($shape);

    return $this->withShape($shape);
  }

  /**
   * Follows the shape's order, not that of `$keys`.
   *
   * @param string[] $keys
   *
   * @throws InvalidArgumentException if any key is not in the shape
   * @throws LogicException after refine() or transform()
   */
  public function pick(array $keys): static {
    $this->assertKnownKeys($keys);

    return $this->withShape(array_intersect_key($this->shape, array_flip($keys)));
  }

  /**
   * @param string[] $keys
   *
   * @throws InvalidArgumentException if any key is not in the shape
   * @throws LogicException after refine() or transform()
   */
  public function omit(array $keys): static {
    $this->assertKnownKeys($keys);

    return $this->withShape(array_diff_key($this->shape, array_flip($keys)));
  }

  /**
   * @param array<string|int, Schema> $shape
   *
   * @throws InvalidArgumentException if any shape value is not a Schema
   * @throws LogicException after refine() or transform()
   */
  public function extend(array $shape): static {
    self::assertShape($shape);

    return $this->withShape(array_replace($this->shape, $shape));
  }

  /**
   * @throws LogicException after refine() or transform(), or if `$other`
   * has refine() or transform(), which would be lost in the merge
   */
  public function merge(self $other): static {
    if ($other->hasRefineOrTransform()) {
      throw new LogicException('cannot merge an object schema that has refine() or transform()');
    }

    return $this->withShape(array_replace($this->shape, $other->shape));
  }

  /**
   * @throws LogicException after refine() or transform()
   */
  public function partial(): static {
    $this->assertDerivable();

    $newShape = [];

    foreach ($this->shape as $key => $schema) {
      $newShape[$key] = $schema->optional();
    }

    return $this->withShape($newShape);
  }

  /**
   * Shallow: only optional fields are cloned; the others enter the new shape
   * as the same instance.
   *
   * @throws LogicException after refine() or transform()
   */
  public function required(): static {
    $this->assertDerivable();

    $newShape = [];

    foreach ($this->shape as $key => $schema) {
      if ($schema->isOptional) {
        $schema = clone $schema;
        $schema->isOptional = false;
      }

      $newShape[$key] = $schema;
    }

    return $this->withShape($newShape);
  }

  public function strip(): static {
    $clone = clone $this;
    $clone->unknownKeys = 'strip';
    $clone->catchall = null;

    return $clone;
  }

  public function strict(): static {
    $clone = clone $this;
    $clone->unknownKeys = 'strict';
    $clone->catchall = null;

    return $clone;
  }

  public function passthrough(): static {
    $clone = clone $this;
    $clone->unknownKeys = 'passthrough';
    $clone->catchall = null;

    return $clone;
  }

  public function catchall(Schema $schema): static {
    $clone = clone $this;
    $clone->unknownKeys = 'catchall';
    $clone->catchall = $schema;

    return $clone;
  }

  /**
   * It is a transform: deriving the object after it (`pick`, `omit`, `extend`...)
   * throws LogicException. Call it last.
   */
  public function asArray(): static {
    return $this->transform(static fn(\stdClass $value): array => (array) $value);
  }

  /**
   * The object's rules and transforms were written for the old shape; that is why
   * deriving after refine() or transform() is refused.
   *
   * @param array<string|int, Schema> $shape
   *
   * @throws LogicException after refine() or transform()
   */
  private function withShape(array $shape): static {
    $this->assertDerivable();

    $clone = clone $this;
    $clone->shape = $shape;

    return $clone;
  }

  /**
   * @throws LogicException after refine() or transform()
   */
  private function assertDerivable(): void {
    if ($this->hasRefineOrTransform()) {
      throw new LogicException('cannot derive an object schema after refine() or transform()');
    }
  }

  private function hasRefineOrTransform(): bool {
    if ($this->transforms !== []) {
      return true;
    }

    foreach ($this->rules as $rule) {
      if ($rule->name === 'refinement') {
        return true;
      }
    }

    return false;
  }

  /**
   * @param array<mixed> $shape
   *
   * @throws InvalidArgumentException if any shape value is not a Schema
   */
  private static function assertShape(array $shape): void {
    foreach ($shape as $key => $schema) {
      if (!$schema instanceof Schema) {
        throw new InvalidArgumentException("object shape value for key '$key' must be a Schema");
      }
    }
  }

  /**
   * @param string[] $keys
   *
   * @throws InvalidArgumentException if any key is not in the shape
   */
  private function assertKnownKeys(array $keys): void {
    foreach ($keys as $key) {
      if (!array_key_exists($key, $this->shape)) {
        throw new InvalidArgumentException("unknown key '$key' in object shape");
      }
    }
  }
}
