<?php

namespace Esliph\Validator\Tests\Schemas\Complex;

use PHPUnit\Framework\Attributes\DataProvider;

use Esliph\Validator\V;
use Esliph\Validator\Errors\ValidatorException;
use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\Schemas\Complex\ArraySchema;
use Esliph\Validator\Schemas\Complex\ObjectSchema;
use Esliph\Validator\Schemas\Complex\UnionSchema;
use Esliph\Validator\Schemas\Primitive\BooleanSchema;
use Esliph\Validator\Schemas\Primitive\IntegerSchema;
use Esliph\Validator\Schemas\Primitive\StringSchema;

use Esliph\Validator\Tests\Schemas\BaseSchemaTestCase;

use Closure;
use InvalidArgumentException;
use Override;
use ReflectionMethod;

class UnionSchemaTest extends BaseSchemaTestCase {

  #[Override]
  protected function createSchema(): Schema {
    return new UnionSchema([new StringSchema(), new IntegerSchema()]);
  }

  #[Override]
  protected function getValidValue(): mixed {
    return 'hello';
  }

  #[Override]
  protected function getInvalidValue(): mixed {
    return true;
  }

  #[Override]
  protected function getDefaultValue(): mixed {
    return 'default';
  }

  #[Override]
  protected function getTransformFunction(): Closure {
    return fn(mixed $value) => $value;
  }

  #[Override]
  protected function getRefineCheck(): Closure {
    return fn(mixed $value): bool => is_string($value);
  }

  public function test_parseType_WithValueMatchingFirstMember_ShouldAccept(): void {
    $schema = new UnionSchema([new StringSchema(), new IntegerSchema()]);

    $result = $schema->safeParse('abc');

    $this->assertTrue($result->success);
    $this->assertSame('abc', $result->data);
    $this->assertEmpty($result->issues);
  }

  public function test_parseType_WithValueMatchingSecondMember_ShouldAccept(): void {
    $schema = new UnionSchema([new StringSchema(), new IntegerSchema()]);

    $result = $schema->safeParse(42);

    $this->assertTrue($result->success);
    $this->assertSame(42, $result->data);
    $this->assertEmpty($result->issues);
  }

  public function test_parseType_WithValueMatchingNoMember_ShouldReturnSingleInvalidUnion(): void {
    $schema = new UnionSchema([new StringSchema(), new IntegerSchema()]);

    $result = $schema->safeParse(true);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_union', $result->issues[0]->code);
    $this->assertSame('Invalid input', $result->issues[0]->message);
    $this->assertSame([], $result->issues[0]->path);
    $this->assertFalse($result->dirty);
  }

  public function test_parseType_WithValueMatchingNoneOfThreeMembers_ShouldReturnSingleInvalidUnion(): void {
    $schema = new UnionSchema([new IntegerSchema(), new BooleanSchema(), new StringSchema()]);

    $result = $schema->safeParse([1, 2]);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_union', $result->issues[0]->code);
  }

  public function test_parseType_WithMemberRuleFailing_ShouldReportOnlyMemberRuleIssue(): void {
    $schema = new UnionSchema([(new StringSchema())->min(5), new IntegerSchema()]);

    $result = $schema->safeParse('abc');

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('too_small', $result->issues[0]->code);
    $this->assertSame('Must be at least 5 characters', $result->issues[0]->message);
    $this->assertTrue($result->dirty);
  }

  public function test_parseType_WithMemberMultipleRulesFailing_ShouldReportAllMemberRuleIssues(): void {
    $schema = new UnionSchema([
      (new StringSchema())->min(5)->email(),
      new IntegerSchema(),
    ]);

    $result = $schema->safeParse('abc');

    $this->assertFalse($result->success);
    $this->assertSame(
      ['too_small', 'invalid_format'],
      array_map(fn($issue) => $issue->code, $result->issues)
    );
  }

  public function test_parseType_WithFirstMemberFailingRuleAndSecondPassing_ShouldAccept(): void {
    $schema = new UnionSchema([
      (new StringSchema())->min(5),
      (new StringSchema())->max(3),
    ]);

    $result = $schema->safeParse('abc');

    $this->assertTrue($result->success);
    $this->assertSame('abc', $result->data);
    $this->assertEmpty($result->issues);
  }

