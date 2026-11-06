<?php

namespace Esliph\Validator\Tests\Schemas;

use PHPUnit\Framework\Attributes\DataProvider;

use Esliph\Validator\V;
use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\Schemas\EnumSchema;
use Esliph\Validator\Tests\Fixtures\EmptyBacked;
use Esliph\Validator\Tests\Fixtures\IntBacked;
use Esliph\Validator\Tests\Fixtures\StringBacked;
use Esliph\Validator\Tests\Fixtures\UnitOnly;

use Closure;
use InvalidArgumentException;
use Override;
use stdClass;

class EnumSchemaTest extends BaseSchemaTestCase {

  #[Override]
  protected function createSchema(): Schema {
    return new EnumSchema(['a', 'b', 1]);
  }

  #[Override]
  protected function getValidValue(): mixed {
    return 'a';
  }

  #[Override]
  protected function getInvalidValue(): mixed {
    return 'c';
  }

  #[Override]
  protected function getDefaultValue(): mixed {
    return 'b';
  }

  #[Override]
  protected function getTransformFunction(): Closure {
    return fn(mixed $value): mixed => $value;
  }

  #[Override]
  protected function getRefineCheck(): Closure {
    return fn(mixed $value, array $params): bool => $value !== 'c';
  }

  /**
   * @return array<string, array{array<mixed>}>
   */
  public static function provideInvalidLists(): array {
    return [
      'empty' => [[]],
      'null' => [[null]],
      'nested_array' => [[[1]]],
      'object' => [[new stdClass()]],
      'nan' => [[NAN]],
      'inf' => [[INF]],
      'enum_case' => [[StringBacked::Active]],
    ];
  }

  /**
   * @return array<string, array{string}>
   */
  public static function provideInvalidEnumClasses(): array {
    return [
      'missing_class' => ['Esliph\Validator\Tests\Fixtures\Missing'],
      'not_an_enum' => [stdClass::class],
      'unit_enum' => [UnitOnly::class],
      'enum_without_cases' => [EmptyBacked::class],
    ];
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithAllInputTypes_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = new EnumSchema(['abc', 0, true]);

    $result = $schema->safeParse($value);

    if ($value === 'abc' || $value === 0 || $value === true) {
      $this->assertTrue($result->success, "Type $typeName should be valid");
      $this->assertSame($value, $result->data);
      return;
    }

    $this->assertFalse($result->success, "Type $typeName should be invalid");
    $this->assertCount(1, $result->issues);

    if ($value === null) {
      $this->assertSame('required', $result->issues[0]->code);
      return;
    }

    $this->assertSame('invalid_enum_value', $result->issues[0]->code);
    $this->assertSame("Expected one of 'abc', 0, true", $result->issues[0]->message);
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithAllInputTypesAgainstIntBackedEnum_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = new EnumSchema(IntBacked::class);

    $result = $schema->safeParse($value);

    if ($value === 0 || $value === 42) {
      $this->assertTrue($result->success, "Type $typeName should be valid");
      $this->assertSame(IntBacked::from($value), $result->data);
      return;
    }

    $this->assertFalse($result->success, "Type $typeName should be invalid");
    $this->assertCount(1, $result->issues);

    if ($value === null) {
      $this->assertSame('required', $result->issues[0]->code);
      return;
    }

    $this->assertSame('invalid_enum_value', $result->issues[0]->code);
    $this->assertSame('Expected one of 0, 42', $result->issues[0]->message);
  }

  public function test_parseType_WithIntValue_ShouldRejectNumericStringAndFloat(): void {
    $schema = V::enum([1]);

    $this->assertFalse($schema->safeParse('1')->success);
    $this->assertFalse($schema->safeParse(1.0)->success);
  }

  public function test_parseType_WithFloatValue_ShouldAcceptSameFloat(): void {
    $result = V::enum([1.5])->safeParse(1.5);

    $this->assertTrue($result->success);
    $this->assertSame(1.5, $result->data);
  }

  public function test_construct_WithAssociativeArray_ShouldDiscardKeys(): void {
    $schema = V::enum(['x' => 'a', 'y' => 'b']);

    $result = $schema->safeParse('a');

    $this->assertSame(['a', 'b'], $schema->getValues());
    $this->assertTrue($result->success);
    $this->assertSame('a', $result->data);
  }

  /**
   * @param array<mixed> $values
   */
  #[DataProvider('provideInvalidLists')]
  public function test_construct_WithInvalidList_ShouldThrowInvalidArgumentException(array $values): void {
    $this->expectException(InvalidArgumentException::class);

    new EnumSchema($values);
  }

  #[DataProvider('provideInvalidEnumClasses')]
  public function test_construct_WithInvalidEnumClass_ShouldThrowInvalidArgumentException(string $class): void {
    $this->expectException(InvalidArgumentException::class);

    new EnumSchema($class); // @phpstan-ignore argument.type
  }

  public function test_parseType_WithIntBackedEnumAndNumericString_ShouldFail(): void {
    $result = V::enum(IntBacked::class)->safeParse('42');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_enum_value', $result->issues[0]->code);
  }

  public function test_parseType_WithStringBackedEnum_ShouldReturnCase(): void {
    $schema = V::enum(StringBacked::class);

    $fromValue = $schema->safeParse('active');
    $fromCase = $schema->safeParse(StringBacked::Inactive);
    $invalid = $schema->safeParse(1);

    $this->assertSame(StringBacked::Active, $fromValue->data);
    $this->assertSame(StringBacked::Inactive, $fromCase->data);
    $this->assertFalse($invalid->success);
    $this->assertSame("Expected one of 'active', 'inactive'", $invalid->issues[0]->message);
  }

  public function test_parseType_WithCaseOfAnotherEnum_ShouldFail(): void {
    $result = V::enum(IntBacked::class)->safeParse(StringBacked::Active);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_enum_value', $result->issues[0]->code);
  }

  public function test_getValues_WithBackedEnum_ShouldReturnBackingValues(): void {
    $this->assertSame([0, 42], V::enum(IntBacked::class)->getValues());
  }

  public function test_enum_FromFactory_ShouldReturnEnumSchema(): void {
    $this->assertInstanceOf(EnumSchema::class, V::enum(['a']));
  }
}
