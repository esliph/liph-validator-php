<?php

namespace Esliph\Validator\Tests\Schemas\Primitive;

use PHPUnit\Framework\Attributes\DataProvider;

use Esliph\Validator\Errors\ValidatorException;
use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Schemas\Primitive\BooleanSchema;

use Esliph\Validator\Tests\Schemas\BaseCoercibleSchemaTestCase;

use Closure;
use Override;

class BooleanSchemaTest extends BaseCoercibleSchemaTestCase {

  #[Override]
  protected function createSchemaCoercible(): CoercibleSchema {
    return new BooleanSchema();
  }

  #[Override]
  protected function getValidValue(): mixed {
    return true;
  }

  #[Override]
  protected function getInvalidValue(): mixed {
    return 'not a boolean';
  }

  #[Override]
  protected function getDefaultValue(): mixed {
    return false;
  }

  #[Override]
  protected function getTransformFunction(): Closure {
    return fn(mixed $value) => $value;
  }

  #[Override]
  protected function getRefineCheck(): Closure {
    return fn(mixed $value): bool => $value === true;
  }

  public function test_parseType_WithBooleanTrue_ShouldAccept(): void {
    $schema = new BooleanSchema();

    $result = $schema->safeParse(true);

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
    $this->assertIsBool($result->data);
  }

  public function test_parseType_WithBooleanFalse_ShouldAccept(): void {
    $schema = new BooleanSchema();

    $result = $schema->safeParse(false);

    $this->assertTrue($result->success);
    $this->assertFalse($result->data);
    $this->assertIsBool($result->data);
  }