  public function test_parseType_WithBothMembersMatching_ShouldUseFirstMember(): void {
    $schema = new UnionSchema([
      (new StringSchema())->transform(fn(string $value): string => 'first'),
      (new StringSchema())->transform(fn(string $value): string => 'second'),
    ]);

    $result = $schema->safeParse('abc');

    $this->assertTrue($result->success);
    $this->assertSame('first', $result->data);
  }

  public function test_parseType_WithCoercingMemberFirst_ShouldReturnCoercedValue(): void {
    $schema = new UnionSchema([(new StringSchema())->coerce(), new IntegerSchema()]);

    $result = $schema->safeParse(5);

    $this->assertTrue($result->success);
    $this->assertSame('5', $result->data);
  }

  public function test_parseType_WithCoercingMemberLast_ShouldReturnOriginalTypeFromFirstMember(): void {
    $schema = new UnionSchema([new IntegerSchema(), (new StringSchema())->coerce()]);

    $result = $schema->safeParse(5);

    $this->assertTrue($result->success);
    $this->assertSame(5, $result->data);
  }

  public function test_parseType_WithMemberTransform_ShouldReturnTransformedValue(): void {
    $schema = new UnionSchema([
      new IntegerSchema(),
      (new StringSchema())->transform(fn(string $value): string => strtoupper($value)),
    ]);

    $result = $schema->safeParse('abc');

    $this->assertTrue($result->success);
    $this->assertSame('ABC', $result->data);
  }

  public function test_parseType_WithMemberNormalizingValue_ShouldReturnParsedValueNotOriginal(): void {
    $schema = new UnionSchema([new IntegerSchema(), new StringSchema()]);

    $result = $schema->safeParse(5.0);

    $this->assertTrue($result->success);
    $this->assertSame(5, $result->data);
  }

  public function test_construct_WithSingleOption_ShouldThrow(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('union must have at least 2 options');

    new UnionSchema([new IntegerSchema()]);
  }

  public function test_construct_WithNoOptions_ShouldThrow(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('union must have at least 2 options');

    new UnionSchema();
  }

  public function test_union_WithSingleArgument_ShouldThrow(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('union must have at least 2 options');

    V::union(V::int());
  }

  public function test_getOptions_ShouldReturnOptionsInOrder(): void {
    $a = V::string();
    $b = V::int();

    $this->assertSame([$a, $b], (new UnionSchema([$a, $b]))->getOptions());
  }

