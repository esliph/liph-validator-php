<?php

namespace Esliph\Validator\Tests\Schemas\Primitive;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;

use Esliph\Validator\V;
use Esliph\Validator\Schemas\Primitive\IntegerSchema;
use Esliph\Validator\Schemas\Primitive\NumberSchema;

class IntegerSchemaTest extends NumberSchemaTestCase {

  #[Override]
  protected function createNumberSchema(): NumberSchema {
    return new IntegerSchema();
  }

  #[Override]
  protected function expectedTypeLabel(): string {
    return 'int';
  }

  #[Override]
  protected function getValidValue(): mixed {
    return 42;
  }

  public function test_parseType_WithString_ShouldDescribeExpectedInt(): void {
    $result = (new IntegerSchema())->safeParse('abc');

    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected int, received string', $result->issues[0]->message);
  }

  public function test_coerce_WithDecimalString_ShouldReportStringAndPassOriginalToClosure(): void {
    $received = null;
    $schema = (new IntegerSchema())->coerce();
    $custom = $schema->messages([
      'invalid_type' => function (mixed $value, array $params) use (&$received): string {
        $received = [$value, $params];
        return 'custom';
      },
    ]);

    $default = $schema->safeParse('1.5');
    $custom->safeParse('1.5');

    $this->assertSame('Expected int, received string', $default->issues[0]->message);
    $this->assertSame(['1.5', ['expected' => 'int', 'received' => 'string']], $received);
  }

  public function test_coerce_WithIntegerStringOutOfRange_ShouldReportRangeAndPassOriginalToClosure(): void {
    $received = null;
    $schema = (new IntegerSchema())->coerce();
    $custom = $schema->messages([
      'invalid_type' => function (mixed $value, array $params) use (&$received): string {
        $received = [$value, $params];
        return 'custom';
      },
    ]);

    $default = $schema->safeParse('99999999999999999999');
    $custom->safeParse('99999999999999999999');

    $this->assertSame('Expected int, received number outside the int range', $default->issues[0]->message);
    $this->assertSame(['99999999999999999999', ['expected' => 'int', 'received' => 'string']], $received);
  }

  /** @return array<string, array{float}> */
  public static function provideFiniteFloats(): array {
    return [
      'whole' => [3.0], 'negative_zero' => [-0.0], 'decimal' => [1.5],
      'two_to_the_63' => [9.2233720368547758E18], 'int_min_as_float' => [(float) PHP_INT_MIN],
    ];
  }

  #[DataProvider('provideFiniteFloats')]
  public function test_parseType_WithFloat_ShouldFailInvalidType(float $input): void {
    $result = (new IntegerSchema())->safeParse($input);

    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected int, received float', $result->issues[0]->message);
  }

  #[DataProvider('provideFiniteFloats')]
  public function test_parseType_WithFloatAndCoerce_ShouldFailInvalidType(float $input): void {
    $result = (new IntegerSchema())->coerce()->safeParse($input);

    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected int, received float', $result->issues[0]->message);
  }

