<?php

namespace Esliph\Validator\Tests\Schemas\Primitive;

use Override;

use Esliph\Validator\Schemas\Primitive\FloatSchema;
use Esliph\Validator\Schemas\Primitive\NumberSchema;

class FloatSchemaTest extends NumberSchemaTestCase {

  #[Override]
  protected function createNumberSchema(): NumberSchema {
    return new FloatSchema();
  }

  #[Override]
  protected function expectedTypeLabel(): string {
    return 'float';
  }

  #[Override]
  protected function getValidValue(): mixed {
    return 3.14;
  }

  public function test_parseType_WithString_ShouldDescribeExpectedFloat(): void {
    $result = (new FloatSchema())->safeParse('abc');

    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected float, received string', $result->issues[0]->message);
  }

  #[Override]
  protected function getInvalidValue(): mixed {
    return 'not a number';
  }

  public function test_parseType_WithValidFloat_ShouldAccept(): void {
    $result = (new FloatSchema())->safeParse(3.14);

    $this->assertTrue($result->success);
    $this->assertSame(3.14, $result->data);
    $this->assertIsFloat($result->data);
  }

  public function test_parseType_WithValidInteger_ShouldAccept(): void {
    $result = (new FloatSchema())->safeParse(42);

    $this->assertTrue($result->success);
    $this->assertIsFloat($result->data);
  }

  public function test_parseType_WithZero_ShouldAccept(): void {
    $result = (new FloatSchema())->safeParse(0);

    $this->assertTrue($result->success);
    $this->assertSame(0.0, $result->data);
  }

  public function test_parseType_WithFloatZero_ShouldAccept(): void {
    $result = (new FloatSchema())->safeParse(0.0);

    $this->assertTrue($result->success);
    $this->assertIsFloat($result->data);
  }

  public function test_parseType_WithNegativeFloat_ShouldAccept(): void {
    $result = (new FloatSchema())->safeParse(-3.14);

    $this->assertTrue($result->success);
    $this->assertSame(-3.14, $result->data);
  }

  public function test_parseType_WithNegativeInteger_ShouldAccept(): void {
    $result = (new FloatSchema())->safeParse(-42);

    $this->assertTrue($result->success);
    $this->assertIsFloat($result->data);
  }

  public function test_parseType_WithInfinity_ShouldReject(): void {
    $result = (new FloatSchema())->safeParse(INF);

    $this->assertFalse($result->success);
    $this->assertSame('Expected finite number, received INF', $result->issues[0]->message);
  }

  public function test_parseType_WithNegativeInfinity_ShouldReject(): void {
    $result = (new FloatSchema())->safeParse(-INF);

    $this->assertFalse($result->success);
    $this->assertSame('Expected finite number, received -INF', $result->issues[0]->message);
  }

  public function test_parseType_WithNaN_ShouldReject(): void {
    $result = (new FloatSchema())->safeParse(NAN);

    $this->assertFalse($result->success);
    $this->assertSame('Expected finite number, received NAN', $result->issues[0]->message);
  }

