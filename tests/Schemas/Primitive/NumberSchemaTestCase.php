<?php

namespace Esliph\Validator\Tests\Schemas\Primitive;

use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Schemas\Primitive\NumberSchema;

use Esliph\Validator\Tests\Schemas\BaseCoercibleSchemaTestCase;

use Closure;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

abstract class NumberSchemaTestCase extends BaseCoercibleSchemaTestCase {

  #[Override]
  protected function createSchemaCoercible(): CoercibleSchema {
    return $this->createNumberSchema();
  }

  abstract protected function createNumberSchema(): NumberSchema;

  #[Override]
  protected function getDefaultValue(): mixed {
    return 100;
  }

  #[Override]
  protected function getTransformFunction(): Closure {
    return fn(mixed $value) => $value;
  }

  #[Override]
  protected function getRefineCheck(): Closure {
    return fn(mixed $value, array $path): bool => $value > 0;
  }

  public function test_min_WithValueGreaterThanMin_ShouldPass(): void {
    $schema = $this->createNumberSchema()->min(10);

    $result = $schema->safeParse(15);

    $this->assertTrue($result->success);
  }

  public function test_min_WithValueEqualToMin_ShouldPass(): void {
    $schema = $this->createNumberSchema()->min(10);

    $result = $schema->safeParse(10);

    $this->assertTrue($result->success);
    $this->assertEquals(10, $result->data);
  }

  public function test_min_WithValueLessThanMin_ShouldFail(): void {
    $schema = $this->createNumberSchema()->min(10);

    $result = $schema->safeParse(5);

    $this->assertFalse($result->success);
    $this->assertSame('too_small', $result->issues[0]->code);
  }

  public function test_min_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'Value must be at least 10';
    $schema = $this->createNumberSchema()->min(10, $customMessage);

    $result = $schema->safeParse(5);

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_min_WithClosureMessage_ShouldUseDynamicMessage(): void {
    $schema = $this->createNumberSchema()->min(10, function (mixed $value, array $params): string {
      return "Value $value must be at least {$params['min']}";
    });

    $result = $schema->safeParse(5);

    $this->assertFalse($result->success);
    $this->assertStringContainsString('Value 5', $result->issues[0]->message);
  }

  public function test_max_WithValueLessThanMax_ShouldPass(): void {
    $schema = $this->createNumberSchema()->max(20);

    $result = $schema->safeParse(15);

    $this->assertTrue($result->success);
  }

  public function test_max_WithValueEqualToMax_ShouldPass(): void {
    $schema = $this->createNumberSchema()->max(20);

    $result = $schema->safeParse(20);

    $this->assertTrue($result->success);
    $this->assertEquals(20, $result->data);
  }

  public function test_max_WithValueGreaterThanMax_ShouldFail(): void {
    $schema = $this->createNumberSchema()->max(20);

    $result = $schema->safeParse(25);

    $this->assertFalse($result->success);
    $this->assertSame('too_big', $result->issues[0]->code);
  }

  public function test_max_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'Value must be at most 20';
    $schema = $this->createNumberSchema()->max(20, $customMessage);

    $result = $schema->safeParse(25);

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_gt_WithValueGreaterThanMin_ShouldPass(): void {
    $schema = $this->createNumberSchema()->gt(10);

    $result = $schema->safeParse(11);

    $this->assertTrue($result->success);
  }

  public function test_gt_WithValueEqualToMin_ShouldFail(): void {
    $schema = $this->createNumberSchema()->gt(10);

    $result = $schema->safeParse(10);

    $this->assertFalse($result->success);
    $this->assertSame('too_small', $result->issues[0]->code);
  }

  public function test_gt_WithValueLessThanMin_ShouldFail(): void {
    $schema = $this->createNumberSchema()->gt(10);

    $result = $schema->safeParse(9);

    $this->assertFalse($result->success);
  }

  public function test_gt_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'Value must be greater than 10';
    $schema = $this->createNumberSchema()->gt(10, $customMessage);

    $result = $schema->safeParse(5);

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_gte_WithValueGreaterThanMin_ShouldPass(): void {
    $schema = $this->createNumberSchema()->gte(10);

    $result = $schema->safeParse(11);

    $this->assertTrue($result->success);
  }

  public function test_gte_WithValueEqualToMin_ShouldPass(): void {
    $schema = $this->createNumberSchema()->gte(10);

    $result = $schema->safeParse(10);

    $this->assertTrue($result->success);
  }

