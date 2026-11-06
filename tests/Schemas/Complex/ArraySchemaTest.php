<?php

namespace Esliph\Validator\Tests\Schemas\Complex;

use PHPUnit\Framework\Attributes\DataProvider;

use Esliph\Validator\Errors\ValidatorException;
use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Schemas\Complex\ArraySchema;
use Esliph\Validator\Schemas\Complex\ObjectSchema;
use Esliph\Validator\Schemas\Primitive\IntegerSchema;
use Esliph\Validator\Schemas\Primitive\StringSchema;
use Esliph\Validator\V;

use Esliph\Validator\Tests\Schemas\BaseCoercibleSchemaTestCase;

use Closure;
use Override;

class ArraySchemaTest extends BaseCoercibleSchemaTestCase {

  #[Override]
  protected function createSchemaCoercible(): CoercibleSchema {
    return new ArraySchema(new IntegerSchema());
  }

  #[Override]
  protected function getValidValue(): mixed {
    return [1, 2, 3];
  }

  #[Override]
  protected function getInvalidValue(): mixed {
    return 'not an array';
  }

  #[Override]
  protected function getDefaultValue(): mixed {
    return [10, 20];
  }

  #[Override]
  protected function getTransformFunction(): Closure {
    return fn(array $value): array => array_reverse($value);
  }

  #[Override]
  protected function getRefineCheck(): Closure {
    return fn(mixed $value, array $params): bool => count($value) === 3;
  }

  public function test_parseType_WithIndexedArray_ShouldAccept(): void {
    $schema = new ArraySchema();

    $result = $schema->safeParse([1, 'a', true]);

    $this->assertTrue($result->success);
    $this->assertSame([1, 'a', true], $result->data);
    $this->assertEmpty($result->issues);
  }

  public function test_parseType_WithEmptyArray_ShouldAccept(): void {
    $schema = new ArraySchema(new IntegerSchema());

    $result = $schema->safeParse([]);

    $this->assertTrue($result->success);
    $this->assertSame([], $result->data);
  }

  public function test_parseType_WithAssociativeArray_ShouldFailWithInvalidType(): void {
    $schema = new ArraySchema();

    $result = $schema->safeParse(['key' => 'value']);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected indexed array, received object', $result->issues[0]->message);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_parseType_WithArrayNotStartingAtZero_ShouldFailWithInvalidType(): void {
    $schema = new ArraySchema();

    $result = $schema->safeParse([1 => 'a', 2 => 'b']);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected indexed array, received object', $result->issues[0]->message);
  }

