<?php

namespace Esliph\Validator\Tests\Schemas\Primitive;

use Override;

use Esliph\Validator\Schemas\Primitive\IntegerSchema;
use Esliph\Validator\Schemas\Primitive\NumberSchema;

class IntegerSchemaTest extends NumberSchemaTestCase {

  #[Override]
  protected function createNumberSchema(): NumberSchema {
    return new IntegerSchema();
  }

  #[Override]
  protected function getValidValue(): mixed {
    return 42;
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

  public function test_parseType_WithFloatWithoutDecimals_ShouldAccept(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(3.0);

    $this->assertTrue($result->success);
    $this->assertSame(3, $result->data);
    $this->assertIsInt($result->data);
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

  public function test_parseType_WithNegativeZeroFloat_ShouldAccept(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(-0.0);

    $this->assertTrue($result->success);
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

  public function test_coerce_WithNonDecimalFloatString_ShouldPass(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('5.0');

    $this->assertTrue($result->success);
    $this->assertSame(5, $result->data);
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

  public function test_coerce_WithFloatValue_ShouldConvertToInt(): void {
    $result = (new IntegerSchema())->coerce()->safeParse(42.0);

    $this->assertTrue($result->success);
    $this->assertSame(42, $result->data);
    $this->assertIsInt($result->data);
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

  public function test_boundary_BoundaryFloatEdgeCases_2_0_ShouldAccept(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(2.0);

    $this->assertTrue($result->success);
    $this->assertSame(2, $result->data);
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
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithNaN_ShouldReject(): void {
    $schema = new IntegerSchema();

    $result = (new IntegerSchema())->safeParse(NAN);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithExponentialString_ShouldPass(): void {
    $result = (new IntegerSchema())->coerce()->safeParse('1e3');

    $this->assertTrue($result->success);
    $this->assertSame(1000, $result->data);
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

  public function test_parseType_WithFloatEqualToIntMin_ShouldAccept(): void {
    $result = (new IntegerSchema())->safeParse((float) PHP_INT_MIN);

    $this->assertTrue($result->success);
    $this->assertSame(PHP_INT_MIN, $result->data);
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
  }
}