  public function test_gte_WithValueLessThanMin_ShouldFail(): void {
    $schema = $this->createNumberSchema()->gte(10);

    $result = $schema->safeParse(9);

    $this->assertFalse($result->success);
  }

  public function test_lt_WithValueLessThanMax_ShouldPass(): void {
    $schema = $this->createNumberSchema()->lt(20);

    $result = $schema->safeParse(15);

    $this->assertTrue($result->success);
  }

  public function test_lt_WithValueEqualToMax_ShouldFail(): void {
    $schema = $this->createNumberSchema()->lt(20);

    $result = $schema->safeParse(20);

    $this->assertFalse($result->success);
    $this->assertSame('too_big', $result->issues[0]->code);
  }

  public function test_lt_WithValueGreaterThanMax_ShouldFail(): void {
    $schema = $this->createNumberSchema()->lt(20);

    $result = $schema->safeParse(25);

    $this->assertFalse($result->success);
  }

  public function test_lte_WithValueLessThanMax_ShouldPass(): void {
    $schema = $this->createNumberSchema()->lte(20);

    $result = $schema->safeParse(15);

    $this->assertTrue($result->success);
  }

  public function test_lte_WithValueEqualToMax_ShouldPass(): void {
    $schema = $this->createNumberSchema()->lte(20);

    $result = $schema->safeParse(20);

    $this->assertTrue($result->success);
  }

  public function test_lte_WithValueGreaterThanMax_ShouldFail(): void {
    $schema = $this->createNumberSchema()->lte(20);

    $result = $schema->safeParse(25);

    $this->assertFalse($result->success);
  }

  public function test_between_WithValueWithinRange_ShouldPass(): void {
    $schema = $this->createNumberSchema()->between(10, 20);

    $result = $schema->safeParse(15);

    $this->assertTrue($result->success);
  }

  public function test_between_WithValueAtMinBoundary_ShouldPass(): void {
    $schema = $this->createNumberSchema()->between(10, 20);

    $result = $schema->safeParse(10);

    $this->assertTrue($result->success);
  }

  public function test_between_WithValueAtMaxBoundary_ShouldPass(): void {
    $schema = $this->createNumberSchema()->between(10, 20);

    $result = $schema->safeParse(20);

    $this->assertTrue($result->success);
  }

  public function test_between_WithValueBelowMin_ShouldFail(): void {
    $schema = $this->createNumberSchema()->between(10, 20);

    $result = $schema->safeParse(5);

    $this->assertFalse($result->success);
    $this->assertSame('out_of_range', $result->issues[0]->code);
  }

  public function test_between_WithValueAboveMax_ShouldFail(): void {
    $schema = $this->createNumberSchema()->between(10, 20);

    $result = $schema->safeParse(25);

    $this->assertFalse($result->success);
  }

  public function test_between_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'Value must be between 10 and 20';
    $schema = $this->createNumberSchema()->between(10, 20, $customMessage);

    $result = $schema->safeParse(5);

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_multipleOf_WithMultipleValue_ShouldPass(): void {
    $schema = $this->createNumberSchema()->multipleOf(5);

    $result = $schema->safeParse(15);

    $this->assertTrue($result->success);
  }

  public function test_multipleOf_WithZero_ShouldPass(): void {
    $schema = $this->createNumberSchema()->multipleOf(5);

    $result = $schema->safeParse(0);

    $this->assertTrue($result->success);
  }

  public function test_multipleOf_WithNonMultipleValue_ShouldFail(): void {
    $schema = $this->createNumberSchema()->multipleOf(5);

    $result = $schema->safeParse(16);

    $this->assertFalse($result->success);
    $this->assertSame('not_multiple', $result->issues[0]->code);
  }

  public function test_multipleOf_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'Value must be multiple of 5';
    $schema = $this->createNumberSchema()->multipleOf(5, message: $customMessage);

    $result = $schema->safeParse(16);

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  /**
   * @return array<string, array{int|float}>
   */
  public static function provideInvalidDivisors(): array {
    return [
      'zero' => [0],
      'float_zero' => [0.0],
      'negative' => [-5],
      'infinity' => [INF],
      'nan' => [NAN],
    ];
  }

  #[DataProvider('provideInvalidDivisors')]
  public function test_multipleOf_WithNonPositiveOrNonFiniteDivisor_ShouldThrow(int|float $divisor): void {
    $this->expectException(InvalidArgumentException::class);

    $this->createNumberSchema()->multipleOf($divisor);
  }

