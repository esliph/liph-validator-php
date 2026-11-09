<?php

namespace Esliph\Validator\Tests\Schemas\Primitive;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;

use Esliph\Validator\Schemas\Primitive\NumberSchema;

class NumberSchemaTest extends NumberSchemaTestCase {

  #[Override]
  protected function createNumberSchema(): NumberSchema {
    return new NumberSchema();
  }

  #[Override]
  protected function expectedTypeLabel(): string {
    return 'number';
  }

  #[Override]
  protected function getValidValue(): mixed {
    return 42;
  }

  #[Override]
  protected function getInvalidValue(): mixed {
    return 'not a number';
  }

  public function test_parseType_WithValidFloat_ShouldAccept(): void {
    $result = (new NumberSchema())->safeParse(3.14);

    $this->assertTrue($result->success);
    $this->assertSame(3.14, $result->data);
    $this->assertIsFloat($result->data);
  }

  public function test_parseType_WithValidStringNumeric_ShouldAccept(): void {
    $result = (new NumberSchema())->coerce()->safeParse('3.14');

    $this->assertTrue($result->success);
    $this->assertSame(3.14, $result->data);
    $this->assertIsFloat($result->data);
  }

  public function test_parseType_WithValidInteger_ShouldAccept(): void {
    $result = (new NumberSchema())->safeParse(42);

    $this->assertTrue($result->success);
    $this->assertEquals(42, $result->data);
    $this->assertIsInt($result->data);
  }

  public function test_parseType_WithZero_ShouldAccept(): void {
    $result = (new NumberSchema())->safeParse(0);

    $this->assertTrue($result->success);
    $this->assertEquals(0, $result->data);
  }

  public function test_parseType_WithNegativeNumber_ShouldAccept(): void {
    $result = (new NumberSchema())->safeParse(-42.5);

    $this->assertTrue($result->success);
    $this->assertSame(-42.5, $result->data);
  }

  public function test_parseType_WithLargeNumber_ShouldAccept(): void {
    $result = (new NumberSchema())->safeParse(PHP_INT_MAX);

    $this->assertTrue($result->success);
    $this->assertSame(PHP_INT_MAX, $result->data);
  }

  public function test_parseType_WithInfinity_ShouldReject(): void {
    $result = (new NumberSchema())->safeParse(INF);

    $this->assertFalse($result->success);
    $this->assertSame('not_finite', $result->issues[0]->code);
    $this->assertSame('Expected finite number, received INF', $result->issues[0]->message);
  }

  public function test_parseType_WithNaN_ShouldReject(): void {
    $result = (new NumberSchema())->safeParse(NAN);

    $this->assertFalse($result->success);
    $this->assertSame('Expected finite number, received NAN', $result->issues[0]->message);
  }

  public function test_coerce_WithOverflowingExponentialString_ShouldReject(): void {
    $result = (new NumberSchema())->coerce()->safeParse('1e400');

    $this->assertFalse($result->success);
    $this->assertSame('Expected finite number, received INF', $result->issues[0]->message);
  }

  public function test_coerce_WithOverflowingExponentialString_ShouldFailNotFiniteWithOriginalInClosure(): void {
    $received = null;
    $schema = (new NumberSchema())->coerce()->messages([
      'not_finite' => function (mixed $value, array $params) use (&$received): string {
        $received = [$value, $params];
        return 'custom';
      },
    ]);

    $result = $schema->safeParse('1e400');

    $this->assertSame('not_finite', $result->issues[0]->code);
    $this->assertSame(['1e400', []], $received);
  }

  /**
   * @return array<string, array{string, int|float}>
   */
  public static function provideNumberGrammarStrings(): array {
    return [
      'spaces' => [' 1 ', 1],
      'plus_sign' => ['+1', 1],
      'leading_zeros' => ['007', 7],
      'negative_zero' => ['-0', 0],
      'int_max' => [(string) PHP_INT_MAX, PHP_INT_MAX],
      'leading_dot' => ['.5', 0.5],
      'decimal' => ['1.5', 1.5],
      'exponent' => ['1e3', 1000.0],
      'beyond_int_range' => ['99999999999999999999', 1.0E20],
    ];
  }

  #[DataProvider('provideNumberGrammarStrings')]
  public function test_coerce_WithNumberGrammarString_ShouldCoerce(string $input, int|float $expected): void {
    $result = (new NumberSchema())->coerce()->safeParse($input);

    $this->assertTrue($result->success);
    $this->assertSame($expected, $result->data);
  }

  public function test_parseType_WithBool_ShouldDescribeAsBool(): void {
    $result = (new NumberSchema())->safeParse(true);

    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected number, received bool', $result->issues[0]->message);
  }

  public function test_parseType_WithString_ShouldReject(): void {
    $result = (new NumberSchema())->safeParse('not a number');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithArray_ShouldReject(): void {
    $result = (new NumberSchema())->safeParse([42]);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithObject_ShouldReject(): void {
    $result = (new NumberSchema())->safeParse(new \stdClass());

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_multipleOf_WithDivisorAndValueAsDecimal_ShouldPass(): void {
    $schema = $this->createNumberSchema()->multipleOf(0.5);

    $result = $schema->safeParse(3.5);

    $this->assertTrue($result->success);
  }

  public function test_multipleOf_WithTenthDivisorAndFloatMultiple_ShouldPass(): void {
    $schema = $this->createNumberSchema()->multipleOf(0.1);

    $result = $schema->safeParse(0.3);

    $this->assertTrue($result->success);
  }

  public function test_multipleOf_WithTenthDivisorAndNonMultiple_ShouldFail(): void {
    $schema = $this->createNumberSchema()->multipleOf(0.1);

    $result = $schema->safeParse(0.35);

    $this->assertFalse($result->success);
    $this->assertSame('not_multiple', $result->issues[0]->code);
  }

  public function test_multipleOf_WithHalfDivisorAndNonMultiple_ShouldFail(): void {
    $schema = $this->createNumberSchema()->multipleOf(0.5);

    $result = $schema->safeParse(0.7);

    $this->assertFalse($result->success);
    $this->assertSame('not_multiple', $result->issues[0]->code);
  }

  public function test_multipleOf_WithIntegerDivisorAndFloatNonMultiple_ShouldFail(): void {
    $schema = $this->createNumberSchema()->multipleOf(5);

    $result = $schema->safeParse(5.5);

    $this->assertFalse($result->success);
  }

  public function test_multipleOf_WithInfinity_ShouldFail(): void {
    $schema = $this->createNumberSchema()->multipleOf(5);

    $result = $schema->safeParse(INF);

    $this->assertFalse($result->success);
  }
}