  public function test_parseType_WithString_ShouldReject(): void {
    $result = (new FloatSchema())->safeParse('not a number');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithArray_ShouldReject(): void {
    $result = (new FloatSchema())->safeParse([3.14]);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithObject_ShouldReject(): void {
    $result = (new FloatSchema())->safeParse(new \stdClass());

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithFloatString_ShouldCoerceToFloat(): void {
    $schema = (new FloatSchema())->coerce();

    $result = $schema->safeParse('3.14');

    $this->assertTrue($result->success);
    $this->assertSame(3.14, $result->data);
    $this->assertIsFloat($result->data);
  }

  public function test_coerce_WithIntegerString_ShouldCoerceToInt(): void {
    $schema = (new FloatSchema())->coerce();

    $result = $schema->safeParse('42');

    $this->assertTrue($result->success);
    $this->assertIsFloat($result->data);
  }

  public function test_coerce_WithZeroString_ShouldCoerce(): void {
    $schema = (new FloatSchema())->coerce();

    $result = $schema->safeParse('0');

    $this->assertTrue($result->success);
    $this->assertSame(0.0, $result->data);
  }

  public function test_coerce_WithBooleanTrue_ShouldFail(): void {
    $schema = (new FloatSchema())->coerce();

    $result = $schema->safeParse(true);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithBooleanFalse_ShouldFail(): void {
    $schema = (new FloatSchema())->coerce();

    $result = $schema->safeParse(false);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithIntegerValue_ShouldAccept(): void {
    $schema = (new FloatSchema())->coerce();

    $result = $schema->safeParse(42);

    $this->assertTrue($result->success);
    $this->assertIsFloat($result->data);
  }

  public function test_coerce_WithNonNumericString_ShouldFail(): void {
    $schema = (new FloatSchema())->coerce();

    $result = $schema->safeParse('abc');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithExponentialString_ShouldCoerce(): void {
    $schema = (new FloatSchema())->coerce();

    $result = $schema->safeParse('1e3');

    $this->assertTrue($result->success);
    $this->assertTrue(is_float($result->data) || is_int($result->data));
  }

  public function test_coerce_WithNegativeFloatString_ShouldCoerce(): void {
    $schema = (new FloatSchema())->coerce();

    $result = $schema->safeParse('-3.14');

    $this->assertTrue($result->success);
    $this->assertSame(-3.14, $result->data);
  }

  public function test_boundary_WithVerySmallPositiveFloat_ShouldAccept(): void {
    $result = (new FloatSchema())->safeParse(1e-300);

    $this->assertTrue($result->success);
    $this->assertIsFloat($result->data);
  }

  public function test_boundary_WithVeryLargeFloat_ShouldAccept(): void {
    $result = (new FloatSchema())->safeParse(1e300);

    $this->assertTrue($result->success);
    $this->assertIsFloat($result->data);
  }

  public function test_boundary_WithNegativeZeroFloat_ShouldAccept(): void {
    $result = (new FloatSchema())->safeParse(-0.0);

    $this->assertTrue($result->success);
  }

  public function test_boundary_WithFloatPrecisionEdgeCases_ShouldAccept(): void {
    $result = (new FloatSchema())->safeParse(0.1 + 0.2);

    $this->assertTrue($result->success);
    $this->assertIsFloat($result->data);
  }

  public function test_boundary_WithPHPFloatMax_ShouldAccept(): void {
    $result = (new FloatSchema())->safeParse(PHP_FLOAT_MAX);

    $this->assertTrue($result->success);
    $this->assertIsFloat($result->data);
  }

  public function test_boundary_WithPHPFloatMin_ShouldAccept(): void {
    $result = (new FloatSchema())->safeParse(PHP_FLOAT_MIN);

    $this->assertTrue($result->success);
    $this->assertIsFloat($result->data);
  }

  public function test_parseType_OnlyFloatAccepts_VerySmallNumber(): void {
    $result = (new FloatSchema())->safeParse(1e-308);

    $this->assertTrue($result->success);
  }

  public function test_rules_WithMinimumOnFloatSchema_ShouldEnforceCorrectly(): void {
    $schema = (new FloatSchema())->min(3.5);

    $this->assertTrue($schema->safeParse(3.5)->success);
    $this->assertTrue($schema->safeParse(3.6)->success);
    $this->assertTrue($schema->safeParse(4)->success);
    $this->assertFalse($schema->safeParse(3.4)->success);
  }

  public function test_rules_WithBetweenOnFloatSchema_ShouldEnforceCorrectly(): void {
    $schema = (new FloatSchema())->between(1.5, 2.5);

    $this->assertTrue($schema->safeParse(1.5)->success);
    $this->assertTrue($schema->safeParse(2.0)->success);
    $this->assertTrue($schema->safeParse(2.5)->success);
    $this->assertFalse($schema->safeParse(1.4)->success);
    $this->assertFalse($schema->safeParse(2.6)->success);
  }

  public function test_rules_WithMultipleOfOnFloatSchema_ShouldEnforceCorrectly(): void {
    $schema = (new FloatSchema())->multipleOf(0.5);

    $this->assertTrue($schema->safeParse(0.5)->success);
    $this->assertTrue($schema->safeParse(1.0)->success);
    $this->assertTrue($schema->safeParse(1.5)->success);
    $this->assertTrue($schema->safeParse(2.0)->success);
  }

  public function test_coerce_WithScientificNotation_ShouldCoerce(): void {
    $schema = (new FloatSchema())->coerce();

    $result = $schema->safeParse('1.5e2');

    $this->assertTrue($result->success);
  }
}
