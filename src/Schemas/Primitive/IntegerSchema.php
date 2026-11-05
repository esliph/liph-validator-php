<?php

namespace Esliph\Validator\Schemas\Primitive;

use Override;

use Esliph\Validator\Errors\Issue;
use Esliph\Validator\Results\ParseResult;

class IntegerSchema extends NumberSchema {

  /** 2^63: o primeiro float acima de PHP_INT_MAX (que não é representável em float). */
  private const float INT_RANGE_END = 9.2233720368547758E18;

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    if ($this->coerce && is_string($value) && is_numeric($value)) {
      // String inteira que cabe vira int exato, sem passar por float.
      $number = $value + 0;

      if (is_float($number) && strpbrk($value, '.eE') === false) {
        return $this->outOfRangeIssue($path);
      }

      $value = $number;
    }

    if (is_int($value)) {
      return ParseResult::ok($value);
    }

    if (is_float($value)) {
      return $this->parseFloat($value, $path);
    }

    return ParseResult::fail(
      new Issue(
        path: $path,
        message: 'Expected integer, received ' . gettype($value),
        code: 'invalid_type'
      )
    );
  }

  /**
   * @param string[] $path
   */
  private function parseFloat(float $value, array $path): ParseResult {
    if (!is_finite($value)) {
      return $this->nonFiniteIssue($value, $path);
    }

    if (fmod($value, 1.0) !== 0.0) {
      return ParseResult::fail(
        new Issue(
          path: $path,
          message: 'Expected integer, received float with decimal part',
          code: 'invalid_type'
        )
      );
    }

    if ($value < (float) PHP_INT_MIN || $value >= self::INT_RANGE_END) {
      return $this->outOfRangeIssue($path);
    }

    return ParseResult::ok((int) $value);
  }

  /**
   * @param string[] $path
   */
  private function outOfRangeIssue(array $path): ParseResult {
    return ParseResult::fail(
      new Issue(
        path: $path,
        message: 'Expected integer, received number outside the integer range',
        code: 'invalid_type'
      )
    );
  }
}