  public function test_parse_WithDecimalFloat_ShouldReportReceivedFloat(): void {
    $result = (new IntegerSchema())->safeParse(1.5);

    $this->assertSame('Expected int, received float', $result->issues[0]->message);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_messages_WithStringAndDecimalFloat_ShouldUseLiteral(): void {
    $result = (new IntegerSchema())->messages(['invalid_type' => 'Inteiro'])->safeParse(1.5);

    $this->assertSame('Inteiro', $result->issues[0]->message);
  }

  public function test_messages_WithClosureAndDecimalFloat_ShouldReceiveOriginalAndParams(): void {
    $received = null;
    $schema = (new IntegerSchema())->messages([
      'invalid_type' => function (mixed $value, array $params) use (&$received): string {
        $received = [$value, $params];
        return 'custom';
      },
    ]);

    $schema->safeParse(1.5);

    $this->assertSame([1.5, ['expected' => 'int', 'received' => 'float']], $received);
  }

  public function test_messages_FollowedByRuleAndCoerce_ShouldKeepCustomMessages(): void {
    $schema = (new IntegerSchema())->messages(['invalid_type' => 'X'])->min(1)->coerce();

    $this->assertSame('X', $schema->safeParse('abc')->issues[0]->message);
    $this->assertSame('Must be greater than or equal to 1', $schema->safeParse(0)->issues[0]->message);
  }

  #[Override]
  protected function getInvalidValue(): mixed {
    return 3.14;
  }

  public function test_parseType_WithValidInteger_ShouldAccept(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(42);

    $this->assertTrue($result->success);
    $this->assertSame(42, $result->data);
    $this->assertIsInt($result->data);
  }

  public function test_parseType_WithFloatWithDecimals_ShouldReject(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(3.14);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithFloatWithoutDecimals_ShouldFail(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(3.0);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithZero_ShouldAccept(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(0);

    $this->assertTrue($result->success);
    $this->assertSame(0, $result->data);
  }

  public function test_parseType_WithNegativeInteger_ShouldAccept(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(-42);

    $this->assertTrue($result->success);
    $this->assertSame(-42, $result->data);
  }

  public function test_parseType_WithNegativeFloatWithDecimals_ShouldReject(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(-3.14);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithLargeInteger_ShouldAccept(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(PHP_INT_MAX);

    $this->assertTrue($result->success);
    $this->assertSame(PHP_INT_MAX, $result->data);
  }

  public function test_parseType_WithNegativeZeroFloat_ShouldFail(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(-0.0);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithString_ShouldReject(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse('not a number');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithArray_ShouldReject(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse([42]);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithIntegerString_ShouldCoerceToInteger(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('42');

    $this->assertTrue($result->success);
    $this->assertSame(42, $result->data);
    $this->assertIsInt($result->data);
  }

  public function test_coerce_WithDecimalString_ShouldFail(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('3.14');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithNonDecimalFloatString_ShouldFail(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('5.0');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithZeroString_ShouldCoerceToZero(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('0');

    $this->assertTrue($result->success);
    $this->assertSame(0, $result->data);
  }

  public function test_coerce_WithNegativeIntegerString_ShouldCoerce(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('-42');

    $this->assertTrue($result->success);
    $this->assertSame(-42, $result->data);
  }

  public function test_coerce_WithBooleanTrue_ShouldFail(): void {
    $result = (new IntegerSchema())->coerce()->safeParse(true);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithBooleanFalse_ShouldFail(): void {
    $result = (new IntegerSchema())->coerce()->safeParse(false);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithFloatValue_ShouldFail(): void {
    $result = (new IntegerSchema())->coerce()->safeParse(42.0);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithFloatWithDecimals_ShouldFail(): void {
    $result = (new IntegerSchema())->coerce()->safeParse(3.14);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithNonNumericString_ShouldFail(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('abc');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_boundary_WithPHPIntMax_ShouldAccept(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(PHP_INT_MAX);

    $this->assertTrue($result->success);
    $this->assertSame(PHP_INT_MAX, $result->data);
  }

  public function test_boundary_WithPHPIntMin_ShouldAccept(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(PHP_INT_MIN);

    $this->assertTrue($result->success);
    $this->assertSame(PHP_INT_MIN, $result->data);
  }

  public function test_boundary_BoundaryFloatEdgeCases_2_0_ShouldFail(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(2.0);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_boundary_BoundaryFloatEdgeCases_2_000001_ShouldReject(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(2.000001);

    $this->assertFalse($result->success);
  }

  public function test_boundary_BoundaryFloatEdgeCases_9_99999_ShouldReject(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(9.99999);

    $this->assertFalse($result->success);
  }

  public function test_parseType_WithInfinity_ShouldReject(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(INF);

    $this->assertFalse($result->success);
    $this->assertSame('not_finite', $result->issues[0]->code);
  }

  public function test_parseType_WithNaN_ShouldReject(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(NAN);

    $this->assertFalse($result->success);
    $this->assertSame('not_finite', $result->issues[0]->code);
  }

  public function test_coerce_WithExponentialString_ShouldFail(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('1e3');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithFloatAboveIntRange_ShouldReject(): void {
    $result = (new IntegerSchema())->safeParse(1e20);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithFloatEqualToTwoToThe63_ShouldReject(): void {
    $result = (new IntegerSchema())->safeParse((float) PHP_INT_MAX);

    $this->assertFalse($result->success);
  }

  public function test_parseType_WithFloatEqualToIntMin_ShouldFail(): void {
    $result = (new IntegerSchema())->safeParse((float) PHP_INT_MIN);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithIntegerStringAboveIntMax_ShouldReject(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('9223372036854775808');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithIntegerStringBelowIntMin_ShouldReject(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('-9223372036854775809');

    $this->assertFalse($result->success);
  }

  public function test_coerce_WithIntMaxString_ShouldCoerceExactly(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('9223372036854775807');

    $this->assertTrue($result->success);
    $this->assertSame(PHP_INT_MAX, $result->data);
  }

  public function test_coerce_WithIntegerStringBeyondFloatPrecision_ShouldCoerceExactly(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('9007199254740993');

    $this->assertTrue($result->success);
    $this->assertSame(9007199254740993, $result->data);
  }

  public function test_coerce_WithExponentialStringAboveIntRange_ShouldReject(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('1e20');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected int, received string', $result->issues[0]->message);
  }

  /**
   * @return array<string, array{string, int}>
   */
  public static function provideIntGrammarStrings(): array {
    return [
      'spaces' => [' 1 ', 1],
      'plus_sign' => ['+1', 1],
      'leading_zeros' => ['007', 7],
      'negative_zero' => ['-0', 0],
      'positive_zero' => ['+0', 0],
      'int_max' => [(string) PHP_INT_MAX, PHP_INT_MAX],
      'int_min' => [(string) PHP_INT_MIN, PHP_INT_MIN],
      'int_max_with_leading_zeros' => ['0009223372036854775807', PHP_INT_MAX],
      'beyond_float_precision' => ['9007199254740993', 9007199254740993],
    ];
  }

  #[DataProvider('provideIntGrammarStrings')]
  public function test_coerce_WithIntGrammarString_ShouldCoerceExactly(string $input, int $expected): void {
    $result = (new IntegerSchema())->coerce()->safeParse($input);

    $this->assertTrue($result->success);
    $this->assertSame($expected, $result->data);
  }

  /**
   * @return array<string, array{string}>
   */
  public static function provideFloatGrammarStrings(): array {
    return [
      'decimal_zero' => ['1.0'],
      'exponent' => ['1e3'],
      'leading_dot' => ['.5'],
      'decimal' => ['1.5'],
    ];
  }

  #[DataProvider('provideFloatGrammarStrings')]
  public function test_coerce_WithFloatGrammarString_ShouldFailInvalidType(string $input): void {
    $result = (new IntegerSchema())->coerce()->safeParse($input);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected int, received string', $result->issues[0]->message);
  }

  /**
   * @return array<string, array{string}>
   */
  public static function provideOutOfRangeIntStrings(): array {
    return [
      'above_max' => ['9223372036854775808'],
      'below_min' => ['-9223372036854775809'],
      'twenty_nines' => ['99999999999999999999'],
      'spaces' => ['  9223372036854775808  '],
      'leading_zeros' => ['00009223372036854775808'],
    ];
  }

  #[DataProvider('provideOutOfRangeIntStrings')]
  public function test_coerce_WithOutOfRangeIntString_ShouldReportRangeWithOriginalInClosure(string $input): void {
    $received = null;
    $schema = (new IntegerSchema())->coerce();
    $custom = $schema->messages([
      'invalid_type' => function (mixed $value, array $params) use (&$received): string {
        $received = [$value, $params];
        return 'custom';
      },
    ]);

    $default = $schema->safeParse($input);
    $custom->safeParse($input);

    $this->assertSame('invalid_type', $default->issues[0]->code);
    $this->assertSame('Expected int, received number outside the int range', $default->issues[0]->message);
    $this->assertSame([$input, ['expected' => 'int', 'received' => 'string']], $received);
  }

  public function test_coerce_WithOverflowingExponentString_ShouldFailInvalidType(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('1e400');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_default_WithNumericStringAndCoerce_ShouldFailInvalidType(): void {
    $result = V::int()->coerce()->_default('5')->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected int, received string', $result->issues[0]->message);
  }
}
