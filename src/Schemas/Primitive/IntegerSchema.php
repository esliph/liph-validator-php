<?php

namespace Esliph\Validator\Schemas\Primitive;

use Override;

use Esliph\Validator\Results\ParseResult;

class IntegerSchema extends NumberSchema {

  /** 2^63: o primeiro float acima de PHP_INT_MAX (que não é representável em float). */
  private const float INT_RANGE_END = 9.2233720368547758E18;

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    $input = $value;

    if ($this->coerce && is_string($value) && is_numeric($value)) {
      // String inteira que cabe vira int exato, sem passar por float.
      $number = $value + 0;
      $isIntegerStringOutOfRange = is_float($number) && strpbrk($value, '.eE') === false;

      if ($isIntegerStringOutOfRange) {
        return $this->outOfRangeIssue($input, $path);
      }

      $value = $number;
    }

    if (is_int($value)) {
      return ParseResult::ok($value);
    }

    if (is_float($value)) {
      return $this->parseFloat($value, $input, $path);
    }

    $received = self::describeType($input);

    return $this->invalidIntType("Expected int, received $received", $input, $path);
  }

  /**
   * @param string[] $path
   */
  private function parseFloat(float $value, mixed $input, array $path): ParseResult {
    if (!is_finite($value)) {
      return $this->nonFiniteIssue($value, $input, $path);
    }

    if (fmod($value, 1.0) !== 0.0) {
      return $this->invalidIntType('Expected int, received float with decimal part', $input, $path);
    }

    if ($value < (float) PHP_INT_MIN || $value >= self::INT_RANGE_END) {
      return $this->outOfRangeIssue($input, $path);
    }

    return ParseResult::ok((int) $value);
  }

  /**
   * @param string[] $path
   */
  private function outOfRangeIssue(mixed $input, array $path): ParseResult {
    return $this->invalidIntType('Expected int, received number outside the int range', $input, $path);
  }

  /**
   * @param string[] $path
   */
  private function invalidIntType(string $default, mixed $input, array $path): ParseResult {
    return ParseResult::fail(
      $this->issue('invalid_type', $default, $input, $path, [
        'expected' => 'int',
        'received' => self::describeType($input),
      ])
    );
  }
}
