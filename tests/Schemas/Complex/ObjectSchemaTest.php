<?php

namespace Esliph\Validator\Tests\Schemas\Complex;

use PHPUnit\Framework\Attributes\DataProvider;

use Esliph\Validator\Errors\ValidatorException;
use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Schemas\Complex\ArraySchema;
use Esliph\Validator\Schemas\Complex\ObjectSchema;
use Esliph\Validator\Schemas\Primitive\IntegerSchema;
use Esliph\Validator\Schemas\Primitive\StringSchema;

use Esliph\Validator\Tests\Schemas\BaseCoercibleSchemaTestCase;
use Esliph\Validator\V;

use Closure;
use InvalidArgumentException;
use LogicException;
use Override;

class ObjectSchemaTest extends BaseCoercibleSchemaTestCase {

  #[Override]
  protected function createSchemaCoercible(): CoercibleSchema {
    return new ObjectSchema([
      'name' => new StringSchema(),
      'age' => new IntegerSchema(),
    ]);
  }

  #[Override]
  protected function getValidValue(): mixed {
    return (object) ['name' => 'Dan', 'age' => 30];
  }

  #[Override]
  protected function getInvalidValue(): mixed {
    return 'not an object';
  }

  #[Override]
  protected function getDefaultValue(): mixed {
    return (object) ['name' => 'Default', 'age' => 1];
  }

  #[Override]
  protected function getTransformFunction(): Closure {
    return fn(mixed $value) => $value;
  }

  #[Override]
  protected function getRefineCheck(): Closure {
    return fn(mixed $value): bool => $value->age >= 18;
  }

  private function createUserSchema(): ObjectSchema {
    return new ObjectSchema([
      'name' => new StringSchema(),
      'age' => new IntegerSchema(),
    ]);
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithAllInputTypes_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = new ObjectSchema();

    $result = $schema->safeParse($value);

    $shouldBeValid = is_object($value);

    if ($shouldBeValid) {
      $this->assertTrue($result->success, "Type $typeName should be valid");
      $this->assertInstanceOf(\stdClass::class, $result->data);
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
    $schema = (new ObjectSchema())->coerce();

    $result = $schema->safeParse($value);

    $isNonEmptyAssociativeArray = is_array($value)
      && !empty($value)
      && array_keys($value) !== range(0, count($value) - 1);
    $shouldBeValid = is_object($value) || $isNonEmptyAssociativeArray;

    if ($shouldBeValid) {
      $this->assertTrue($result->success, "Type $typeName should coerce successfully");
      $this->assertInstanceOf(\stdClass::class, $result->data);
    } else {
      $this->assertFalse($result->success, "Type $typeName should fail coercion");

      if ($value === null || (is_string($value) && trim($value) === '')) {
        $this->assertSame('required', $result->issues[0]->code);
      } else {
        $this->assertSame('invalid_type', $result->issues[0]->code);
      }
    }
  }

  public function test_parseType_WithStdClass_ShouldAccept(): void {
    $schema = $this->createUserSchema();

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30]);