  public function test_parseType_WithArrayWithGapInKeys_ShouldFailWithInvalidType(): void {
    $schema = new ArraySchema();

    $result = $schema->safeParse([0 => 'a', 2 => 'c']);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithString_ShouldFailWithReceivedTypeInMessage(): void {
    $schema = new ArraySchema();

    $result = $schema->safeParse('[1, 2, 3]');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected array, received string', $result->issues[0]->message);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_parseType_WithStdClass_ShouldFailWithInvalidType(): void {
    $schema = new ArraySchema();

    $result = $schema->safeParse(new \stdClass());

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected array, received object', $result->issues[0]->message);
  }

  public function test_parseType_WithTraversableObject_ShouldFailWithInvalidType(): void {
    $schema = new ArraySchema();

    $result = $schema->safeParse(new \ArrayObject([1, 2, 3]));

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithNull_ShouldFailWithRequired(): void {
    $schema = new ArraySchema();

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame([], $result->issues[0]->path);
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithAllInputTypes_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = new ArraySchema();

    $result = $schema->safeParse($value);

    $shouldBeValid = is_array($value) && array_is_list($value);

    if ($shouldBeValid) {
      $this->assertTrue($result->success, "Type $typeName should be valid");
      $this->assertSame($value, $result->data);
    } else {
      $this->assertFalse($result->success, "Type $typeName should be invalid");
      $this->assertCount(1, $result->issues);

      if ($value === null) {
        $this->assertSame('required', $result->issues[0]->code);
      } else {
        $this->assertSame('invalid_type', $result->issues[0]->code);
        $expectedMessage = is_array($value)
          ? 'Expected indexed array, received object'
          : 'Expected array, received ' . gettype($value);
        $this->assertSame($expectedMessage, $result->issues[0]->message);
      }
    }
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithCoercionAndAllInputTypes_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = (new ArraySchema())->coerce();

    $result = $schema->safeParse($value);

    // Nenhuma string do provider é um JSON que decodifica para array.
    $shouldBeValid = is_array($value) && array_is_list($value);

    if ($shouldBeValid) {
      $this->assertTrue($result->success, "Type $typeName should be valid");
      $this->assertSame($value, $result->data);
    } else {
      $this->assertFalse($result->success, "Type $typeName should fail coercion");

      if ($value === null) {
        $this->assertSame('required', $result->issues[0]->code);
      } else {
        $this->assertSame('invalid_type', $result->issues[0]->code);
      }
    }
  }

  public function test_coerce_WithJsonArrayString_ShouldDecodeToArray(): void {
    $schema = (new ArraySchema())->coerce();

    $result = $schema->safeParse('[1, "a", true, null]');

    $this->assertTrue($result->success);
    $this->assertSame([1, 'a', true, null], $result->data);
  }

  public function test_coerce_WithEmptyJsonArray_ShouldDecodeToEmptyArray(): void {
    $schema = (new ArraySchema())->coerce();

    $result = $schema->safeParse('[]');

    $this->assertTrue($result->success);
    $this->assertSame([], $result->data);
  }

  public function test_coerce_WithJsonArrayWithSurroundingWhitespace_ShouldDecodeToArray(): void {
    $schema = (new ArraySchema())->coerce();

    $result = $schema->safeParse("  [1, 2]\n");

    $this->assertTrue($result->success);
    $this->assertSame([1, 2], $result->data);
  }

  public function test_coerce_WithNestedJsonArray_ShouldDecodeAndValidateNestedElements(): void {
    $schema = (new ArraySchema(new ArraySchema(new IntegerSchema())))->coerce();

    $result = $schema->safeParse('[[1, 2], [3]]');

    $this->assertTrue($result->success);
    $this->assertSame([[1, 2], [3]], $result->data);
  }

  public function test_coerce_WithJsonObjectString_ShouldFailWithInvalidType(): void {
    $schema = (new ArraySchema())->coerce();

    $result = $schema->safeParse('{"key": "value"}');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected indexed array, received object', $result->issues[0]->message);
  }

  public function test_coerce_WithInvalidJson_ShouldFailWithInvalidType(): void {
    $schema = (new ArraySchema())->coerce();

    $result = $schema->safeParse('[1, 2');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected array, received string', $result->issues[0]->message);
  }

  public function test_coerce_WithEmptyString_ShouldFailWithInvalidType(): void {
    $schema = (new ArraySchema())->coerce();

    $result = $schema->safeParse('');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithJsonScalarStrings_ShouldFailWithInvalidType(): void {
    $schema = (new ArraySchema())->coerce();

    foreach (['123', '"abc"', 'true', 'null', '1.5'] as $json) {
      $result = $schema->safeParse($json);

      $this->assertFalse($result->success, "'$json' should not be coerced to array");
      $this->assertSame('invalid_type', $result->issues[0]->code);
      $this->assertSame('Expected array, received string', $result->issues[0]->message);
    }
  }

  public function test_coerce_WithNonStringNonArrayValues_ShouldStillFail(): void {
    $schema = (new ArraySchema())->coerce();

    foreach ([42, 1.5, true, new \stdClass(), new \ArrayObject([1])] as $value) {
      $result = $schema->safeParse($value);

      $this->assertFalse($result->success);
      $this->assertSame('invalid_type', $result->issues[0]->code);
    }
  }

  public function test_coerce_WithAssociativeArray_ShouldStillFail(): void {
    $schema = (new ArraySchema())->coerce();

    $result = $schema->safeParse(['key' => 'value']);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithJsonString_ShouldValidateDecodedElements(): void {
    $schema = (new ArraySchema(new IntegerSchema()))->coerce();

    $result = $schema->safeParse('[1, "x", 3]');

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame([1], $result->issues[0]->path);
  }

  public function test_coerce_WithJsonString_ShouldApplyRulesToDecodedArray(): void {
    $schema = (new ArraySchema())->coerce()->min(2);

    $result = $schema->safeParse('[1]');

    $this->assertFalse($result->success);
    $this->assertSame('too_small', $result->issues[0]->code);
  }

  public function test_coerce_OnArray_ShouldNotCoerceElements(): void {
    $schema = (new ArraySchema(new IntegerSchema()))->coerce();

    $result = $schema->safeParse('["1", "2"]');

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame([0], $result->issues[0]->path);
    $this->assertSame([1], $result->issues[1]->path);
  }

  public function test_coerce_WithCoercibleElementSchema_ShouldCoerceElements(): void {
    $schema = (new ArraySchema((new IntegerSchema())->coerce()))->coerce();

    $result = $schema->safeParse('["1", "2"]');

    $this->assertTrue($result->success);
    $this->assertSame([1, 2], $result->data);
  }

  public function test_coerce_ShouldNotMutateOriginalSchema(): void {
    $base = new ArraySchema();

    $coerced = $base->coerce();

    $this->assertTrue($coerced->safeParse('[1]')->success);
    $this->assertFalse($base->safeParse('[1]')->success);
  }

  public function test_validateType_WithoutElementSchema_ShouldAcceptAnyElements(): void {
    $schema = new ArraySchema();
    $object = new \stdClass();

    $result = $schema->safeParse([1, 'a', null, [1, 2], $object]);

    $this->assertTrue($result->success);
    $this->assertSame([1, 'a', null, [1, 2], $object], $result->data);
  }

  public function test_validateType_WithAllValidElements_ShouldReturnParsedElements(): void {
    $schema = new ArraySchema(new IntegerSchema());

    $result = $schema->safeParse([1, 2, 3]);

    $this->assertTrue($result->success);
    $this->assertSame([1, 2, 3], $result->data);
  }

  public function test_validateType_WithInvalidElement_ShouldReportIndexPath(): void {
    $schema = new ArraySchema(new IntegerSchema());

    $result = $schema->safeParse([1, 2, 'three']);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame([2], $result->issues[0]->path);
  }

  public function test_validateType_WithMultipleInvalidElements_ShouldReportAllIssues(): void {
    $schema = new ArraySchema(new IntegerSchema());

    $result = $schema->safeParse(['a', 2, 'c', 4, 'e']);

    $this->assertFalse($result->success);
    $this->assertCount(3, $result->issues);
    $this->assertSame([0], $result->issues[0]->path);
    $this->assertSame([2], $result->issues[1]->path);
    $this->assertSame([4], $result->issues[2]->path);
  }

  public function test_validateType_WithElementFailingMultipleRules_ShouldReportAllElementIssues(): void {
    $schema = new ArraySchema((new StringSchema())->min(3)->email());

    $result = $schema->safeParse(['ok@mail.com', 'a']);

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame('too_small', $result->issues[0]->code);
    $this->assertSame([1], $result->issues[0]->path);
    $this->assertSame('invalid_format', $result->issues[1]->code);
    $this->assertSame([1], $result->issues[1]->path);
  }

  public function test_validateType_WithNullElement_ShouldFailWithRequiredAtIndex(): void {
    $schema = new ArraySchema(new IntegerSchema());

    $result = $schema->safeParse([1, null]);

    $this->assertFalse($result->success);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame([1], $result->issues[0]->path);
  }

  public function test_validateType_WithOptionalElementAndNull_ShouldKeepNull(): void {
    $schema = new ArraySchema((new IntegerSchema())->optional());

    $result = $schema->safeParse([1, null, 3]);

    $this->assertTrue($result->success);
    $this->assertSame([1, null, 3], $result->data);
  }

  public function test_validateType_WithElementDefault_ShouldReplaceNullWithDefault(): void {
    $schema = new ArraySchema((new IntegerSchema())->_default(0));

    $result = $schema->safeParse([1, null, 3]);

    $this->assertTrue($result->success);
    $this->assertSame([1, 0, 3], $result->data);
  }

  public function test_validateType_WithElementTransform_ShouldReturnTransformedElements(): void {
    $schema = new ArraySchema((new IntegerSchema())->transform(fn(int $v): int => $v * 10));

    $result = $schema->safeParse([1, 2, 3]);

    $this->assertTrue($result->success);
    $this->assertSame([10, 20, 30], $result->data);
  }

  public function test_validateType_WithCoercibleElement_ShouldReturnCoercedElements(): void {
    $schema = new ArraySchema((new IntegerSchema())->coerce());

    $result = $schema->safeParse(['1', '2', 3]);

    $this->assertTrue($result->success);
    $this->assertSame([1, 2, 3], $result->data);
  }

  public function test_validateType_WithNestedArrays_ShouldReportNestedIndexPath(): void {
    $schema = new ArraySchema(new ArraySchema(new IntegerSchema()));

    $result = $schema->safeParse([[1, 2], [3, 'x']]);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame([1, 1], $result->issues[0]->path);
  }

  public function test_validateType_WithNestedNonArrayElement_ShouldReportElementPath(): void {
    $schema = new ArraySchema(new ArraySchema(new IntegerSchema()));

    $result = $schema->safeParse([[1], 'not an array']);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected array, received string', $result->issues[0]->message);
    $this->assertSame([1], $result->issues[0]->path);
  }

  public function test_validateType_WithNestedArrayRuleFailing_ShouldReportElementPath(): void {
    $schema = new ArraySchema((new ArraySchema())->min(2));

    $result = $schema->safeParse([[1, 2], [1]]);

    $this->assertFalse($result->success);
    $this->assertSame('too_small', $result->issues[0]->code);
    $this->assertSame([1], $result->issues[0]->path);
  }

  public function test_validateType_WithObjectInsideArrayInsideObject_ShouldReportFullPath(): void {
    $schema = new ObjectSchema([
      'users' => new ArraySchema(new ObjectSchema([
        'email' => (new StringSchema())->email(),
      ])),
    ]);
    $input = (object) [
      'users' => [
        (object) ['email' => 'ok@mail.com'],
        (object) ['email' => 'invalid'],
      ],
    ];

    $result = $schema->safeParse($input);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_format', $result->issues[0]->code);
    $this->assertSame(['users', 1, 'email'], $result->issues[0]->path);
  }

  public function test_validateType_WithArrayOfObjects_ShouldReturnParsedObjects(): void {
    $schema = new ArraySchema(new ObjectSchema([
      'name' => (new StringSchema())->transform(fn(string $v): string => strtoupper($v)),
    ]));

    $result = $schema->safeParse([(object) ['name' => 'ana'], (object) ['name' => 'bia']]);

    $this->assertTrue($result->success);
    $this->assertSame('ANA', $result->data[0]->name);
    $this->assertSame('BIA', $result->data[1]->name);
  }

  public function test_validateType_WhenParentRuleFails_ShouldNotValidateElements(): void {
    $schema = (new ArraySchema(new IntegerSchema()))->min(3);

    $result = $schema->safeParse(['a', 'b']);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('too_small', $result->issues[0]->code);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_validateType_WhenElementFails_ShouldNotApplyTransforms(): void {
    $called = false;
    $schema = (new ArraySchema(new IntegerSchema()))->transform(function (array $v) use (&$called): array {
      $called = true;

      return $v;
    });

    $result = $schema->safeParse([1, 'x']);

    $this->assertFalse($result->success);
    $this->assertFalse($called);
  }

  public function test_validateType_WithTransform_ShouldReceiveParsedElements(): void {
    $schema = (new ArraySchema((new IntegerSchema())->coerce()))
      ->transform(fn(array $v): int => array_sum($v));

    $result = $schema->safeParse(['1', '2', '3']);

    $this->assertTrue($result->success);
    $this->assertSame(6, $result->data);
  }

  public function test_of_WithSchema_ShouldValidateElements(): void {
    $schema = (new ArraySchema())->of(new StringSchema());

    $this->assertTrue($schema->safeParse(['a', 'b'])->success);

    $result = $schema->safeParse(['a', 1]);

    $this->assertFalse($result->success);
    $this->assertSame([1], $result->issues[0]->path);
  }

  public function test_of_ShouldReplaceExistingElementSchema(): void {
    $schema = (new ArraySchema(new IntegerSchema()))->of(new StringSchema());

    $this->assertTrue($schema->safeParse(['a'])->success);
    $this->assertFalse($schema->safeParse([1])->success);
  }

  public function test_of_ShouldNotMutateOriginalSchema(): void {
    $base = new ArraySchema();

    $typed = $base->of(new IntegerSchema());

    $this->assertFalse($typed->safeParse(['a'])->success);
    $this->assertTrue($base->safeParse(['a'])->success);
  }

  public function test_of_ShouldKeepExistingRules(): void {
    $schema = (new ArraySchema())->min(2)->of(new IntegerSchema());

    $result = $schema->safeParse([1]);

    $this->assertFalse($result->success);
    $this->assertSame('too_small', $result->issues[0]->code);
  }

  public function test_min_WithLengthBelowMin_ShouldFailWithTooSmall(): void {
    $schema = (new ArraySchema())->min(2);

    $result = $schema->safeParse([1]);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('too_small', $result->issues[0]->code);
    $this->assertSame('Array must have at least 2 elements', $result->issues[0]->message);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_min_WithLengthEqualToMin_ShouldPass(): void {
    $schema = (new ArraySchema())->min(2);

    $result = $schema->safeParse([1, 2]);

    $this->assertTrue($result->success);
  }

  public function test_min_WithLengthAboveMin_ShouldPass(): void {
    $schema = (new ArraySchema())->min(2);

    $result = $schema->safeParse([1, 2, 3]);

    $this->assertTrue($result->success);
  }

  public function test_min_WithZeroAndEmptyArray_ShouldPass(): void {
    $schema = (new ArraySchema())->min(0);

    $result = $schema->safeParse([]);

    $this->assertTrue($result->success);
  }

  public function test_min_WithCustomStringMessage_ShouldUseIt(): void {
    $schema = (new ArraySchema())->min(2, 'Too few items');

    $result = $schema->safeParse([1]);

    $this->assertSame('Too few items', $result->issues[0]->message);
  }

  public function test_min_WithCustomClosureMessage_ShouldReceiveValueAndParams(): void {
    $schema = (new ArraySchema())->min(
      3,
      fn(array $value, array $params): string => 'Got ' . count($value) . ', need ' . $params['length']
    );

    $result = $schema->safeParse([1]);

    $this->assertSame('Got 1, need 3', $result->issues[0]->message);
  }

  public function test_min_ShouldNotMutateOriginalSchema(): void {
    $base = new ArraySchema();

    $withMin = $base->min(2);

    $this->assertFalse($withMin->safeParse([1])->success);
    $this->assertTrue($base->safeParse([1])->success);
  }

  public function test_max_WithLengthAboveMax_ShouldFailWithTooBig(): void {
    $schema = (new ArraySchema())->max(2);

    $result = $schema->safeParse([1, 2, 3]);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('too_big', $result->issues[0]->code);
    $this->assertSame('Array must have at most 2 elements', $result->issues[0]->message);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_max_WithLengthEqualToMax_ShouldPass(): void {
    $schema = (new ArraySchema())->max(2);

    $result = $schema->safeParse([1, 2]);

    $this->assertTrue($result->success);
  }

  public function test_max_WithLengthBelowMax_ShouldPass(): void {
    $schema = (new ArraySchema())->max(2);

    $result = $schema->safeParse([1]);

    $this->assertTrue($result->success);
  }

  public function test_max_WithZeroAndNonEmptyArray_ShouldFail(): void {
    $schema = (new ArraySchema())->max(0);

    $this->assertTrue($schema->safeParse([])->success);
    $this->assertFalse($schema->safeParse([1])->success);
  }

  public function test_max_WithCustomStringMessage_ShouldUseIt(): void {
    $schema = (new ArraySchema())->max(1, 'Too many items');

    $result = $schema->safeParse([1, 2]);

    $this->assertSame('Too many items', $result->issues[0]->message);
  }

  public function test_max_WithCustomClosureMessage_ShouldReceiveValueAndParams(): void {
    $schema = (new ArraySchema())->max(
      1,
      fn(array $value, array $params): string => 'Got ' . count($value) . ', max ' . $params['length']
    );

    $result = $schema->safeParse([1, 2, 3]);

    $this->assertSame('Got 3, max 1', $result->issues[0]->message);
  }

  public function test_length_WithExactLength_ShouldPass(): void {
    $schema = (new ArraySchema())->length(2);

    $result = $schema->safeParse([1, 2]);

    $this->assertTrue($result->success);
  }

  public function test_length_WithShorterArray_ShouldFail(): void {
    $schema = (new ArraySchema())->length(2);

    $result = $schema->safeParse([1]);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('Array must have exactly 2 elements', $result->issues[0]->message);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_length_WithLongerArray_ShouldFail(): void {
    $schema = (new ArraySchema())->length(2);

    $result = $schema->safeParse([1, 2, 3]);

    $this->assertFalse($result->success);
    $this->assertSame('Array must have exactly 2 elements', $result->issues[0]->message);
  }

  public function test_length_WithWrongLength_ShouldFailWithInvalidLengthCode(): void {
    // Mesmo código de StringSchema::length.
    $schema = (new ArraySchema())->length(2);

    $tooShort = $schema->safeParse([1]);
    $tooLong = $schema->safeParse([1, 2, 3]);

    $this->assertSame('invalid_length', $tooShort->issues[0]->code);
    $this->assertSame('invalid_length', $tooLong->issues[0]->code);
  }

  public function test_length_WithCustomStringMessage_ShouldUseIt(): void {
    $schema = (new ArraySchema())->length(2, 'Need a pair');

    $result = $schema->safeParse([1]);

    $this->assertSame('Need a pair', $result->issues[0]->message);
  }

  public function test_length_WithCustomClosureMessage_ShouldReceiveValueAndParams(): void {
    $schema = (new ArraySchema())->length(
      2,
      fn(array $value, array $params): string => count($value) . ' != ' . $params['length']
    );

    $result = $schema->safeParse([1, 2, 3]);

    $this->assertSame('3 != 2', $result->issues[0]->message);
  }

  public function test_nonempty_WithEmptyArray_ShouldFailWithTooSmall(): void {
    $schema = (new ArraySchema())->nonempty();

    $result = $schema->safeParse([]);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('too_small', $result->issues[0]->code);
    $this->assertSame('Array must not be empty', $result->issues[0]->message);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_nonempty_WithSingleElement_ShouldPass(): void {
    $schema = (new ArraySchema())->nonempty();

    $result = $schema->safeParse([0]);

    $this->assertTrue($result->success);
    $this->assertSame([0], $result->data);
  }

  public function test_nonempty_WithCustomStringMessage_ShouldUseIt(): void {
    $schema = (new ArraySchema())->nonempty('List is empty');

    $result = $schema->safeParse([]);

    $this->assertSame('List is empty', $result->issues[0]->message);
  }

  public function test_nonempty_WithCustomClosureMessage_ShouldUseIt(): void {
    $schema = (new ArraySchema())->nonempty(fn(array $value, array $params): string => 'Empty: ' . count($value));

    $result = $schema->safeParse([]);

    $this->assertSame('Empty: 0', $result->issues[0]->message);
  }

  public function test_nonempty_WithEmptyJsonStringAndCoerce_ShouldFail(): void {
    $schema = (new ArraySchema())->coerce()->nonempty();

    $result = $schema->safeParse('[]');

    $this->assertFalse($result->success);
    $this->assertSame('too_small', $result->issues[0]->code);
  }

  public function test_rules_WithMultipleFailingRules_ShouldReportAllIssues(): void {
    $schema = (new ArraySchema())->nonempty()->min(2)->length(3);

    $result = $schema->safeParse([]);

    $this->assertFalse($result->success);
    $this->assertCount(3, $result->issues);
    $this->assertSame('too_small', $result->issues[0]->code);
    $this->assertSame('too_small', $result->issues[1]->code);
    $this->assertSame('invalid_length', $result->issues[2]->code);
  }

  public function test_rules_WithMaxAndLengthFailing_ShouldReportBothIssues(): void {
    $schema = (new ArraySchema())->max(1)->length(1);

    $result = $schema->safeParse([1, 2]);

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame('too_big', $result->issues[0]->code);
    $this->assertSame('invalid_length', $result->issues[1]->code);
  }

  public function test_rules_WithMinAndMaxRange_ShouldRespectBothBounds(): void {
    $schema = (new ArraySchema())->min(1)->max(3);

    $this->assertFalse($schema->safeParse([])->success);
    $this->assertTrue($schema->safeParse([1])->success);
    $this->assertTrue($schema->safeParse([1, 2, 3])->success);
    $this->assertFalse($schema->safeParse([1, 2, 3, 4])->success);
  }

  public function test_refine_WithFailingCheck_ShouldFailWithCustomCode(): void {
    $schema = (new ArraySchema(new IntegerSchema()))->refine(
      fn(mixed $value, array $params): bool => count(array_unique($value)) === count($value),
      'Items must be unique'
    );

    $result = $schema->safeParse([1, 2, 1]);

    $this->assertFalse($result->success);
    $this->assertSame('custom', $result->issues[0]->code);
    $this->assertSame('Items must be unique', $result->issues[0]->message);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_refine_WhenFailing_ShouldNotValidateElements(): void {
    $schema = (new ArraySchema(new IntegerSchema()))->refine(
      fn(mixed $value, array $params): bool => false,
      'Always fails'
    );

    $result = $schema->safeParse(['a']);

    $this->assertCount(1, $result->issues);
    $this->assertSame('custom', $result->issues[0]->code);
  }

  public function test_optional_WithNonNullInvalidValue_ShouldStillFail(): void {
    $schema = (new ArraySchema())->optional();

    $result = $schema->safeParse('not an array');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_optional_WithEmptyArray_ShouldReturnEmptyArray(): void {
    $schema = (new ArraySchema())->optional();

    $result = $schema->safeParse([]);

    $this->assertTrue($result->success);
    $this->assertSame([], $result->data);
  }

  public function test_default_WithClosure_ShouldUseClosureResult(): void {
    $schema = (new ArraySchema(new IntegerSchema()))->_default(fn(): array => [7, 8]);

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame([7, 8], $result->data);
  }

  public function test_default_WithInvalidDefault_ShouldValidateDefaultElements(): void {
    $schema = (new ArraySchema(new IntegerSchema()))->_default(['x']);

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame([0], $result->issues[0]->path);
  }

  public function test_default_WithNonNullValue_ShouldIgnoreDefault(): void {
    $schema = (new ArraySchema(new IntegerSchema()))->_default([1]);

    $result = $schema->safeParse([2, 3]);

    $this->assertTrue($result->success);
    $this->assertSame([2, 3], $result->data);
  }

  public function test_clone_ShouldDeepCloneElementSchema(): void {
    $schema = new ArraySchema(new IntegerSchema());
    $property = new \ReflectionProperty(ArraySchema::class, 'elementSchema');

    $cloned = clone $schema;

    $this->assertNotSame($property->getValue($schema), $property->getValue($cloned));
    $this->assertEquals($property->getValue($schema), $property->getValue($cloned));
  }

  public function test_clone_WithoutElementSchema_ShouldKeepNull(): void {
    $schema = new ArraySchema();
    $property = new \ReflectionProperty(ArraySchema::class, 'elementSchema');

    $cloned = clone $schema;

    $this->assertNull($property->getValue($cloned));
    $this->assertTrue($cloned->safeParse(['anything', 1])->success);
  }

  public function test_clone_FluentChangesOnDerivedSchema_ShouldNotAffectOriginal(): void {
    $base = new ArraySchema((new IntegerSchema())->min(0));

    $derived = $base->max(1)->nonempty()->coerce()->transform(fn(array $v): int => count($v));

    $this->assertTrue($derived->safeParse('[5]')->success);
    $this->assertSame(1, $derived->safeParse('[5]')->data);
    $this->assertTrue($base->safeParse([])->success);
    $this->assertSame([1, 2, 3], $base->safeParse([1, 2, 3])->data);
    $this->assertFalse($base->safeParse('[5]')->success);
    $this->assertFalse($base->safeParse([-1])->success);
  }

  public function test_parse_WithValidArray_ShouldReturnParsedData(): void {
    $schema = new ArraySchema((new IntegerSchema())->coerce());

    $data = $schema->parse(['1', 2]);

    $this->assertSame([1, 2], $data);
  }

  public function test_parse_WithInvalidElements_ShouldThrowWithAllIssues(): void {
    $schema = new ArraySchema(new IntegerSchema());

    try {
      $schema->parse(['a', 1, 'b']);
      $this->fail('Expected ValidatorException');
    } catch (ValidatorException $e) {
      $issues = $e->getIssues();

      $this->assertCount(2, $issues);
      $this->assertSame([0], $issues[0]->path);
      $this->assertSame([2], $issues[1]->path);
    }
  }

  public function test_isValid_WithValidAndInvalidArrays_ShouldReturnBool(): void {
    $schema = new ArraySchema(new StringSchema());

    $this->assertTrue($schema->isValid(['a', 'b']));
    $this->assertTrue($schema->isValid([]));
    $this->assertFalse($schema->isValid(['a', 1]));
    $this->assertFalse($schema->isValid(['k' => 'a']));
    $this->assertFalse($schema->isValid(null));
  }

  public function test_factory_WithElementSchema_ShouldBuildEquivalentSchema(): void {
    $schema = V::_array(V::int());

    $this->assertInstanceOf(ArraySchema::class, $schema);
    $this->assertTrue($schema->safeParse([1, 2])->success);
    $this->assertSame([1], $schema->safeParse([1, 'x'])->issues[0]->path);
  }

  public function test_factory_WithoutElementSchema_ShouldAcceptAnyElements(): void {
    $schema = V::_array();

    $this->assertTrue($schema->safeParse([1, 'a', null])->success);
  }
}