  /**
   * @return array<string, array{float}>
   */
  public static function provideNonFiniteValues(): array {
    return [
      'infinity' => [INF],
      'negative_infinity' => [-INF],
      'nan' => [NAN],
    ];
  }

  #[DataProvider('provideNonFiniteValues')]
  public function test_parseType_WithNonFiniteValue_ShouldFailWithInvalidType(float $value): void {
    $result = $this->createNumberSchema()->safeParse($value);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  #[DataProvider('provideNonFiniteValues')]
  public function test_parseType_WithNonFiniteValueAndCoercion_ShouldFailWithInvalidType(float $value): void {
    $result = $this->createNumberSchema()->coerce()->safeParse($value);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithOverflowingExponentialString_ShouldFailWithInvalidType(): void {
    $result = $this->createNumberSchema()->coerce()->safeParse('1e400');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_multipleOf_WithFractionalDivisorAndIntegerMultiple_ShouldPass(): void {
    $schema = $this->createNumberSchema()->multipleOf(0.1);

    $result = $schema->safeParse(3);

    $this->assertTrue($result->success);
  }

  public function test_positive_WithPositiveValue_ShouldPass(): void {
    $schema = $this->createNumberSchema()->positive();

    $result = $schema->safeParse(10);

    $this->assertTrue($result->success);
  }

  public function test_positive_WithZero_ShouldFail(): void {
    $schema = $this->createNumberSchema()->positive();

    $result = $schema->safeParse(0);

    $this->assertFalse($result->success);
  }

  public function test_positive_WithNegativeValue_ShouldFail(): void {
    $schema = $this->createNumberSchema()->positive();

    $result = $schema->safeParse(-5);

    $this->assertFalse($result->success);
  }

  public function test_negative_WithNegativeValue_ShouldPass(): void {
    $schema = $this->createNumberSchema()->negative();

    $result = $schema->safeParse(-10);

    $this->assertTrue($result->success);
  }

  public function test_negative_WithZero_ShouldFail(): void {
    $schema = $this->createNumberSchema()->negative();

    $result = $schema->safeParse(0);

    $this->assertFalse($result->success);
  }

  public function test_negative_WithPositiveValue_ShouldFail(): void {
    $schema = $this->createNumberSchema()->negative();

    $result = $schema->safeParse(5);

    $this->assertFalse($result->success);
  }

  public function test_nonnegative_WithPositiveValue_ShouldPass(): void {
    $schema = $this->createNumberSchema()->nonnegative();

    $result = $schema->safeParse(10);

    $this->assertTrue($result->success);
  }

  public function test_nonnegative_WithZero_ShouldPass(): void {
    $schema = $this->createNumberSchema()->nonnegative();

    $result = $schema->safeParse(0);

    $this->assertTrue($result->success);
  }

  public function test_nonnegative_WithNegativeValue_ShouldFail(): void {
    $schema = $this->createNumberSchema()->nonnegative();

    $result = $schema->safeParse(-5);

    $this->assertFalse($result->success);
  }

  public function test_nonpositive_WithNegativeValue_ShouldPass(): void {
    $schema = $this->createNumberSchema()->nonpositive();

    $result = $schema->safeParse(-10);

    $this->assertTrue($result->success);
  }

  public function test_nonpositive_WithZero_ShouldPass(): void {
    $schema = $this->createNumberSchema()->nonpositive();

    $result = $schema->safeParse(0);

    $this->assertTrue($result->success);
  }

  public function test_nonpositive_WithPositiveValue_ShouldFail(): void {
    $schema = $this->createNumberSchema()->nonpositive();

    $result = $schema->safeParse(5);

    $this->assertFalse($result->success);
  }

  public function test_positive_WithZero_ShouldDescribePositiveInDefaultMessage(): void {
    $result = $this->createNumberSchema()->positive()->safeParse(0);

    $this->assertSame('Must be a positive number', $result->issues[0]->message);
  }

  public function test_nonnegative_WithNegativeValue_ShouldDescribeNonNegativeInDefaultMessage(): void {
    $result = $this->createNumberSchema()->nonnegative()->safeParse(-1);

    $this->assertSame('Must be a non-negative number', $result->issues[0]->message);
  }

  public function test_negative_WithZero_ShouldDescribeNegativeInDefaultMessage(): void {
    $result = $this->createNumberSchema()->negative()->safeParse(0);

    $this->assertSame('Must be a negative number', $result->issues[0]->message);
  }

  public function test_nonpositive_WithPositiveValue_ShouldDescribeNonPositiveInDefaultMessage(): void {
    $result = $this->createNumberSchema()->nonpositive()->safeParse(1);

    $this->assertSame('Must be a non-positive number', $result->issues[0]->message);
  }

  public function test_combined_GtAndLt_ShouldEnforceExclusiveRange(): void {
    $schema = $this->createNumberSchema()
      ->gt(10)
      ->lt(20);

    $this->assertFalse($schema->safeParse(10)->success);
    $this->assertFalse($schema->safeParse(20)->success);
    $this->assertTrue($schema->safeParse(15)->success);
  }

  public function test_combined_GteAndLte_ShouldEnforceInclusiveRange(): void {
    $schema = $this->createNumberSchema()
      ->gte(10)
      ->lte(20);

    $this->assertTrue($schema->safeParse(10)->success);
    $this->assertTrue($schema->safeParse(15)->success);
    $this->assertTrue($schema->safeParse(20)->success);
    $this->assertFalse($schema->safeParse(9)->success);
    $this->assertFalse($schema->safeParse(21)->success);
  }

  public function test_combined_BetweenAndMultipleOf_ShouldEnforceBoth(): void {
    $schema = $this->createNumberSchema()
      ->between(0, 50)
      ->multipleOf(10);

    $this->assertTrue($schema->safeParse(0)->success);
    $this->assertTrue($schema->safeParse(10)->success);
    $this->assertTrue($schema->safeParse(30)->success);
    $this->assertTrue($schema->safeParse(50)->success);

    $this->assertFalse($schema->safeParse(5)->success);
    $this->assertFalse($schema->safeParse(55)->success);
  }

  public function test_combined_PositiveAndMultipleOf_ShouldEnforceBoth(): void {
    $schema = $this->createNumberSchema()
      ->positive()
      ->multipleOf(3);

    $this->assertTrue($schema->safeParse(3)->success);
    $this->assertTrue($schema->safeParse(9)->success);
    $this->assertTrue($schema->safeParse(300)->success);

    $this->assertFalse($schema->safeParse(0)->success);
    $this->assertFalse($schema->safeParse(4)->success);
    $this->assertFalse($schema->safeParse(-3)->success);
  }

  public function test_cloneWithRules_ShouldCreateIndependentCopy(): void {
    $schema1 = $this->createNumberSchema()->gt(10);
    $schema2 = clone $schema1;
    $schema2 = $schema2->lt(20);

    $value = 15;

    $this->assertTrue($schema1->safeParse($value)->success);
    $this->assertTrue($schema2->safeParse($value)->success);

    $this->assertTrue($schema1->safeParse(25)->success);
    $this->assertFalse($schema2->safeParse(25)->success);
  }

  public function test_chainedOperations_ShouldReturnValidSchema(): void {
    $schema = $this->createNumberSchema()
      ->gt(0)
      ->lt(100)
      ->positive()
      ->multipleOf(2);

    $result = $schema->safeParse(42);

    $this->assertTrue($result->success);
  }

  public function test_refineWithComplexCondition_ShouldEnforce(): void {
    $schema = $this->createNumberSchema()->refine(
      fn(mixed $v, array $p): bool => $v > 0 && $v < 100 && fmod($v, 2) === 0.0,
      'Must be positive, less than 100, and even'
    );

    $this->assertTrue($schema->safeParse(2)->success);
    $this->assertTrue($schema->safeParse(50)->success);
    $this->assertFalse($schema->safeParse(1)->success);
    $this->assertFalse($schema->safeParse(101)->success);
    $this->assertFalse($schema->safeParse(-2)->success);
  }

  public function test_optional_WithGtRule_ShouldAllowNull(): void {
    $schema = $this->createNumberSchema()
      ->gt(10)
      ->optional();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
  }

  public function test_default_WithGtRule_ShouldApplyDefault(): void {
    $schema = $this->createNumberSchema()
      ->gt(0)
      ->_default(100);

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertEquals(100, $result->data);
  }

  public function test_validateThenTransform_ShouldTransformValueValidated(): void {
    $schema = $this->createNumberSchema()
      ->transform(fn(mixed $v) => $v * 2)
      ->gt(10);

    $result = $schema->safeParse(6);

    $this->assertFalse($result->success);

    $result2 = $schema->safeParse(12);

    $this->assertTrue($result2->success);
  }
}
