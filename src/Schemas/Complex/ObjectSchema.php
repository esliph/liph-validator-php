<?php

namespace Esliph\Validator\Schemas\Complex;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Errors\Issue;
use Esliph\Validator\Schemas\CoercibleSchema;
use Override;

final class ObjectSchema extends CoercibleSchema {

  protected bool $strict = false;
  protected ?Schema $catchall = null;

  /**
   * @param array<string, Schema> $shape
   */
  public function __construct(
    protected array $shape = []
  ) {
  }

  public function __clone(): void {
    parent::__clone();

    $this->shape = array_map(
      static fn(Schema $schema): Schema => clone $schema,
      $this->shape
    );

    if ($this->catchall !== null) {
      $this->catchall = clone $this->catchall;
    }
  }

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    if ($this->coerce && is_array($value)) {
      if (!$this->isAssociativeArray($value)) {
        return ParseResult::fail(
          new Issue(
            path: $path,
            message: 'Expected object or associative array, received indexed array',
            code: 'invalid_type'
          )
        );
      }

      return ParseResult::ok((object) $value);
    }

    if (!is_object($value)) {
      return ParseResult::fail(
        new Issue(
          path: $path,
          message: 'Expected object, received ' . gettype($value),
          code: 'invalid_type'
        )
      );
    }

    return ParseResult::ok($value);
  }

  #[Override]
  protected function validateType(mixed $value, array $path = []): ParseResult {
    $parsedValue = new \stdClass();
    $issues = [];

    foreach ($this->shape as $key => $schema) {
      $fieldPath = array_merge($path, [$key]);
      $fieldValue = $value->$key ?? null;

      $result = $schema->_parse($fieldValue, $fieldPath);

      if (!$result->success) {
        $issues = array_merge($issues, $result->issues);
      } else {
        $parsedValue->$key = $result->data;
      }
    }

    if ($this->strict) {
      foreach ($value as $key => $val) {
        if (!isset($this->shape[$key])) {
          $fieldPath = array_merge($path, [$key]);
          $issues[] = new Issue(
            path: $fieldPath,
            message: 'Unrecognized key in object',
            code: 'unrecognized_keys'
          );
        }
      }
    } else if ($this->catchall !== null) {
      foreach ($value as $key => $val) {
        if (!isset($this->shape[$key])) {
          $fieldPath = array_merge($path, [$key]);
          $result = $this->catchall->_parse($val, $fieldPath);

          if (!$result->success) {
            $issues = array_merge($issues, $result->issues);
          } else {
            $parsedValue->$key = $result->data;
          }
        }
      }
    } else {
      foreach ($value as $key => $val) {
        if (!isset($this->shape[$key])) {
          $parsedValue->$key = $val;
        }
      }
    }

    if (!empty($issues)) {
      return ParseResult::fails($issues);
    }

    return ParseResult::ok($parsedValue);
  }

  /**
   * @param array<string, Schema> $shape
   */
  public function shape(array $shape): static {
    $clone = clone $this;
    $clone->shape = $shape;

    return $clone;
  }

  /**
   * @param string[] $keys
   */
  public function pick(array $keys): static {
    $clone = clone $this;
    $newShape = [];

    foreach ($keys as $key) {
      if (isset($this->shape[$key])) {
        $newShape[$key] = $this->shape[$key];
      }
    }

    $clone->shape = $newShape;

    return $clone;
  }

  /**
   * @param string[] $keys
   */
  public function omit(array $keys): static {
    $clone = clone $this;
    $keysToOmit = array_flip($keys);
    $newShape = [];

    foreach ($this->shape as $key => $schema) {
      if (!isset($keysToOmit[$key])) {
        $newShape[$key] = $schema;
      }
    }

    $clone->shape = $newShape;

    return $clone;
  }

  /**
   * @param array<string, Schema> $shape
   */
  public function extend(array $shape): static {
    $clone = clone $this;
    $clone->shape = array_replace($this->shape, $shape);

    return $clone;
  }

  public function merge(self $other): static {
    return $this->extend($other->shape);
  }

  public function partial(): static {
    $clone = clone $this;
    $newShape = [];

    foreach ($this->shape as $key => $schema) {
      $newShape[$key] = $schema->optional();
    }

    $clone->shape = $newShape;

    return $clone;
  }

  public function required(): static {
    $clone = clone $this;
    $newShape = [];

    foreach ($this->shape as $key => $schema) {
      $newSchema = clone $schema;
      $newSchema->isOptional = false;
      $newShape[$key] = $newSchema;
    }

    $clone->shape = $newShape;

    return $clone;
  }

  public function strict(): static {
    $clone = clone $this;
    $clone->strict = true;
    $clone->catchall = null;

    return $clone;
  }

  public function passthrough(): static {
    $clone = clone $this;
    $clone->strict = false;
    $clone->catchall = null;

    return $clone;
  }

  public function catchall(Schema $schema): static {
    $clone = clone $this;
    $clone->strict = false;
    $clone->catchall = $schema;

    return $clone;
  }

  public function asArray(): static {
    return $this->transform(static fn(\stdClass $value): array => (array) $value);
  }
}