    $this->assertTrue($result->success);
    $this->assertEquals((object) ['name' => 'Dan', 'age' => 30], $result->data);
  }

  public function test_parseType_WithAssociativeArrayWithoutCoerce_ShouldFailWithInvalidType(): void {
    $schema = $this->createUserSchema();

    $result = $schema->safeParse(['name' => 'Dan', 'age' => 30]);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame([], $result->issues[0]->path);
    $this->assertSame('Expected object, received array', $result->issues[0]->message);
  }

  public function test_parseType_WithString_ShouldFailWithReceivedTypeInMessage(): void {
    $schema = $this->createUserSchema();

    $result = $schema->safeParse('abc');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected object, received string', $result->issues[0]->message);
  }

  public function test_parseType_WithCustomClassInstance_ShouldReturnStdClassWithPublicProperties(): void {
    $schema = new ObjectSchema(['name' => new StringSchema()]);
    $input = new class {
      public string $name = 'Dan';
      public int $extra = 7;
      private string $secret = 'hidden';
    };

    $result = $schema->safeParse($input);

    $this->assertTrue($result->success);
    $this->assertInstanceOf(\stdClass::class, $result->data);
    $this->assertEquals((object) ['name' => 'Dan'], $result->data);
  }

  public function test_coerce_WithAssociativeArray_ShouldConvertToObject(): void {
    $schema = $this->createUserSchema()->coerce();

    $result = $schema->safeParse(['name' => 'Dan', 'age' => 30]);

    $this->assertTrue($result->success);
    $this->assertInstanceOf(\stdClass::class, $result->data);
    $this->assertSame('Dan', $result->data->name);
    $this->assertSame(30, $result->data->age);
  }

  public function test_coerce_WithIndexedArray_ShouldFailWithInvalidType(): void {
    $schema = $this->createUserSchema()->coerce();

    $result = $schema->safeParse(['Dan', 30]);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected object or associative array, received list', $result->issues[0]->message);
  }

  public function test_coerce_WithEmptyArray_ShouldFailWithInvalidType(): void {
    $schema = (new ObjectSchema(['name' => (new StringSchema())->optional()]))->coerce();

    $result = $schema->safeParse([]);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithObject_ShouldKeepAcceptingObjects(): void {
    $schema = $this->createUserSchema()->coerce();

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30]);

    $this->assertTrue($result->success);
    $this->assertEquals((object) ['name' => 'Dan', 'age' => 30], $result->data);
  }

  public function test_coerce_WithJsonObjectString_ShouldDecodeToStdClass(): void {
    $schema = $this->createUserSchema()->coerce();

    $result = $schema->safeParse('{"name":"Dan","age":30}');

    $this->assertTrue($result->success);
    $this->assertInstanceOf(\stdClass::class, $result->data);
    $this->assertSame('Dan', $result->data->name);
    $this->assertSame(30, $result->data->age);
  }

  public function test_coerce_WithNestedJsonObject_ShouldValidateNestedStdClassWithoutChildCoerce(): void {
    $schema = (new ObjectSchema(['user' => new ObjectSchema(['id' => new IntegerSchema()])]))->coerce();

    $result = $schema->safeParse(' {"user":{"id":1}} ');

    $this->assertTrue($result->success);
    $this->assertInstanceOf(\stdClass::class, $result->data->user);
    $this->assertSame(1, $result->data->user->id);
  }

  public function test_coerce_WithNestedJsonObjectFailing_ShouldReportNestedPath(): void {
    $schema = (new ObjectSchema(['user' => new ObjectSchema(['id' => new IntegerSchema()])]))->coerce();

    $result = $schema->safeParse('{"user":{"id":"x"}}');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame(['user', 'id'], $result->issues[0]->path);
  }

  public function test_coerce_WithJsonListString_ShouldFailExpectedObject(): void {
    $result = $this->createUserSchema()->coerce()->safeParse('[{"a":1}]');

    $this->assertFalse($result->success);
    $this->assertSame('Expected object, received string', $result->issues[0]->message);
  }

  public function test_coerce_WithInvalidJson_ShouldFailExpectedObject(): void {
    $result = $this->createUserSchema()->coerce()->safeParse('{"a":');

    $this->assertFalse($result->success);
    $this->assertSame('Expected object, received string', $result->issues[0]->message);
  }

  public function test_coerce_WithAssociativeArrayDefault_ShouldFailExpectedObjectReceivedArray(): void {
    $schema = $this->createUserSchema()->coerce()->_default(['name' => 'Dan', 'age' => 30]);

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected object, received array', $result->issues[0]->message);
  }

  public function test_coerce_WithNestedAssociativeArrayAndNonCoercibleChild_ShouldFailAtNestedPath(): void {
    $schema = (new ObjectSchema([
      'address' => new ObjectSchema(['city' => new StringSchema()]),
    ]))->coerce();

    $result = $schema->safeParse(['address' => ['city' => 'Recife']]);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame(['address'], $result->issues[0]->path);
  }

  public function test_coerce_WithNestedAssociativeArrayAndCoercibleChild_ShouldConvertRecursively(): void {
    $schema = (new ObjectSchema([
      'address' => (new ObjectSchema(['city' => new StringSchema()]))->coerce(),
    ]))->coerce();

    $result = $schema->safeParse(['address' => ['city' => 'Recife']]);

    $this->assertTrue($result->success);
    $this->assertInstanceOf(\stdClass::class, $result->data->address);
    $this->assertSame('Recife', $result->data->address->city);
  }

  public function test_coerce_ShouldNotModifyOriginalSchema(): void {
    $base = $this->createUserSchema();

    $base->coerce();

    $this->assertFalse($base->isValid(['name' => 'Dan', 'age' => 30]));
  }

  public function test_validateType_WithMissingField_ShouldFailWithRequiredAtFieldPath(): void {
    $schema = $this->createUserSchema();

    $result = $schema->safeParse((object) ['name' => 'Dan']);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame(['age'], $result->issues[0]->path);
  }

  public function test_validateType_WithFieldExplicitlyNull_ShouldFailWithRequired(): void {
    $schema = $this->createUserSchema();

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => null]);

    $this->assertFalse($result->success);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame(['age'], $result->issues[0]->path);
  }

  public function test_validateType_WithFalsyFieldValues_ShouldNotTreatThemAsMissing(): void {
    $schema = new ObjectSchema([
      'count' => new IntegerSchema(),
      'label' => new StringSchema(),
    ]);

    $result = $schema->safeParse((object) ['count' => 0, 'label' => '']);

    $this->assertTrue($result->success);
    $this->assertSame(0, $result->data->count);
    $this->assertSame('', $result->data->label);
  }

  public function test_validateType_WithInvalidField_ShouldReportFieldPath(): void {
    $schema = $this->createUserSchema();

    $result = $schema->safeParse((object) ['name' => 123, 'age' => 30]);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame(['name'], $result->issues[0]->path);
  }

  public function test_validateType_WithMultipleInvalidFields_ShouldReportAllIssues(): void {
    $schema = $this->createUserSchema();

    $result = $schema->safeParse((object) ['name' => 123, 'age' => 'abc']);

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame(['name'], $result->issues[0]->path);
    $this->assertSame(['age'], $result->issues[1]->path);
  }

  public function test_validateType_WithEmptyObjectAndRequiredFields_ShouldReportEveryMissingField(): void {
    $schema = $this->createUserSchema();

    $result = $schema->safeParse(new \stdClass());

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame(['name'], $result->issues[0]->path);
    $this->assertSame('required', $result->issues[1]->code);
    $this->assertSame(['age'], $result->issues[1]->path);
  }

  public function test_validateType_WithFieldRulesFailing_ShouldReportEveryRuleIssueAtFieldPath(): void {
    $schema = new ObjectSchema([
      'email' => (new StringSchema())->min(10)->email(),
    ]);

    $result = $schema->safeParse((object) ['email' => 'abc']);

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame('too_small', $result->issues[0]->code);
    $this->assertSame(['email'], $result->issues[0]->path);
    $this->assertSame('invalid_format', $result->issues[1]->code);
    $this->assertSame(['email'], $result->issues[1]->path);
  }

  public function test_validateType_WithNestedInvalidField_ShouldReportFullPath(): void {
    $schema = new ObjectSchema([
      'address' => new ObjectSchema([
        'geo' => new ObjectSchema(['lat' => new IntegerSchema()]),
      ]),
    ]);

    $result = $schema->safeParse((object) [
      'address' => (object) ['geo' => (object) ['lat' => 'north']],
    ]);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame(['address', 'geo', 'lat'], $result->issues[0]->path);
  }

  public function test_validateType_WithObjectInsideArrayInsideObject_ShouldReportFullPath(): void {
    $schema = new ObjectSchema([
      'users' => new ArraySchema(new ObjectSchema([
        'email' => (new StringSchema())->email(),
      ])),
    ]);

    $result = $schema->safeParse((object) [
      'users' => [
        (object) ['email' => 'a@b.com'],
        (object) ['email' => 'invalid'],
      ],
    ]);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_format', $result->issues[0]->code);
    $this->assertSame(['users', 1, 'email'], $result->issues[0]->path);
  }

  public function test_validateType_WithInvalidFieldsAcrossNestedLevels_ShouldAggregateAllIssues(): void {
    $schema = new ObjectSchema([
      'name' => new StringSchema(),
      'address' => new ObjectSchema([
        'city' => new StringSchema(),
        'zip' => new IntegerSchema(),
      ]),
    ]);

    $result = $schema->safeParse((object) [
      'name' => 1,
      'address' => (object) ['city' => 2],
    ]);

    $this->assertFalse($result->success);
    $this->assertCount(3, $result->issues);
    $this->assertSame(['name'], $result->issues[0]->path);
    $this->assertSame(['address', 'city'], $result->issues[1]->path);
    $this->assertSame('invalid_type', $result->issues[1]->code);
    $this->assertSame(['address', 'zip'], $result->issues[2]->path);
    $this->assertSame('required', $result->issues[2]->code);
  }

  public function test_validateType_WithChildTransformsAndCoercion_ShouldReturnParsedChildValues(): void {
    $schema = new ObjectSchema([
      'name' => (new StringSchema())->trim()->toUpperCase(),
      'age' => (new IntegerSchema())->coerce(),
    ]);

    $result = $schema->safeParse((object) ['name' => '  dan  ', 'age' => '42']);

    $this->assertTrue($result->success);
    $this->assertSame('DAN', $result->data->name);
    $this->assertSame(42, $result->data->age);
  }

  public function test_validateType_WithValidInput_ShouldNotMutateInputObject(): void {
    $schema = new ObjectSchema(['name' => (new StringSchema())->trim()]);
    $input = (object) ['name' => '  dan  '];

    $result = $schema->safeParse($input);

    $this->assertTrue($result->success);
    $this->assertNotSame($input, $result->data);
    $this->assertSame('  dan  ', $input->name);
    $this->assertSame('dan', $result->data->name);
  }

  public function test_validateType_WithMissingFieldWithDefault_ShouldUseDefault(): void {
    $schema = new ObjectSchema([
      'role' => (new StringSchema())->_default('user'),
    ]);

    $result = $schema->safeParse(new \stdClass());

    $this->assertTrue($result->success);
    $this->assertSame('user', $result->data->role);
  }

  public function test_validateType_WithNullFieldWithClosureDefault_ShouldUseDefaultResult(): void {
    $schema = new ObjectSchema([
      'role' => (new StringSchema())->_default(fn() => 'guest'),
    ]);

    $result = $schema->safeParse((object) ['role' => null]);

    $this->assertTrue($result->success);
    $this->assertSame('guest', $result->data->role);
  }

  public function test_validateType_WithMissingOptionalField_ShouldOmitProperty(): void {
    $schema = new ObjectSchema([
      'name' => new StringSchema(),
      'nickname' => (new StringSchema())->optional(),
    ]);

    $result = $schema->safeParse((object) ['name' => 'Dan']);

    $this->assertTrue($result->success);
    $this->assertSame('Dan', $result->data->name);
    $this->assertObjectNotHasProperty('nickname', $result->data);
  }

  public function test_validateType_WithOptionalFieldExplicitlyNull_ShouldKeepNull(): void {
    $schema = V::object(['f' => V::string()->optional()]);

    $result = $schema->safeParse((object) ['f' => null]);

    $this->assertTrue($result->success);
    $this->assertObjectHasProperty('f', $result->data);
    $this->assertNull($result->data->f);
  }

  public function test_validateType_WithMissingNullField_ShouldOmitProperty(): void {
    $result = V::object(['f' => V::null()])->safeParse(new \stdClass());

    $this->assertTrue($result->success);
    $this->assertObjectNotHasProperty('f', $result->data);
  }

  public function test_validateType_WithMissingNullableUnionField_ShouldOmitProperty(): void {
    $result = V::object(['f' => V::union(V::string(), V::null())])->safeParse(new \stdClass());

    $this->assertTrue($result->success);
    $this->assertObjectNotHasProperty('f', $result->data);
  }

  public function test_validateType_WithMissingUnionFieldWithDefaultMember_ShouldUseDefault(): void {
    $result = V::object(['f' => V::union(V::int()->_default(5), V::string())])->safeParse(new \stdClass());

    $this->assertTrue($result->success);
    $this->assertSame(5, $result->data->f);
  }

  public function test_validateType_WithFieldOnlyViaMagicGet_ShouldTreatAsMissing(): void {
    $input = new class {
      public function __get(string $name): mixed {
        return 'x';
      }

      public function __isset(string $name): bool {
        return true;
      }
    };

    $result = V::object(['f' => V::string()])->safeParse($input);

    $this->assertFalse($result->success);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame(['f'], $result->issues[0]->path);
  }

  public function test_validateType_WithDeclaredPublicProperty_ShouldReadIt(): void {
    $input = new class {
      public string $f = 'x';
    };

    $result = V::object(['f' => V::string()])->safeParse($input);

    $this->assertTrue($result->success);
    $this->assertSame('x', $result->data->f);
  }

  public function test_validateType_WithPrivateProperty_ShouldNotReadIt(): void {
    $input = new class {
      private string $f = 'x';

      public function get(): string {
        return $this->f;
      }
    };

    $result = V::object(['f' => V::string()->optional()])->safeParse($input);

    $this->assertTrue($result->success);
    $this->assertObjectNotHasProperty('f', $result->data);
    $this->assertSame('x', $input->get());
  }

  public function test_validateType_WithIntegerKey_ShouldReadNormalizedKey(): void {
    $result = (new ObjectSchema([0 => new StringSchema()]))->safeParse((object) ['0' => 'a']);

    $this->assertTrue($result->success);
    $this->assertSame('a', $result->data->{'0'});
  }

  public function test_coerce_WithJsonIntegerKey_ShouldReadField(): void {
    $result = (new ObjectSchema([0 => new StringSchema()]))->coerce()->safeParse('{"0":"a"}');

    $this->assertTrue($result->success);
    $this->assertSame('a', $result->data->{'0'});
  }

  public function test_strict_WithJsonIntegerUnknownKey_ShouldReportIntPath(): void {
    $result = (new ObjectSchema([0 => new StringSchema()]))->strict()->coerce()->safeParse('{"0":"a","1":"b"}');

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('unrecognized_keys', $result->issues[0]->code);
    $this->assertSame([1], $result->issues[0]->path);
  }

  public function test_validateType_WithArrayObjectAsProps_ShouldTreatFieldAsMissing(): void {
    $input = new \ArrayObject(['f' => 'x'], \ArrayObject::ARRAY_AS_PROPS);

    $result = V::object(['f' => V::string()])->safeParse($input);

    $this->assertFalse($result->success);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame(['f'], $result->issues[0]->path);
  }

  public function test_validateType_WithUninitializedTypedProperty_ShouldTreatAsMissing(): void {
    $input = new class {
      public string $f;
    };

    $required = V::object(['f' => V::string()])->safeParse($input);
    $optional = V::object(['f' => V::string()->optional()])->safeParse($input);

    $this->assertFalse($required->success);
    $this->assertSame('required', $required->issues[0]->code);
    $this->assertSame(['f'], $required->issues[0]->path);
    $this->assertTrue($optional->success);
    $this->assertObjectNotHasProperty('f', $optional->data);
  }

  public function test_refine_WithMissingOptionalReadWithCoalesce_ShouldGetFallback(): void {
    $schema = V::object(['nick' => V::string()->optional()])
      ->refine(fn(object $o): bool => ($o->nick ?? 'anon') === 'anon');

    $this->assertTrue($schema->safeParse(new \stdClass())->success);
  }

  public function test_validateType_WithEmptyShape_ShouldAcceptAnyObject(): void {
    $schema = new ObjectSchema();

    $result = $schema->safeParse((object) ['anything' => [1, 2, 3]]);

    $this->assertTrue($result->success);
    $this->assertEquals(new \stdClass(), $result->data);
  }

  public function test_validateType_WithEmptyShapeAndPassthrough_ShouldKeepEveryKey(): void {
    $schema = (new ObjectSchema())->passthrough();

    $result = $schema->safeParse((object) ['anything' => [1, 2, 3]]);

    $this->assertTrue($result->success);
    $this->assertSame([1, 2, 3], $result->data->anything);
  }

  public function test_strip_ByDefault_ShouldDropUnknownKeys(): void {
    $result = $this->createUserSchema()->safeParse((object) ['name' => 'Dan', 'age' => 30, 'extra' => 'x']);

    $this->assertTrue($result->success);
    $this->assertObjectNotHasProperty('extra', $result->data);
  }

  public function test_strip_AfterPassthrough_ShouldDropUnknownKeys(): void {
    $schema = $this->createUserSchema()->passthrough()->strip();

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'extra' => 'x']);

    $this->assertTrue($result->success);
    $this->assertObjectNotHasProperty('extra', $result->data);
  }

  public function test_strip_AfterStrict_ShouldAcceptAndDropUnknownKeys(): void {
    $schema = $this->createUserSchema()->strict()->strip();

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'extra' => 'x']);

    $this->assertTrue($result->success);
    $this->assertObjectNotHasProperty('extra', $result->data);
  }

  public function test_strip_AfterCatchall_ShouldNotValidateUnknownKeys(): void {
    $schema = $this->createUserSchema()->catchall(new IntegerSchema())->strip();

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'extra' => 'not int']);

    $this->assertTrue($result->success);
    $this->assertObjectNotHasProperty('extra', $result->data);
  }

  public function test_strict_AfterStrip_ShouldReportOneIssuePerKey(): void {
    $schema = $this->createUserSchema()->strip()->strict();

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'x' => 1, 'y' => 2]);

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame('unrecognized_keys', $result->issues[0]->code);
    $this->assertSame(['x'], $result->issues[0]->path);
    $this->assertSame('unrecognized_keys', $result->issues[1]->code);
    $this->assertSame(['y'], $result->issues[1]->path);
  }

  public function test_passthrough_ShouldCopyUnknownKeysAfterShapeFieldsInInputOrder(): void {
    $schema = $this->createUserSchema()->passthrough();

    $result = $schema->safeParse((object) ['z' => 1, 'name' => 'Dan', 'a' => 2, 'age' => 30]);

    $this->assertTrue($result->success);
    $this->assertSame(['name', 'age', 'z', 'a'], array_keys(get_object_vars($result->data)));
  }

  public function test_refine_ShouldNotReceiveUnknownKeys(): void {
    $received = null;
    $schema = $this->createUserSchema()->refine(function (mixed $value) use (&$received): bool {
      $received = $value;

      return true;
    });

    $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'extra' => 'x']);

    $this->assertObjectNotHasProperty('extra', $received);
  }

  public function test_strip_ShouldNotModifyOriginalSchema(): void {
    $base = $this->createUserSchema();
    $base->passthrough()->strip();
    $p = $base->passthrough();
    $input = (object) ['name' => 'Dan', 'age' => 30, 'extra' => 'x'];

    $this->assertObjectNotHasProperty('extra', $base->parse($input));
    $this->assertSame('x', $p->parse($input)->extra);
  }

  public function test_passthrough_ShouldCopyUnknownKeysUntouched(): void {
    $schema = $this->createUserSchema()->passthrough();
    $extra = (object) ['nested' => true];

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'extra' => $extra, 'nothing' => null]);

    $this->assertTrue($result->success);
    $this->assertSame($extra, $result->data->extra);
    $this->assertTrue(property_exists($result->data, 'nothing'));
    $this->assertNull($result->data->nothing);
  }

  public function test_passthrough_AfterStrict_ShouldAcceptUnknownKeysAgain(): void {
    $schema = $this->createUserSchema()->strict()->passthrough();

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'extra' => 'x']);

    $this->assertTrue($result->success);
    $this->assertSame('x', $result->data->extra);
  }

  public function test_passthrough_AfterCatchall_ShouldStopValidatingUnknownKeys(): void {
    $schema = $this->createUserSchema()->catchall(new IntegerSchema())->passthrough();

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'extra' => 'not int']);

    $this->assertTrue($result->success);
    $this->assertSame('not int', $result->data->extra);
  }

  public function test_strict_WithUnknownKey_ShouldFailWithUnrecognizedKeys(): void {
    $schema = $this->createUserSchema()->strict();

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'extra' => 'x']);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('unrecognized_keys', $result->issues[0]->code);
    $this->assertSame(['extra'], $result->issues[0]->path);
    $this->assertSame('Unrecognized key in object', $result->issues[0]->message);
  }

  public function test_strict_WithMultipleUnknownKeys_ShouldReportOneIssuePerKey(): void {
    $schema = $this->createUserSchema()->strict();

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'a' => 1, 'b' => 2]);

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame(['a'], $result->issues[0]->path);
    $this->assertSame(['b'], $result->issues[1]->path);
  }

  public function test_strict_WithOnlyKnownKeys_ShouldSucceed(): void {
    $schema = $this->createUserSchema()->strict();

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30]);

    $this->assertTrue($result->success);
    $this->assertEquals((object) ['name' => 'Dan', 'age' => 30], $result->data);
  }

  public function test_strict_WithInvalidFieldAndUnknownKey_ShouldReportBothIssues(): void {
    $schema = $this->createUserSchema()->strict();

    $result = $schema->safeParse((object) ['name' => 1, 'age' => 30, 'extra' => 'x']);

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame(['name'], $result->issues[0]->path);
    $this->assertSame('unrecognized_keys', $result->issues[1]->code);
    $this->assertSame(['extra'], $result->issues[1]->path);
  }

  public function test_strict_WithNestedUnknownKey_ShouldReportFullPath(): void {
    $schema = new ObjectSchema([
      'address' => (new ObjectSchema(['city' => new StringSchema()]))->strict(),
    ]);

    $result = $schema->safeParse((object) [
      'address' => (object) ['city' => 'Recife', 'country' => 'BR'],
    ]);

    $this->assertFalse($result->success);
    $this->assertSame('unrecognized_keys', $result->issues[0]->code);
    $this->assertSame(['address', 'country'], $result->issues[0]->path);
  }

  public function test_strict_WithKnownKeyExplicitlyNullOnOptionalField_ShouldNotReportUnrecognized(): void {
    $schema = (new ObjectSchema(['nickname' => (new StringSchema())->optional()]))->strict();

    $result = $schema->safeParse((object) ['nickname' => null]);

    $this->assertTrue($result->success);
  }

  public function test_strict_AfterCatchall_ShouldOverrideCatchall(): void {
    $schema = $this->createUserSchema()->catchall(new IntegerSchema())->strict();

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'extra' => 1]);

    $this->assertFalse($result->success);
    $this->assertSame('unrecognized_keys', $result->issues[0]->code);
    $this->assertSame(['extra'], $result->issues[0]->path);
  }

  public function test_strict_ShouldNotModifyOriginalSchema(): void {
    $base = $this->createUserSchema();

    $base->strict();

    $this->assertTrue($base->isValid((object) ['name' => 'Dan', 'age' => 30, 'extra' => 'x']));
  }

  public function test_catchall_WithValidUnknownKey_ShouldIncludeParsedValue(): void {
    $schema = $this->createUserSchema()->catchall((new IntegerSchema())->coerce());

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'score' => '99']);

    $this->assertTrue($result->success);
    $this->assertSame(99, $result->data->score);
  }

  public function test_catchall_WithInvalidUnknownKey_ShouldFailAtKeyPath(): void {
    $schema = $this->createUserSchema()->catchall(new IntegerSchema());

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'score' => 'high']);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame(['score'], $result->issues[0]->path);
  }

  public function test_catchall_WithMultipleInvalidUnknownKeys_ShouldReportAllIssues(): void {
    $schema = $this->createUserSchema()->catchall(new IntegerSchema());

    $result = $schema->safeParse((object) ['name' => 1, 'age' => 30, 'a' => 'x', 'b' => 'y']);

    $this->assertFalse($result->success);
    $this->assertCount(3, $result->issues);
    $this->assertSame(['name'], $result->issues[0]->path);
    $this->assertSame(['a'], $result->issues[1]->path);
    $this->assertSame(['b'], $result->issues[2]->path);
  }

  public function test_catchall_ShouldNotApplyToKnownKeys(): void {
    $schema = $this->createUserSchema()->catchall(new IntegerSchema());

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30]);

    $this->assertTrue($result->success);
    $this->assertSame('Dan', $result->data->name);
  }

  public function test_catchall_WithNullUnknownValueAndRequiredCatchall_ShouldFailWithRequired(): void {
    $schema = $this->createUserSchema()->catchall(new IntegerSchema());

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'extra' => null]);

    $this->assertFalse($result->success);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame(['extra'], $result->issues[0]->path);
  }

  public function test_catchall_WithNestedObject_ShouldReportFullPath(): void {
    $schema = new ObjectSchema([
      'meta' => (new ObjectSchema())->catchall(new IntegerSchema()),
    ]);

    $result = $schema->safeParse((object) ['meta' => (object) ['views' => 'many']]);

    $this->assertFalse($result->success);
    $this->assertSame(['meta', 'views'], $result->issues[0]->path);
  }

  public function test_catchall_AfterStrict_ShouldValidateUnknownKeysWithCatchall(): void {
    $schema = $this->createUserSchema()->strict()->catchall(new IntegerSchema());

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'extra' => 1]);

    $this->assertTrue($result->success);
    $this->assertSame(1, $result->data->extra);
  }

  public function test_catchall_ShouldNotModifyOriginalSchema(): void {
    $base = $this->createUserSchema();

    $base->catchall(new IntegerSchema());

    $this->assertTrue($base->isValid((object) ['name' => 'Dan', 'age' => 30, 'extra' => 'x']));
  }

  public function test_shape_WithNewShape_ShouldReplaceShape(): void {
    $schema = $this->createUserSchema()->shape(['id' => new IntegerSchema()]);

    $result = $schema->safeParse((object) ['id' => 1]);

    $this->assertTrue($result->success);
    $this->assertEquals((object) ['id' => 1], $result->data);
  }

  public function test_shape_ShouldNotModifyOriginalSchema(): void {
    $base = $this->createUserSchema();

    $base->shape(['id' => new IntegerSchema()]);

    $this->assertFalse($base->isValid((object) ['id' => 1]));
  }

  public function test_pick_WithExistingKeys_ShouldKeepOnlyPickedFields(): void {
    $schema = $this->createUserSchema()->pick(['name']);

    $result = $schema->safeParse((object) ['name' => 'Dan']);

    $this->assertTrue($result->success);
    $this->assertSame('Dan', $result->data->name);
  }

  public function test_pick_WithUnknownKey_ShouldThrow(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("unknown key 'doesNotExist' in object shape");

    $this->createUserSchema()->pick(['name', 'doesNotExist']);
  }

  public function test_pick_WithKeysOutOfOrder_ShouldFollowShapeOrder(): void {
    $schema = $this->createUserSchema()->pick(['age', 'name']);

    $this->assertSame(['name', 'age'], array_keys($schema->getShape()));
  }

  public function test_pick_WithEmptyList_ShouldReturnEmptyShape(): void {
    $schema = $this->createUserSchema()->pick([]);

    $this->assertSame([], $schema->getShape());
  }

  public function test_pick_OnStrictSchema_ShouldKeepStrictAndRejectNonPickedKeys(): void {
    $schema = $this->createUserSchema()->strict()->pick(['name']);

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30]);

    $this->assertFalse($result->success);
    $this->assertSame('unrecognized_keys', $result->issues[0]->code);
    $this->assertSame(['age'], $result->issues[0]->path);
  }

  public function test_pick_ShouldNotModifyOriginalSchema(): void {
    $base = $this->createUserSchema();

    $base->pick(['name']);

    $result = $base->safeParse((object) ['name' => 'Dan']);
    $this->assertFalse($result->success);
    $this->assertSame(['age'], $result->issues[0]->path);
  }

  public function test_omit_WithExistingKey_ShouldRemoveField(): void {
    $schema = $this->createUserSchema()->omit(['age']);

    $result = $schema->safeParse((object) ['name' => 'Dan']);

    $this->assertTrue($result->success);
    $this->assertEquals((object) ['name' => 'Dan'], $result->data);
  }

  public function test_omit_WithUnknownKey_ShouldThrow(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("unknown key 'doesNotExist' in object shape");

    $this->createUserSchema()->omit(['doesNotExist']);
  }

  public function test_omit_WithEmptyList_ShouldKeepShape(): void {
    $schema = $this->createUserSchema()->omit([]);

    $this->assertSame(['name', 'age'], array_keys($schema->getShape()));
  }

  public function test_construct_WithNonSchemaValue_ShouldThrow(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("object shape value for key 'age' must be a Schema");

    // @phpstan-ignore argument.type
    new ObjectSchema(['name' => new StringSchema(), 'age' => 'int']);
  }

  public function test_shape_WithNonSchemaValue_ShouldThrow(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("object shape value for key 'age' must be a Schema");

    // @phpstan-ignore argument.type
    $this->createUserSchema()->shape(['name' => new StringSchema(), 'age' => 'int']);
  }

  public function test_extend_WithNonSchemaValue_ShouldThrow(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("object shape value for key 'age' must be a Schema");

    // @phpstan-ignore argument.type
    $this->createUserSchema()->extend(['name' => new StringSchema(), 'age' => 'int']);
  }

  public function test_getShape_ShouldReturnCurrentShape(): void {
    $name = new StringSchema();

    $this->assertSame(['name' => $name], (new ObjectSchema(['name' => $name]))->getShape());
  }

  /**
   * @return iterable<string, array{Closure(ObjectSchema): ObjectSchema}>
   */
  public static function provideDerivations(): iterable {
    yield 'pick' => [fn(ObjectSchema $s) => $s->pick(['name'])];
    yield 'omit' => [fn(ObjectSchema $s) => $s->omit(['age'])];
    yield 'extend' => [fn(ObjectSchema $s) => $s->extend(['x' => new StringSchema()])];
    yield 'merge' => [fn(ObjectSchema $s) => $s->merge(new ObjectSchema(['x' => new StringSchema()]))];
    yield 'partial' => [fn(ObjectSchema $s) => $s->partial()];
    yield 'required' => [fn(ObjectSchema $s) => $s->required()];
    yield 'shape' => [fn(ObjectSchema $s) => $s->shape(['x' => new StringSchema()])];
  }

  /**
   * @param Closure(ObjectSchema): ObjectSchema $derive
   */
  #[DataProvider('provideDerivations')]
  public function test_derive_AfterRefine_ShouldThrowLogicException(Closure $derive): void {
    $base = $this->createUserSchema()->refine(fn(mixed $v): bool => true);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('cannot derive an object schema after refine() or transform()');

    $derive($base);
  }

  /**
   * @param Closure(ObjectSchema): ObjectSchema $derive
   */
  #[DataProvider('provideDerivations')]
  public function test_derive_AfterTransform_ShouldThrowLogicException(Closure $derive): void {
    $base = $this->createUserSchema()->transform(fn(mixed $v): mixed => $v);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('cannot derive an object schema after refine() or transform()');

    $derive($base);
  }

  /**
   * @return iterable<string, array{Closure(ObjectSchema): ObjectSchema}>
   */
  public static function provideOtherDerivedObjects(): iterable {
    yield 'refine' => [fn(ObjectSchema $s) => $s->refine(fn(mixed $v): bool => true)];
    yield 'transform' => [fn(ObjectSchema $s) => $s->transform(fn(mixed $v): mixed => $v)];
    yield 'asArray' => [fn(ObjectSchema $s) => $s->asArray()];
  }

  /**
   * @param Closure(ObjectSchema): ObjectSchema $finalize
   */
  #[DataProvider('provideOtherDerivedObjects')]
  public function test_merge_WithOtherAfterRefineOrTransform_ShouldThrowLogicException(Closure $finalize): void {
    $other = $finalize(new ObjectSchema(['x' => new StringSchema()]));

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('cannot merge an object schema that has refine() or transform()');

    $this->createUserSchema()->merge($other);
  }

  public function test_pick_AfterAsArray_ShouldThrowLogicException(): void {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('cannot derive an object schema after refine() or transform()');

    $this->createUserSchema()->asArray()->pick(['name']);
  }

  public function test_optional_AfterRefine_ShouldStillWork(): void {
    $schema = $this->createUserSchema()->refine(fn(mixed $v): bool => true)->optional();

    $this->assertTrue($schema->safeParse(null)->success);
  }

  public function test_derive_WithRefinedChild_ShouldNotThrow(): void {
    $schema = new ObjectSchema([
      'name' => (new StringSchema())->refine(fn(mixed $v): bool => true),
      'age' => new IntegerSchema(),
    ]);

    $result = $schema->pick(['name'])->safeParse((object) ['name' => 'Dan']);

    $this->assertTrue($result->success);
  }

  public function test_omit_ShouldNotModifyOriginalSchema(): void {
    $base = $this->createUserSchema();

    $base->omit(['age']);

    $this->assertFalse($base->isValid((object) ['name' => 'Dan']));
  }

  public function test_extend_WithNewField_ShouldRequireIt(): void {
    $schema = $this->createUserSchema()->extend(['email' => (new StringSchema())->email()]);

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30]);

    $this->assertFalse($result->success);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame(['email'], $result->issues[0]->path);
  }

  public function test_extend_WithExistingKey_ShouldOverrideFieldSchema(): void {
    $schema = $this->createUserSchema()->extend(['age' => new StringSchema()]);

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 'thirty']);

    $this->assertTrue($result->success);
    $this->assertSame('thirty', $result->data->age);
  }

  public function test_extend_OnStrictSchema_ShouldKeepStrict(): void {
    $schema = $this->createUserSchema()->strict()->extend(['email' => new StringSchema()]);

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'email' => 'a@b.com', 'x' => 1]);

    $this->assertFalse($result->success);
    $this->assertSame('unrecognized_keys', $result->issues[0]->code);
    $this->assertSame(['x'], $result->issues[0]->path);
  }

  public function test_extend_WithIntegerKeys_ShouldPreserveKeys(): void {
    $schema = (new ObjectSchema([10 => new StringSchema()]))->extend([20 => new IntegerSchema()]);

    $result = $schema->safeParse((object) [10 => 'a', 20 => 1]);

    $this->assertTrue($result->success);
    $this->assertSame(['10' => 'a', '20' => 1], (array) $result->data);
  }

  public function test_extend_WithExistingKey_ShouldKeepOriginalPosition(): void {
    $schema = $this->createUserSchema()->extend(['name' => new StringSchema()]);

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30]);

    $this->assertSame(['name', 'age'], array_keys((array) $result->data));
  }

  public function test_extend_ShouldNotModifyOriginalSchema(): void {
    $base = $this->createUserSchema();

    $base->extend(['email' => new StringSchema()]);

    $this->assertTrue($base->isValid((object) ['name' => 'Dan', 'age' => 30]));
  }

  public function test_merge_WithOtherSchema_ShouldCombineShapes(): void {
    $other = new ObjectSchema(['email' => new StringSchema()]);
    $schema = $this->createUserSchema()->merge($other);

    $valid = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'email' => 'a@b.com']);
    $missing = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30]);

    $this->assertTrue($valid->success);
    $this->assertFalse($missing->success);
    $this->assertSame(['email'], $missing->issues[0]->path);
  }

  public function test_merge_WithOverlappingKey_ShouldUseOtherSchemaField(): void {
    $other = new ObjectSchema(['age' => new StringSchema()]);
    $schema = $this->createUserSchema()->merge($other);

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30]);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame(['age'], $result->issues[0]->path);
  }

  public function test_merge_WithStrictOtherSchema_ShouldKeepUnknownKeysPolicyOfBase(): void {
    $other = (new ObjectSchema(['email' => new StringSchema()]))->strict();
    $schema = $this->createUserSchema()->merge($other);

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'email' => 'a@b.com', 'x' => 1]);

    $this->assertTrue($result->success);
    $this->assertObjectNotHasProperty('x', $result->data);
  }

  public function test_merge_ShouldNotModifyEitherSchema(): void {
    $base = $this->createUserSchema();
    $other = new ObjectSchema(['email' => new StringSchema()]);

    $base->merge($other);

    $this->assertTrue($base->isValid((object) ['name' => 'Dan', 'age' => 30]));
    $this->assertTrue($other->isValid((object) ['email' => 'a@b.com']));
  }

  public function test_partial_WithEmptyObject_ShouldReturnEmptyObject(): void {
    $schema = $this->createUserSchema()->partial();

    $result = $schema->safeParse(new \stdClass());

    $this->assertTrue($result->success);
    $this->assertEquals(new \stdClass(), $result->data);
    $this->assertSame([], $schema->asArray()->parse(new \stdClass()));
  }

  public function test_partial_WithPresentInvalidField_ShouldStillValidateIt(): void {
    $schema = $this->createUserSchema()->partial();

    $result = $schema->safeParse((object) ['age' => 'abc']);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame(['age'], $result->issues[0]->path);
  }

  public function test_partial_WithNestedObject_ShouldBeShallow(): void {
    $schema = (new ObjectSchema([
      'address' => new ObjectSchema(['city' => new StringSchema()]),
    ]))->partial();

    $result = $schema->safeParse((object) ['address' => new \stdClass()]);

    $this->assertFalse($result->success);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame(['address', 'city'], $result->issues[0]->path);
  }

  public function test_partial_ShouldNotModifyOriginalSchema(): void {
    $base = $this->createUserSchema();

    $base->partial();

    $this->assertFalse($base->isValid(new \stdClass()));
  }

  public function test_required_AfterPartial_ShouldRequireAllFieldsAgain(): void {
    $schema = $this->createUserSchema()->partial()->required();

    $result = $schema->safeParse(new \stdClass());

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame(['name'], $result->issues[0]->path);
    $this->assertSame(['age'], $result->issues[1]->path);
  }

  public function test_required_WithOptionalFields_ShouldMakeThemRequired(): void {
    $schema = (new ObjectSchema([
      'nickname' => (new StringSchema())->optional(),
    ]))->required();

    $result = $schema->safeParse(new \stdClass());

    $this->assertFalse($result->success);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame(['nickname'], $result->issues[0]->path);
  }

  public function test_required_ShouldKeepFieldRulesAndTransforms(): void {
    $schema = (new ObjectSchema([
      'name' => (new StringSchema())->min(3)->toUpperCase()->optional(),
    ]))->required();

    $invalid = $schema->safeParse((object) ['name' => 'ab']);
    $valid = $schema->safeParse((object) ['name' => 'dan']);

    $this->assertFalse($invalid->success);
    $this->assertSame('too_small', $invalid->issues[0]->code);
    $this->assertTrue($valid->success);
    $this->assertSame('DAN', $valid->data->name);
  }

  public function test_required_ShouldNotModifyOriginalSchema(): void {
    $partial = $this->createUserSchema()->partial();

    $partial->required();

    $this->assertTrue($partial->isValid(new \stdClass()));
  }

  public function test_asArray_WithValidObject_ShouldReturnAssociativeArray(): void {
    $schema = $this->createUserSchema()->asArray();

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30]);

    $this->assertTrue($result->success);
    $this->assertSame(['name' => 'Dan', 'age' => 30], $result->data);
  }

  public function test_asArray_WithNestedObject_ShouldConvertOnlyTopLevel(): void {
    $schema = (new ObjectSchema([
      'address' => new ObjectSchema(['city' => new StringSchema()]),
    ]))->asArray();

    $result = $schema->safeParse((object) ['address' => (object) ['city' => 'Recife']]);

    $this->assertTrue($result->success);
    $this->assertIsArray($result->data);
    $this->assertInstanceOf(\stdClass::class, $result->data['address']);
  }

  public function test_asArray_WithCoercedAssociativeArray_ShouldRoundTripToArray(): void {
    $schema = $this->createUserSchema()->coerce()->asArray();

    $result = $schema->safeParse(['name' => 'Dan', 'age' => 30]);

    $this->assertTrue($result->success);
    $this->assertSame(['name' => 'Dan', 'age' => 30], $result->data);
  }

  public function test_asArray_WithInvalidObject_ShouldNotTransform(): void {
    $schema = $this->createUserSchema()->asArray();

    $result = $schema->safeParse((object) ['name' => 'Dan']);

    $this->assertFalse($result->success);
    $this->assertNull($result->data);
  }

  public function test_asArray_ShouldNotModifyOriginalSchema(): void {
    $base = $this->createUserSchema();

    $base->asArray();

    $this->assertInstanceOf(\stdClass::class, $base->safeParse((object) ['name' => 'Dan', 'age' => 30])->data);
  }

  public function test_refine_WhenFailing_ShouldReportCustomIssueAtObjectPath(): void {
    $schema = (new ObjectSchema([
      'password' => new StringSchema(),
      'confirm' => new StringSchema(),
    ]))->refine(
      fn(mixed $value): bool => $value->password === $value->confirm,
      'Passwords must match'
    );

    $result = $schema->safeParse((object) ['password' => 'abc', 'confirm' => 'xyz']);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('custom', $result->issues[0]->code);
    $this->assertSame([], $result->issues[0]->path);
    $this->assertSame('Passwords must match', $result->issues[0]->message);
  }

  public function test_refine_WithClosureMessage_ShouldResolveMessageFromValue(): void {
    $schema = $this->createUserSchema()->refine(
      fn(mixed $value): bool => $value->age >= 18,
      fn(mixed $value, array $params): string => "{$value->name} is under age"
    );

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 10]);

    $this->assertFalse($result->success);
    $this->assertSame('Dan is under age', $result->issues[0]->message);
  }

  public function test_refine_WithInvalidField_ShouldReportOnlyFieldIssue(): void {
    $schema = $this->createUserSchema()->refine(
      fn(mixed $value): bool => false,
      'Always fails'
    );

    $result = $schema->safeParse((object) ['name' => 1, 'age' => 'abc']);

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame(['name'], $result->issues[0]->path);
    $this->assertSame('invalid_type', $result->issues[1]->code);
    $this->assertSame(['age'], $result->issues[1]->path);
  }

  public function test_refine_WithValidFieldsAndFailingRefine_ShouldReturnDirtyCustomIssue(): void {
    $schema = $this->createUserSchema()->refine(
      fn(mixed $value): bool => false,
      'Always fails'
    );

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30]);

    $this->assertFalse($result->success);
    $this->assertTrue($result->dirty);
    $this->assertCount(1, $result->issues);
    $this->assertSame('custom', $result->issues[0]->code);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_refine_ShouldReceiveParsedChildValues(): void {
    $received = null;
    $schema = (new ObjectSchema([
      'role' => (new StringSchema())->_default('user'),
      'age' => (new IntegerSchema())->coerce(),
      'name' => (new StringSchema())->trim(),
    ]))->refine(function (mixed $value) use (&$received): bool {
      $received = $value;

      return true;
    });

    $result = $schema->safeParse((object) ['age' => '30', 'name' => '  dan  ']);

    $this->assertTrue($result->success);
    $this->assertSame('user', $received->role);
    $this->assertSame(30, $received->age);
    $this->assertSame('dan', $received->name);
  }

  public function test_refine_WithMultipleFailingRules_ShouldReportAllIssues(): void {
    $schema = $this->createUserSchema()
      ->refine(fn(mixed $value): bool => false, 'First')
      ->refine(fn(mixed $value): bool => false, 'Second');

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30]);

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame('First', $result->issues[0]->message);
    $this->assertSame('Second', $result->issues[1]->message);
  }

  public function test_refine_ShouldReceiveTrimmedChildValue(): void {
    $received = null;
    $schema = (new ObjectSchema(['name' => (new StringSchema())->trim()]))->refine(
      function (mixed $value) use (&$received): bool {
        $received = $value->name;

        return true;
      }
    );

    $result = $schema->safeParse((object) ['name' => '  dan  ']);

    $this->assertTrue($result->success);
    $this->assertSame('dan', $received);
    $this->assertSame('dan', $result->data->name);
  }

  public function test_refine_OnNestedObject_ShouldReportNestedPath(): void {
    $schema = new ObjectSchema([
      'range' => (new ObjectSchema([
        'from' => new IntegerSchema(),
        'to' => new IntegerSchema(),
      ]))->refine(
        fn(mixed $value): bool => $value->from <= $value->to,
        'Invalid range'
      ),
    ]);

    $result = $schema->safeParse((object) ['range' => (object) ['from' => 10, 'to' => 1]]);

    $this->assertFalse($result->success);
    $this->assertSame('custom', $result->issues[0]->code);
    $this->assertSame(['range'], $result->issues[0]->path);
  }

  public function test_transform_ShouldReceiveParsedValue(): void {
    $schema = (new ObjectSchema([
      'name' => (new StringSchema())->trim(),
      'extra' => (new IntegerSchema())->coerce(),
    ]))->transform(fn(mixed $value): string => "{$value->name}:" . gettype($value->extra));

    $result = $schema->safeParse((object) ['name' => '  dan  ', 'extra' => '5']);

    $this->assertTrue($result->success);
    $this->assertSame('dan:integer', $result->data);
  }

  public function test_default_WithClosureReturningObject_ShouldValidateDefault(): void {
    $schema = $this->createUserSchema()->_default(fn() => (object) ['name' => 'Anon', 'age' => 0]);

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame('Anon', $result->data->name);
  }

  public function test_default_WithInvalidDefault_ShouldFailValidation(): void {
    $schema = $this->createUserSchema()->_default((object) ['name' => 'Anon']);

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame(['age'], $result->issues[0]->path);
  }

  public function test_clone_ShouldShareChildren(): void {
    $base = $this->createUserSchema()->catchall(new IntegerSchema());
    $catchall = new \ReflectionProperty(ObjectSchema::class, 'catchall');
    $cloned = clone $base;

    $modified = $cloned->partial()->strict();

    $this->assertFalse($base->isValid(new \stdClass()));
    $this->assertTrue($base->isValid((object) ['name' => 'Dan', 'age' => 30, 'extra' => 1]));
    $this->assertFalse($cloned->isValid(new \stdClass()));
    $this->assertTrue($modified->isValid(new \stdClass()));
    $this->assertSame($base->getShape(), (clone $base)->strict()->getShape());
    $this->assertSame($catchall->getValue($base), $catchall->getValue($base->optional()));
  }

  public function test_required_WithAlreadyRequiredField_ShouldKeepSameInstance(): void {
    $name = new StringSchema();
    $nick = (new StringSchema())->optional();
    $schema = new ObjectSchema(['name' => $name, 'nick' => $nick]);

    $shape = $schema->required()->getShape();

    $this->assertSame($name, $shape['name']);
    $this->assertNotSame($nick, $shape['nick']);
    $this->assertFalse($schema->required()->isValid((object) ['name' => 'a']));
    $this->assertTrue($schema->isValid((object) ['name' => 'a']));
  }

  public function test_required_WithNestedPartialObject_ShouldBeShallow(): void {
    $inner = (new ObjectSchema(['city' => new StringSchema()]))->partial();

    $schema = (new ObjectSchema(['address' => $inner]))->required();

    $this->assertSame($inner, $schema->getShape()['address']);
    $this->assertTrue($schema->isValid((object) ['address' => new \stdClass()]));
  }

  public function test_partial_AfterRefine_ShouldThrowAndKeepShape(): void {
    $name = new StringSchema();
    $schema = (new ObjectSchema(['name' => $name]))->refine(static fn(mixed $v): bool => true);

    try {
      $schema->partial();
      $this->fail('Expected LogicException');
    } catch (LogicException) {
    }

    $this->assertSame($name, $schema->getShape()['name']);
  }

  public function test_required_AfterTransform_ShouldThrowAndKeepShape(): void {
    $name = new StringSchema();
    $schema = (new ObjectSchema(['name' => $name]))->transform(static fn(mixed $v): mixed => $v);

    try {
      $schema->required();
      $this->fail('Expected LogicException');
    } catch (LogicException) {
    }

    $this->assertSame($name, $schema->getShape()['name']);
  }

  public function test_parse_WithNestedInvalidField_ShouldThrowWithIssuePath(): void {
    $schema = new ObjectSchema([
      'address' => new ObjectSchema(['city' => new StringSchema()]),
    ]);

    try {
      $schema->parse((object) ['address' => (object) ['city' => 1]]);
      $this->fail('Expected ValidatorException');
    } catch (ValidatorException $exception) {
      $this->assertSame(['address', 'city'], $exception->getFirstIssue()->path);
      $this->assertTrue($exception->hasIssueAt('address.city'));
    }
  }

  public function test_messages_WithCoercedListClosure_ShouldReceiveListParams(): void {
    $received = null;
    $schema = (new ObjectSchema())->coerce()->messages([
      'invalid_type' => function (mixed $value, array $params) use (&$received): string {
        $received = [$value, $params];
        return 'custom';
      },
    ]);

    $result = $schema->safeParse([1, 2]);

    $this->assertSame('custom', $result->issues[0]->message);
    $this->assertSame([[1, 2], ['expected' => 'object', 'received' => 'list']], $received);
  }

  public function test_messages_WithUnrecognizedKeysString_ShouldUseCustomMessage(): void {
    $schema = (new ObjectSchema(['name' => new StringSchema(), 'age' => new IntegerSchema()]))->strict()->messages(['unrecognized_keys' => 'Chave desconhecida']);

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'extra' => 5]);

    $this->assertSame('unrecognized_keys', $result->issues[0]->code);
    $this->assertSame('Chave desconhecida', $result->issues[0]->message);
  }

  public function test_messages_WithUnrecognizedKeysClosure_ShouldReceiveExtraValueAndKey(): void {
    $received = null;
    $schema = (new ObjectSchema(['name' => new StringSchema(), 'age' => new IntegerSchema()]))->strict()->messages([
      'unrecognized_keys' => function (mixed $value, array $params) use (&$received): string {
        $received = [$value, $params];
        return 'custom';
      },
    ]);

    $result = $schema->safeParse((object) ['name' => 'Dan', 'age' => 30, 'extra' => 5]);

    $this->assertSame(['extra'], $result->issues[0]->path);
    $this->assertSame([5, ['key' => 'extra']], $received);
  }

  public function test_messages_OnShapeField_ShouldUseFieldMessageWithFieldPath(): void {
    $schema = new ObjectSchema(['age' => (new IntegerSchema())->messages(['required' => 'Informe a idade'])]);

    $direct = $schema->safeParse((object) []);
    $roundTrip = $schema->partial()->required()->safeParse((object) []);

    $this->assertSame(['age'], $direct->issues[0]->path);
    $this->assertSame('Informe a idade', $direct->issues[0]->message);
    $this->assertSame(['age'], $roundTrip->issues[0]->path);
    $this->assertSame('Informe a idade', $roundTrip->issues[0]->message);
  }
}