  public function test_parseType_WithInteger_ShouldReject(): void {
    $schema = new BooleanSchema();

    $result = $schema->safeParse(1);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithFloat_ShouldReject(): void {
    $schema = new BooleanSchema();

    $result = $schema->safeParse(1.5);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithString_ShouldReject(): void {
    $schema = new BooleanSchema();

    $result = $schema->safeParse('true');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithArray_ShouldReject(): void {
    $schema = new BooleanSchema();

    $result = $schema->safeParse([true]);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithObject_ShouldReject(): void {
    $schema = new BooleanSchema();

    $result = $schema->safeParse(new \stdClass());

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithNull_ShouldReject(): void {
    $schema = new BooleanSchema();

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertSame('required', $result->issues[0]->code);
  }

  public function test_coerce_WithStringTrue_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('true');

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_coerce_WithStringTrue_UpperCase_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('TRUE');

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_coerce_WithStringTrue_MixedCase_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('TrUe');

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_coerce_WithStringOne_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('1');

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_coerce_WithStringYes_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('yes');

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_coerce_WithStringYes_UpperCase_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('YES');

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_coerce_WithStringOn_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('on');

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_coerce_WithStringOn_UpperCase_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('ON');

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_coerce_WithStringFalse_ShouldCoerceToFalse(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('false');

    $this->assertTrue($result->success);
    $this->assertFalse($result->data);
  }

  public function test_coerce_WithStringFalse_UpperCase_ShouldCoerceToFalse(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('FALSE');

    $this->assertTrue($result->success);
    $this->assertFalse($result->data);
  }

  public function test_coerce_WithStringZero_ShouldCoerceToFalse(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('0');

    $this->assertTrue($result->success);
    $this->assertFalse($result->data);
  }

  public function test_coerce_WithStringNo_ShouldCoerceToFalse(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('no');

    $this->assertTrue($result->success);
    $this->assertFalse($result->data);
  }

  public function test_coerce_WithStringNo_UpperCase_ShouldCoerceToFalse(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('NO');

    $this->assertTrue($result->success);
    $this->assertFalse($result->data);
  }

  public function test_coerce_WithStringOff_ShouldCoerceToFalse(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('off');

    $this->assertTrue($result->success);
    $this->assertFalse($result->data);
  }

  public function test_coerce_WithStringOff_UpperCase_ShouldCoerceToFalse(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('OFF');

    $this->assertTrue($result->success);
    $this->assertFalse($result->data);
  }

  public function test_coerce_WithEmptyString_ShouldFailRequired(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('');

    $this->assertFalse($result->success);
    $this->assertSame('required', $result->issues[0]->code);
  }

  public function test_coerce_WithStringWithLeadingWhitespace_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('  true');

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_coerce_WithStringWithTrailingWhitespace_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('true  ');

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_coerce_WithStringWithBothWhitespace_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('  true  ');

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_coerce_WithStringRandomText_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('random text');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithUncoercibleString_ShouldReportOriginalTypeInMessage(): void {
    $result = (new BooleanSchema())->coerce()->safeParse('2');

    $this->assertSame('Expected bool, received string', $result->issues[0]->message);
  }

  public function test_coerce_WithUncoercibleInteger_ShouldReportOriginalTypeInMessage(): void {
    $result = (new BooleanSchema())->coerce()->safeParse(2);

    $this->assertSame('Expected bool, received int', $result->issues[0]->message);
  }

  public function test_coerce_WithStringNotTrue_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('not true');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithIntegerOne_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(1);

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_coerce_WithIntegerNegativeOne_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(-1);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithIntegerTwo_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(2);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithIntegerLarge_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(PHP_INT_MAX);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithIntegerZero_ShouldCoerceToFalse(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(0);

    $this->assertTrue($result->success);
    $this->assertFalse($result->data);
  }

  public function test_coerce_WithFloatPositive_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(1.5);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithFloatSmallPositive_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(0.1);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithFloatNegative_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(-1.5);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithFloatZero_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(0.0);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithFloatInfinity_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(INF);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithFloatNegativeInfinity_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(-INF);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithFloatNaN_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(NAN);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithNumericString_ShouldCoerceCorrectly(): void {
    $schema = (new BooleanSchema())->coerce();

    $resultOne = $schema->safeParse('1');
    $this->assertTrue($resultOne->success);
    $this->assertTrue($resultOne->data);

    $resultTwo = $schema->safeParse('2');
    $this->assertFalse($resultTwo->success);
    $this->assertSame('invalid_type', $resultTwo->issues[0]->code);
  }

  public function test_coerce_WithEmptyArray_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse([]);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithNonEmptyIndexedArray_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(['a', 'b', 'c']);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithNonEmptyAssociativeArray_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(['key' => 'value']);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithArrayWithOneElement_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse([0]);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithArrayWithFalseElement_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse([false]);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithArrayWithNullElement_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse([null]);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithStdClass_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(new \stdClass());

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithCustomObject_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(new class {
    });

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithClosure_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(fn() => null);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithBooleanTrue_ShouldRemainTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(true);

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_coerce_WithBooleanFalse_ShouldRemainFalse(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(false);

    $this->assertTrue($result->success);
    $this->assertFalse($result->data);
  }

  public function test_edgecase_StringWithWhitespaceAndTrue_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('   true   ');

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_edgecase_StringWithTabsAndYes_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse("\t\tyes\t\t");

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_edgecase_StringWithNewlinesAndOn_ShouldCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse("\n\non\n\n");

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_edgecase_StringAlmostFalse_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('fals');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_edgecase_StringAlmostTrue_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse('tru');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_edgecase_NegativeZero_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(-0.0);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_edgecase_VerySmallPositiveFloat_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(0.000001);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_edgecase_OptionalWithNull_ShouldSucceed(): void {
    $schema = (new BooleanSchema())->optional();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
  }

  public function test_edgecase_DefaultValueWithNull_ShouldUseDefault(): void {
    $schema = (new BooleanSchema())->coerce()->_default(true);

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_edgecase_CloneWithoutCoerce_ShouldCreateIndependentCopy(): void {
    $schema1 = new BooleanSchema();
    $schema2 = clone $schema1;

    $this->assertNotSame($schema1, $schema2);
    $this->assertFalse($schema1->safeParse('true')->success);
    $this->assertFalse($schema2->safeParse('true')->success);
  }

  public function test_edgecase_CloneWithCoerce_ShouldCreateIndependentCopy(): void {
    $schema1 = (new BooleanSchema())->coerce();
    $schema2 = clone $schema1;

    $this->assertNotSame($schema1, $schema2);
    $this->assertTrue($schema1->safeParse('true')->success);
    $this->assertTrue($schema2->safeParse('true')->success);
  }

  public function test_edgecase_RefineWithComplexCondition_ShouldEnforce(): void {
    $schema = (new BooleanSchema())->refine(
      fn(mixed $value): bool => $value === true,
      'Value must be exactly true'
    );

    $this->assertTrue($schema->safeParse(true)->success);
    $this->assertFalse($schema->safeParse(false)->success);
  }

  public function test_edgecase_CoerceWithRefine_ShouldCoerceThenValidate(): void {
    $schema = (new BooleanSchema())
      ->coerce()
      ->refine(
        fn(mixed $value): bool => $value === true,
        'Must be true'
      );

    $this->assertTrue($schema->safeParse('true')->success);
    $this->assertTrue($schema->safeParse('yes')->success);
    $this->assertFalse($schema->safeParse('false')->success);
  }

  public function test_edgecase_TransformWithBoolean_ShouldKeepValue(): void {
    $schema = (new BooleanSchema())->transform(fn(mixed $v) => $v);

    $result = $schema->safeParse(true);

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_edgecase_CoerceWithTransform_ShouldCoerceThenTransform(): void {
    $schema = (new BooleanSchema())
      ->coerce()
      ->transform(fn(mixed $v) => $v ? 'yes' : 'no');

    $result = $schema->safeParse('true');

    $this->assertTrue($result->success);
    $this->assertSame('yes', $result->data);
  }

  public function test_edgecase_MultipleTransforms_ShouldChain(): void {
    $schema = (new BooleanSchema())
      ->coerce()
      ->transform(fn(mixed $v) => $v ? 1 : 0)
      ->transform(fn(mixed $v) => $v === 1 ? 'enabled' : 'disabled');

    $result = $schema->safeParse('on');

    $this->assertTrue($result->success);
    $this->assertSame('enabled', $result->data);
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithAllInputTypes_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = new BooleanSchema();

    $result = $schema->safeParse($value);

    $shouldBeValid = is_bool($value);

    if ($shouldBeValid) {
      $this->assertTrue($result->success, "Type $typeName should be valid");
      $this->assertIsBool($result->data);
    } else {
      $this->assertFalse($result->success, "Type $typeName should be invalid");

      if ($value === null) {
        $this->assertSame('required', $result->issues[0]->code);
      } else {
        $this->assertSame('invalid_type', $result->issues[0]->code);
      }
    }
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithCoercionAndAllInputTypes_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse($value);

    $parsed = $value;
    if (is_string($value)) {
      $parsed = mb_strtolower(trim($value));
    }

    $knownCoerceableValues = [true, false, 1, 0, '1', '0', 'true', 'false', 'yes', 'no', 'on', 'off'];
    $shouldSucceed = in_array($parsed, $knownCoerceableValues, true);

    if ($shouldSucceed) {
      $this->assertTrue($result->success, "Type $typeName should coerce successfully");
      $this->assertIsBool($result->data);
    } else {
      $this->assertFalse($result->success, "Type $typeName should fail coercion");

      if ($value === null || (is_string($value) && trim($value) === '')) {
        $this->assertSame('required', $result->issues[0]->code);
      } else {
        $this->assertSame('invalid_type', $result->issues[0]->code);
      }
    }
  }

  public function test_coerce_WithNumericStringValidation_ShouldCoerceOnlySpecificValues(): void {
    $schema = (new BooleanSchema())->coerce();

    $validCases = [
      '1' => true,
      '0' => false,
    ];

    foreach ($validCases as $input => $expected) {
      $result = $schema->safeParse($input);
      $this->assertTrue($result->success, "Failed to coerce '$input'");
      $this->assertSame($expected, $result->data, "Expected '$input' to coerce to " . ($expected ? 'true' : 'false'));
    }

    $invalidCases = ['2', '100', '-1', '-100'];

    foreach ($invalidCases as $input) {
      $result = $schema->safeParse($input);
      $this->assertFalse($result->success, "'$input' should fail coercion");
      $this->assertSame('invalid_type', $result->issues[0]->code);
    }
  }

  public function test_coerce_WithTruthyStringVariations_ShouldAllCoerceToTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $truthyStrings = ['true', 'TRUE', 'True', 'TrUe', '1', 'yes', 'YES', 'Yes', 'on', 'ON', 'On'];

    foreach ($truthyStrings as $str) {
      $result = $schema->safeParse($str);
      $this->assertTrue($result->success, "Failed to coerce '$str'");
      $this->assertTrue($result->data, "Expected '$str' to coerce to true");
    }
  }

  public function test_coerce_WithFalsyStringVariations_ShouldAllCoerceToFalse(): void {
    $schema = (new BooleanSchema())->coerce();

    $falsyStrings = ['false', 'FALSE', 'False', 'FaLsE', '0', 'no', 'NO', 'No', 'off', 'OFF', 'Off'];

    foreach ($falsyStrings as $str) {
      $result = $schema->safeParse($str);
      $this->assertTrue($result->success, "Failed to coerce '$str'");
      $this->assertFalse($result->data, "Expected '$str' to coerce to false");
    }
  }

  public function test_coerce_WithRandomStringsShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $randomStrings = ['hello', 'world', 'maybe', 'random', 'anything', 'not a boolean', '2', 'perhaps'];

    foreach ($randomStrings as $str) {
      $result = $schema->safeParse($str);
      $this->assertFalse($result->success, "'$str' should fail coercion");
      $this->assertSame('invalid_type', $result->issues[0]->code);
    }
  }

  public function test_boundary_WithIntPHP_INT_MAX_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(PHP_INT_MAX);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_boundary_WithIntPHP_INT_MIN_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(PHP_INT_MIN);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_boundary_WithVerySmallNegativeFloat_ShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $result = $schema->safeParse(-0.000001);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_chainedOperations_ShouldReturnValidSchema(): void {
    $schema = (new BooleanSchema())
      ->coerce()
      ->refine(fn(mixed $v) => true, 'Check passed');

    $result = $schema->safeParse('yes');

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
  }

  public function test_parseAndThrowException_WithInvalidType_ShouldThrow(): void {
    $this->expectException(ValidatorException::class);

    $schema = new BooleanSchema();
    $schema->parse('invalid');
  }

  public function test_isValid_WithValidBoolean_ShouldReturnTrue(): void {
    $schema = new BooleanSchema();

    $this->assertTrue($schema->isValid(true));
    $this->assertTrue($schema->isValid(false));
  }

  public function test_isValid_WithInvalidType_ShouldReturnFalse(): void {
    $schema = new BooleanSchema();

    $this->assertFalse($schema->isValid(1));
    $this->assertFalse($schema->isValid('true'));
    $this->assertFalse($schema->isValid(null));
  }

  public function test_isValid_WithCoerce_ShouldReturnTrueForValidValues(): void {
    $schema = (new BooleanSchema())->coerce();

    $this->assertTrue($schema->isValid('true'));
    $this->assertTrue($schema->isValid(1));
    $this->assertTrue($schema->isValid('yes'));
  }

  public function test_coerce_WithStringOne_AndNumericOneInteger_ShouldBothBeTrue(): void {
    $schema = (new BooleanSchema())->coerce();

    $stringOne = $schema->safeParse('1');
    $integerOne = $schema->safeParse(1);

    $this->assertTrue($stringOne->success);
    $this->assertTrue($stringOne->data);
    $this->assertTrue($integerOne->success);
    $this->assertTrue($integerOne->data);
  }

  public function test_coerce_WithStringZero_AndNumericZeroInteger_ShouldBothBeFalse(): void {
    $schema = (new BooleanSchema())->coerce();

    $stringZero = $schema->safeParse('0');
    $integerZero = $schema->safeParse(0);

    $this->assertTrue($stringZero->success);
    $this->assertFalse($stringZero->data);
    $this->assertTrue($integerZero->success);
    $this->assertFalse($integerZero->data);
  }

  public function test_coerce_WithRandomIntegersShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $randomIntegers = [2, 5, 10, -5, -10, 100, PHP_INT_MAX, PHP_INT_MIN];

    foreach ($randomIntegers as $int) {
      $result = $schema->safeParse($int);
      $this->assertFalse($result->success, "Integer $int should fail coercion");
      $this->assertSame('invalid_type', $result->issues[0]->code);
    }
  }

  public function test_coerce_WithRandomFloatsShouldFail(): void {
    $schema = (new BooleanSchema())->coerce();

    $randomFloats = [0.5, 1.5, -0.5, 0.1, INF, -INF, NAN];

    foreach ($randomFloats as $float) {
      $result = $schema->safeParse($float);
      $this->assertFalse($result->success, "Float should fail coercion");
      $this->assertSame('invalid_type', $result->issues[0]->code);
    }
  }

  public function test_messages_WithInvalidTypeClosureAndCoercion_ShouldReceiveOriginalValueAndParams(): void {
    $received = null;
    $schema = (new BooleanSchema())->coerce()->messages([
      'invalid_type' => function (mixed $value, array $params) use (&$received): string {
        $received = [$value, $params];
        return 'sim ou não';
      },
    ]);

    $result = $schema->safeParse('talvez');

    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('sim ou não', $result->issues[0]->message);
    $this->assertSame(['talvez', ['expected' => 'bool', 'received' => 'string']], $received);
  }

  /** @return array<string, array{mixed, bool}> */
  public static function provideBooleanVocabulary(): array {
    return [
      'true' => ['true', true], 'upper_true' => ['TRUE', true], 'padded_yes' => [" Yes\t", true],
      'upper_on' => ['ON', true], 'one' => ['1', true], 'int_one' => [1, true],
      'mixed_false' => ['False', false], 'off' => ['off', false], 'padded_no' => [' NO ', false],
      'zero' => ['0', false], 'int_zero' => [0, false],
    ];
  }

  #[DataProvider('provideBooleanVocabulary')]
  public function test_coerce_WithVocabularyValue_ShouldCoerce(mixed $input, bool $expected): void {
    $result = (new BooleanSchema())->coerce()->safeParse($input);

    $this->assertTrue($result->success);
    $this->assertSame($expected, $result->data);
  }

  /** @return array<string, array{mixed}> */
  public static function provideNonVocabularyValues(): array {
    return [
      'int_two' => [2], 'float_one' => [1.0], 'string_ten' => ['10'],
      'string_y' => ['y'], 'accented_true' => ['TRUÉ'], 'empty_array' => [[]],
    ];
  }

  #[DataProvider('provideNonVocabularyValues')]
  public function test_coerce_WithNonVocabularyValue_ShouldFailWithOriginalType(mixed $input): void {
    $result = (new BooleanSchema())->coerce()->safeParse($input);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected bool, received ' . get_debug_type($input), $result->issues[0]->message);
  }
}