  public function test_parseType_WithFailingRootValue_ShouldReportEmptyPath(): void {
    $schema = new UnionSchema([new StringSchema(), new IntegerSchema()]);

    $result = $schema->safeParse(true);

    $this->assertCount(1, $result->issues);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_parseType_WithNullAndNoMemberAcceptingNull_ShouldFailRequired(): void {
    $schema = new UnionSchema([new StringSchema(), new IntegerSchema()]);

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_parseType_WithNullAndOptionalMember_ShouldAcceptNull(): void {
    $schema = new UnionSchema([new StringSchema(), (new IntegerSchema())->optional()]);

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
  }

  public function test_parseType_WithNullAndMemberDefault_ShouldReturnMemberDefault(): void {
    $schema = new UnionSchema([(new StringSchema())->_default('fallback'), new IntegerSchema()]);

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame('fallback', $result->data);
  }

  public function test_optional_WithNull_ShouldReturnNullWithoutCallingMembers(): void {
    $schema = (new UnionSchema([new StringSchema(), new IntegerSchema()]))->optional();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
    $this->assertEmpty($result->issues);
  }

  public function test_optional_WithNonNullValue_ShouldStillValidateMembers(): void {
    $schema = (new UnionSchema([new StringSchema(), new IntegerSchema()]))->optional();

    $result = $schema->safeParse(true);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_union', $result->issues[0]->code);
  }

  public function test_default_WithNull_ShouldParseDefaultThroughMembers(): void {
    $schema = (new UnionSchema([
      new IntegerSchema(),
      (new StringSchema())->transform(fn(string $value): string => strtoupper($value)),
    ]))->_default('fallback');

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame('FALLBACK', $result->data);
  }

  public function test_default_WithClosure_ShouldResolveClosureBeforeMembers(): void {
    $schema = (new UnionSchema([new StringSchema(), new IntegerSchema()]))->_default(fn(): int => 7);

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame(7, $result->data);
  }

  public function test_default_WithValueMatchingNoMember_ShouldFail(): void {
    $schema = (new UnionSchema([new StringSchema(), new IntegerSchema()]))->_default(true);

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_union', $result->issues[0]->code);
  }

  public function test_refine_WithUnionRuleFailing_ShouldReturnCustomIssue(): void {
    $schema = (new UnionSchema([new StringSchema(), new IntegerSchema()]))
      ->refine(fn(mixed $value): bool => is_int($value), 'Must be an integer');

    $result = $schema->safeParse('abc');

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('custom', $result->issues[0]->code);
    $this->assertSame('Must be an integer', $result->issues[0]->message);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_refine_WithClosureMessage_ShouldResolveMessageWithValue(): void {
    $schema = (new UnionSchema([new StringSchema(), new IntegerSchema()]))
      ->refine(
        fn(mixed $value): bool => is_int($value),
        fn(mixed $value, array $params): string => "Got '$value'"
      );

    $result = $schema->safeParse('abc');

    $this->assertFalse($result->success);
    $this->assertSame("Got 'abc'", $result->issues[0]->message);
  }

  public function test_refine_WithMultipleUnionRulesFailing_ShouldReportAllIssues(): void {
    $schema = (new UnionSchema([new StringSchema(), new IntegerSchema()]))
      ->refine(fn(mixed $value): bool => false, 'first')
      ->refine(fn(mixed $value): bool => false, 'second');

    $result = $schema->safeParse(1);

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame('first', $result->issues[0]->message);
    $this->assertSame('second', $result->issues[1]->message);
  }

  public function test_refine_WithNoMemberMatching_ShouldNotRunUnionRules(): void {
    $called = false;
    $schema = (new UnionSchema([new StringSchema(), new IntegerSchema()]))
      ->refine(function (mixed $value) use (&$called): bool {
        $called = true;

        return false;
      });

    $result = $schema->safeParse(true);

    $this->assertFalse($result->success);
    $this->assertFalse($called);
    $this->assertSame(['invalid_union'], array_map(fn($issue) => $issue->code, $result->issues));
  }

  public function test_refine_ShouldReceiveValueParsedByMember(): void {
    $received = null;
    $schema = (new UnionSchema([
      (new IntegerSchema())->coerce(),
      new StringSchema(),
    ]))->refine(function (mixed $value) use (&$received): bool {
      $received = $value;

      return true;
    });

    $result = $schema->safeParse('15');

    $this->assertTrue($result->success);
    $this->assertSame(15, $received);
    $this->assertSame(15, $result->data);
  }

  public function test_transform_ShouldRunAfterMemberTransform(): void {
    $schema = (new UnionSchema([
      new IntegerSchema(),
      (new StringSchema())->transform(fn(string $value): string => strtoupper($value)),
    ]))->transform(fn(string $value): string => $value . '!');

    $result = $schema->safeParse('abc');

    $this->assertTrue($result->success);
    $this->assertSame('ABC!', $result->data);
  }

  public function test_transform_WithNoMemberMatching_ShouldNotRun(): void {
    $called = false;
    $schema = (new UnionSchema([new StringSchema(), new IntegerSchema()]))
      ->transform(function (mixed $value) use (&$called): mixed {
        $called = true;

        return $value;
      });

    $result = $schema->safeParse(true);

    $this->assertFalse($result->success);
    $this->assertFalse($called);
  }

  public function test_validateType_WithUnionInsideObject_ShouldReportFieldPath(): void {
    $schema = new ObjectSchema([
      'id' => new UnionSchema([new StringSchema(), new IntegerSchema()]),
    ]);
    $value = new \stdClass();
    $value->id = true;

    $result = $schema->safeParse($value);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_union', $result->issues[0]->code);
    $this->assertSame(['id'], $result->issues[0]->path);
  }

  public function test_validateType_WithUnionInsideArray_ShouldReportIndexPath(): void {
    $schema = new ArraySchema(new UnionSchema([new StringSchema(), new IntegerSchema()]));

    $result = $schema->safeParse([1, 'a', false]);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_union', $result->issues[0]->code);
    $this->assertSame([2], $result->issues[0]->path);
  }

  public function test_validateType_WithUnionInsideArray_ShouldReturnParsedElements(): void {
    $schema = new ArraySchema(new UnionSchema([new IntegerSchema(), new StringSchema()]));

    $result = $schema->safeParse([1, 'a', 2.0]);

    $this->assertTrue($result->success);
    $this->assertSame([1, 'a', 2], $result->data);
  }

  public function test_validateType_WithDeeplyNestedUnion_ShouldReportFullPath(): void {
    $schema = new ObjectSchema([
      'users' => new ArraySchema(new ObjectSchema([
        'contact' => new UnionSchema([
          (new StringSchema())->email(),
          new IntegerSchema(),
        ]),
      ])),
    ]);
    $first = new \stdClass();
    $first->contact = 'a@b.com';
    $second = new \stdClass();
    $second->contact = 'not-an-email';
    $value = new \stdClass();
    $value->users = [$first, $second];

    $result = $schema->safeParse($value);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame(['users', 1, 'contact'], $result->issues[0]->path);
    $this->assertSame('invalid_format', $result->issues[0]->code);
  }

  public function test_validateType_WithOptionalUnionInsideObjectAndMissingField_ShouldPass(): void {
    $schema = new ObjectSchema([
      'id' => (new UnionSchema([new StringSchema(), new IntegerSchema()]))->optional(),
    ]);

    $result = $schema->safeParse(new \stdClass());

    $this->assertTrue($result->success);
    $this->assertNull($result->data->id);
  }

  public function test_validateType_WithRequiredUnionInsideObjectAndMissingField_ShouldFailRequiredAtFieldPath(): void {
    $schema = new ObjectSchema([
      'id' => new UnionSchema([new StringSchema(), new IntegerSchema()]),
    ]);

    $result = $schema->safeParse(new \stdClass());

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame(['id'], $result->issues[0]->path);
  }

  public function test_parseType_WithUnionOfObjectAndArray_ShouldAcceptEither(): void {
    $schema = new UnionSchema([
      new ObjectSchema(['name' => new StringSchema()]),
      new ArraySchema(new IntegerSchema()),
    ]);
    $object = new \stdClass();
    $object->name = 'Ana';

    $objectResult = $schema->safeParse($object);
    $arrayResult = $schema->safeParse([1, 2, 3]);

    $this->assertTrue($objectResult->success);
    $this->assertEquals($object, $objectResult->data);
    $this->assertTrue($arrayResult->success);
    $this->assertSame([1, 2, 3], $arrayResult->data);
  }

  public function test_parseType_WithUnionOfObjectAndArrayMatchingNone_ShouldReportIssuesFromDirtyArrayMember(): void {
    $schema = new UnionSchema([
      new ObjectSchema(['name' => new StringSchema()]),
      new ArraySchema(new IntegerSchema()),
    ]);

    $result = $schema->safeParse([1, 'x', 3, 'y']);

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame([1], $result->issues[0]->path);
    $this->assertSame([3], $result->issues[1]->path);
  }

  public function test_parseType_WithUnionOfObjects_ShouldFallBackToSecondWhenFirstFieldFails(): void {
    $schema = new UnionSchema([
      new ObjectSchema(['kind' => (new StringSchema())->regex('/^circle$/'), 'radius' => new IntegerSchema()]),
      new ObjectSchema(['kind' => (new StringSchema())->regex('/^square$/'), 'side' => new IntegerSchema()]),
    ]);
    $value = new \stdClass();
    $value->kind = 'square';
    $value->side = 4;

    $result = $schema->safeParse($value);

    $this->assertTrue($result->success);
    $this->assertSame('square', $result->data->kind);
    $this->assertSame(4, $result->data->side);
  }

  public function test_parseType_WithUnionOfObjectsMatchingNone_ShouldReportFieldIssuesFromFirstMember(): void {
    $schema = new UnionSchema([
      new ObjectSchema(['radius' => new IntegerSchema()]),
      new ObjectSchema(['side' => new IntegerSchema()]),
    ]);

    $result = $schema->safeParse(new \stdClass());

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame(['radius'], $result->issues[0]->path);
    $this->assertSame('required', $result->issues[0]->code);
  }

  public function test_parseType_WithStrictObjectFirst_ShouldFallBackToObjectAcceptingExtraKey(): void {
    $schema = new UnionSchema([
      (new ObjectSchema(['a' => new StringSchema()]))->strict(),
      new ObjectSchema(['a' => new StringSchema(), 'b' => new IntegerSchema()]),
    ]);
    $value = new \stdClass();
    $value->a = 'x';
    $value->b = 1;

    $result = $schema->safeParse($value);

    $this->assertTrue($result->success);
    $this->assertSame('x', $result->data->a);
    $this->assertSame(1, $result->data->b);
  }

  public function test_parseType_WithStrictObjectFirstAndInvalidExtraKey_ShouldReportExtraKeyFromFirstMember(): void {
    $schema = new UnionSchema([
      (new ObjectSchema(['a' => new StringSchema()]))->strict(),
      new ObjectSchema(['a' => new StringSchema(), 'b' => new IntegerSchema()]),
    ]);
    $value = new \stdClass();
    $value->a = 'x';
    $value->b = 'not a number';

    $result = $schema->safeParse($value);

    $this->assertFalse($result->success);
    $this->assertSame(
      [['b']],
      array_map(fn($issue) => $issue->path, $result->issues)
    );
    $this->assertSame(
      ['unrecognized_keys'],
      array_map(fn($issue) => $issue->code, $result->issues)
    );
  }

  public function test_parseType_WithNestedUnionMatchingNone_ShouldReportSingleInvalidUnion(): void {
    $schema = new UnionSchema([
      new UnionSchema([new StringSchema(), new IntegerSchema()]),
      new BooleanSchema(),
    ]);

    $valid = $schema->safeParse(false);
    $invalid = $schema->safeParse([]);

    $this->assertTrue($valid->success);
    $this->assertFalse($valid->data);
    $this->assertFalse($invalid->success);
    $this->assertCount(1, $invalid->issues);
    $this->assertSame('invalid_union', $invalid->issues[0]->code);
  }

  public function test_parse_WithNoMemberMatching_ShouldThrowWithInvalidUnion(): void {
    $schema = new UnionSchema([new StringSchema(), new IntegerSchema()]);

    try {
      $schema->parse(true);
      $this->fail('Expected ValidatorException');
    } catch (ValidatorException $exception) {
      $this->assertCount(1, $exception->getIssues());
      $this->assertSame(
        ['' => ['Invalid input']],
        $exception->getMessagesByPath()
      );
    }
  }

  public function test_parseType_WithNullAndNullMember_ShouldAcceptNull(): void {
    $schema = V::union(V::string(), V::null());

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
  }

  public function test_parseType_WithNullAndDirtyMember_ShouldStillFailRequired(): void {
    $schema = V::union(
      V::null()->refine(fn(mixed $v): bool => false, 'never'),
      V::int()
    );

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('required', $result->issues[0]->code);
  }

  public function test_parseType_WithCleanMemberBeforeDirty_ShouldReportDirtyMember(): void {
    $schema = V::union(V::int(), V::string()->min(5));

    $result = $schema->safeParse('abc');

    $this->assertCount(1, $result->issues);
    $this->assertSame('too_small', $result->issues[0]->code);
  }

  public function test_parseType_WithTwoDirtyMembers_ShouldReportFirstDirty(): void {
    $schema = V::union(V::string()->min(5), V::string()->email());

    $result = $schema->safeParse('abc');

    $this->assertCount(1, $result->issues);
    $this->assertSame('too_small', $result->issues[0]->code);
  }

  public function test_parseType_WithNestedUnion_ShouldPassThroughDirtyResult(): void {
    $inner = V::union(V::string()->min(5), V::int());
    $schema = V::union($inner, V::boolean());

    $result = $schema->safeParse('abc');

    $this->assertCount(1, $result->issues);
    $this->assertSame('too_small', $result->issues[0]->code);
    $this->assertTrue($result->dirty);
  }

  public function test_parseType_WithWrongDiscriminantInObjectMember_ShouldReportFirstObjectMember(): void {
    $schema = V::union(
      V::object(['kind' => V::literal('circle'), 'radius' => V::int()]),
      V::object(['kind' => V::literal('square'), 'side' => V::int()])
    );

    $result = $schema->safeParse((object) ['kind' => 'square', 'side' => 'x']);

    $this->assertSame(
      ['invalid_literal', 'required'],
      array_map(fn($issue) => $issue->code, $result->issues)
    );
    $this->assertSame(
      [['kind'], ['radius']],
      array_map(fn($issue) => $issue->path, $result->issues)
    );
    $this->assertSame("Expected literal 'circle'", $result->issues[0]->message);
  }

  public function test_parseType_WithSuccessfulMember_ShouldReturnMemberResultInstance(): void {
    $expected = ParseResult::ok('member');
    $member = new class($expected) extends Schema {

      public function __construct(private ParseResult $result) {
      }

      #[Override]
      protected function _parse(mixed $value, array $path = []): ParseResult {
        return $this->result;
      }

      #[Override]
      protected function parseType(mixed $value, array $path = []): ParseResult {
        return $this->result;
      }
    };
    $union = new UnionSchema([$member, new StringSchema()]);

    $result = (new ReflectionMethod(UnionSchema::class, 'parseType'))->invoke($union, 'x', []);

    $this->assertSame($expected, $result);
  }

  public function test_refine_WithNullAcceptedByMember_ShouldRunUnionRulesOnNull(): void {
    $schema = V::union(V::null(), V::int())
      ->refine(fn(mixed $v): bool => $v !== null, 'no null');

    $result = $schema->safeParse(null);

    $this->assertCount(1, $result->issues);
    $this->assertSame('custom', $result->issues[0]->code);
    $this->assertSame('no null', $result->issues[0]->message);
  }

  public function test_parseType_WithOptionalInnerUnionAndNull_ShouldAcceptNull(): void {
    $schema = V::union(V::union(V::int(), V::string())->optional(), V::boolean());

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
  }

  public function test_validateType_WithNullableUnionInsideArray_ShouldAcceptNullElements(): void {
    $schema = V::_array(V::union(V::null(), V::int()));

    $result = $schema->safeParse([null, 1]);

    $this->assertTrue($result->success);
    $this->assertSame([null, 1], $result->data);
  }

  public function test_parse_WithMatchingMember_ShouldReturnParsedValue(): void {
    $schema = new UnionSchema([new IntegerSchema(), new StringSchema()]);

    $this->assertSame(3, $schema->parse(3.0));
  }

  public function test_union_FactoryMethod_ShouldBuildEquivalentSchema(): void {
    $schema = V::union(V::string(), V::int());

    $this->assertInstanceOf(UnionSchema::class, $schema);
    $this->assertTrue($schema->isValid('a'));
    $this->assertTrue($schema->isValid(1));
    $this->assertFalse($schema->isValid(true));
  }

  public function test_optional_ShouldNotMutateOriginal(): void {
    $base = new UnionSchema([new StringSchema(), new IntegerSchema()]);

    $optional = $base->optional();

    $this->assertTrue($optional->safeParse(null)->success);
    $this->assertFalse($base->safeParse(null)->success);
  }

  public function test_refine_ShouldNotMutateOriginal(): void {
    $base = new UnionSchema([new StringSchema(), new IntegerSchema()]);

    $refined = $base->refine(fn(mixed $value): bool => false);

    $this->assertFalse($refined->safeParse('abc')->success);
    $this->assertTrue($base->safeParse('abc')->success);
  }

  public function test_transform_ShouldNotMutateOriginal(): void {
    $base = new UnionSchema([new StringSchema(), new IntegerSchema()]);

    $transformed = $base->transform(fn(mixed $value): string => 'changed');

    $this->assertSame('changed', $transformed->safeParse('abc')->data);
    $this->assertSame('abc', $base->safeParse('abc')->data);
  }

  public function test_clone_ShouldShareMemberSchemas(): void {
    $string = new StringSchema();
    $integer = new IntegerSchema();
    $base = new UnionSchema([$string, $integer]);
    $before = $base->safeParse(1.5);

    $cloned = clone $base;

    $this->assertSame([$string, $integer], $cloned->getOptions());
    $this->assertSame([$string, $integer], $base->getOptions());
    $this->assertEquals($before, $base->safeParse(1.5));
  }

  public function test_clone_FluentMethod_ShouldShareMembersWithOriginal(): void {
    $base = new UnionSchema([new StringSchema(), new IntegerSchema()]);

    $optional = $base->optional();

    $this->assertSame($base->getOptions(), $optional->getOptions());
    $this->assertSame('abc', $base->safeParse('abc')->data);
    $this->assertSame('abc', $optional->safeParse('abc')->data);
    $this->assertFalse($base->safeParse(null)->success);
  }

  public function test_clone_WithNestedComposite_ShouldShareGrandchildren(): void {
    $inner = new StringSchema();
    $object = new ObjectSchema(['name' => $inner]);
    $base = new UnionSchema([$object, new IntegerSchema()]);
    $before = $base->safeParse((object) ['name' => 'Dan']);

    $cloned = clone $base;

    $this->assertSame($object, $cloned->getOptions()[0]);
    $this->assertSame($inner, $object->getShape()['name']);
    $this->assertEquals($before, $base->safeParse((object) ['name' => 'Dan']));
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithAllInputTypes_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = new UnionSchema([new StringSchema(), new IntegerSchema()]);

    $result = $schema->safeParse($value);

    $isWholeFloat = is_float($value) && is_finite($value) && floor($value) === $value;
    $shouldBeValid = is_string($value) || is_int($value) || $isWholeFloat;

    if ($shouldBeValid) {
      $this->assertTrue($result->success, "Type $typeName should be valid");
      $this->assertSame($isWholeFloat ? (int) $value : $value, $result->data);
    } else {
      $this->assertFalse($result->success, "Type $typeName should be invalid");

      if ($value === null) {
        $this->assertCount(1, $result->issues);
        $this->assertSame('required', $result->issues[0]->code);
      } else {
        $this->assertCount(1, $result->issues, "Type $typeName should report a single issue");
        $this->assertSame('invalid_union', $result->issues[0]->code);
      }
    }
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithAllInputTypesAndReversedMembers_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = new UnionSchema([new BooleanSchema(), (new StringSchema())->coerce()]);

    $result = $schema->safeParse($value);

    $isStringCoercible = is_scalar($value);

    if (is_bool($value)) {
      $this->assertTrue($result->success, "Type $typeName should be accepted by the first member");
      $this->assertSame($value, $result->data);
    } else if ($isStringCoercible) {
      $this->assertTrue($result->success, "Type $typeName should be coerced by the second member");
      $this->assertSame((string) $value, $result->data);
    } else {
      $this->assertFalse($result->success, "Type $typeName should be invalid");

      $this->assertCount(1, $result->issues);

      if ($value === null) {
        $this->assertSame('required', $result->issues[0]->code);
      } else {
        $this->assertSame('invalid_union', $result->issues[0]->code);
      }
    }
  }

  #[Override]
  protected function emitsInvalidType(): bool {
    return false;
  }

  public function test_messages_WithInvalidUnionString_ShouldUseCustomMessage(): void {
    $result = V::union(V::string(), V::int())->messages(['invalid_union' => 'nenhuma'])->safeParse([]);

    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_union', $result->issues[0]->code);
    $this->assertSame('nenhuma', $result->issues[0]->message);
  }

  public function test_messages_WithInvalidUnionClosure_ShouldReceiveValueAndEmptyParams(): void {
    $received = null;
    $schema = V::union(V::string(), V::int())->messages([
      'invalid_union' => function (mixed $value, array $params) use (&$received): string {
        $received = [$value, $params];
        return 'custom';
      },
    ]);

    $schema->safeParse(1.5);

    $this->assertSame([1.5, []], $received);
  }

  public function test_parseType_WithDirtyMemberStringMessage_ShouldKeepLiteral(): void {
    $schema = V::union(V::object(['a' => V::int()->messages(['invalid_type' => 'A inteiro'])]), V::string());

    $result = $schema->safeParse((object) ['a' => 'x']);

    $this->assertCount(1, $result->issues);
    $this->assertSame(['a'], $result->issues[0]->path);
    $this->assertSame('A inteiro', $result->issues[0]->message);
  }

  public function test_messages_WithDirtyMember_ShouldReturnMemberCustomMessage(): void {
    $schema = V::union(
      V::object(['a' => V::int()->messages(['invalid_type' => 'a inválido'])]),
      V::int()
    )->messages(['invalid_union' => 'nenhuma']);

    $result = $schema->safeParse((object) ['a' => 'x']);

    $this->assertCount(1, $result->issues);
    $this->assertSame('a inválido', $result->issues[0]->message);
    $this->assertSame(['a'], $result->issues[0]->path);
  }

  public function test_messages_RequiredOnMember_ShouldNotOverrideUnionRequired(): void {
    $result = V::union(V::string()->messages(['required' => 'A']), V::int())->safeParse(null);

    $this->assertCount(1, $result->issues);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame('Value is required', $result->issues[0]->message);
  }

  public function test_messages_WithInvalidTypeCode_ShouldThrow(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("Unknown message code 'invalid_type'; allowed: required, invalid_union");

    V::union(V::string(), V::int())->messages(['invalid_type' => 'x']);
  }
}
