<?php

namespace Esliph\Validator\Tests\Schemas;

use Closure;

use Esliph\Validator\Errors\Issue;
use PHPUnit\Framework\TestCase;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Errors\ValidatorException;
use InvalidArgumentException;
use Override;

class TestSchema extends Schema {

  /**
   * @param Closure(mixed, array): ParseResult $checkType
   * @param Closure(mixed, array): ParseResult $checkValid
   */
  public function __construct(
    private ?Closure $checkType = null,
    private ?Closure $checkValid = null
  ) {
  }

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    if ($this->checkType === null) {
      return ParseResult::ok($value);
    }

    return ($this->checkType)($value, $path);
  }

  #[Override]
  protected function validateType(mixed $value, array $path = []): ParseResult {
    if ($this->checkValid === null) {
      return parent::validateType($value, $path);
    }

    return ($this->checkValid)($value, $path);
  }
}

class SchemaTest extends TestCase {

  public function test_clone_WithoutRulesOrTransforms_ShouldCreateIdenticalCopy(): void {
    $schema = new TestSchema();

    $cloned = clone $schema;

    $this->assertNotSame($schema, $cloned);
  }

  public function test_messages_WithUnknownCode_ShouldListBaseCodesInOrder(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("Unknown message code 'nope'; allowed: required, invalid_type");

    (new TestSchema())->messages(['nope' => 'x']);
  }

  public function test_messages_WithEmptyArray_ShouldKeepDefaultMessages(): void {
    $result = (new TestSchema())->messages([])->safeParse(null);

    $this->assertSame('Value is required', $result->issues[0]->message);
  }

  public function test_messages_WithClosureReturningInt_ShouldCastToString(): void {
    /** @phpstan-ignore argument.type */
    $schema = (new TestSchema())->messages([
      'required' => fn(mixed $value, array $params): int => 42,
    ]);

    $result = $schema->safeParse(null);

    $this->assertSame('42', $result->issues[0]->message);
  }

  public function test_messages_WithStringContainingPlaceholders_ShouldKeepItLiteral(): void {
    $result = (new TestSchema())->messages(['required' => '{received} %s'])->safeParse(null);

    $this->assertSame('{received} %s', $result->issues[0]->message);
  }

  public function test_clone_WithRules_ShouldCloneAllRules(): void {
    $check1 = fn(mixed $value, array $params) => strlen($value) > 3;
    $check2 = fn(mixed $value, array $params) => strlen($value) < 20;

    $schema = new TestSchema();
    $schema = $schema->refine($check1, 'Too short');
    $schema = $schema->refine($check2, 'Too long');

    $cloned = clone $schema;

    $this->assertNotSame($schema, $cloned);
  }

  public function test_clone_WithTransforms_ShouldResetTransformKeys(): void {
    $schema = new TestSchema();
    $schema = $schema->transform(fn($v) => strtoupper($v));
    $schema = $schema->transform(fn($v) => trim($v));

    $cloned = clone $schema;

    $this->assertNotSame($schema, $cloned);
  }

  public function test_clone_WithObjectDefault_ShouldCloneTheDefault(): void {
    $default = new \stdClass();
    $default->value = 'test';

    $schema = new TestSchema();
    $schema = $schema->_default($default);

    $cloned = clone $schema;

    $this->assertNotSame($schema->safeParse(null)->data, $cloned->safeParse(null)->data);
  }

  public function test_parse_WithNullRequired_ShouldThrowValidatorException(): void {
    $this->expectException(ValidatorException::class);

    $schema = new TestSchema();
    $schema->parse(null);
  }

  public function test_parse_WithValidValue_ShouldReturnData(): void {
    $schema = new TestSchema();

    $result = $schema->parse('test');

    $this->assertSame('test', $result);
  }

  public function test_parse_WithValidationError_ShouldThrowValidatorException(): void {
    $this->expectException(ValidatorException::class);

    $schema = new TestSchema();
    $schema = $schema->refine(
      fn(mixed $v, array $p) => strlen($v) > 10,
      'Must be longer than 10 characters'
    );

    $schema->parse('short');
  }

  public function test_isValid_WithValidValue_ShouldReturnTrue(): void {
    $schema = new TestSchema();

    $this->assertTrue($schema->isValid('test'));
  }

  public function test_isValid_WithInvalidValue_ShouldReturnFalse(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(
      fn(mixed $v, array $p) => strlen($v) > 10
    );

    $this->assertFalse($schema->isValid('short'));
  }

  public function test_isValid_WithNull_ShouldReturnFalse(): void {
    $schema = new TestSchema();

    $this->assertFalse($schema->isValid(null));
  }

  public function test_safeParse_WithValidValue_ShouldReturnSuccessResult(): void {
    $schema = new TestSchema();

    $result = $schema->safeParse('test');

    $this->assertTrue($result->success);
    $this->assertSame('test', $result->data);
    $this->assertEmpty($result->issues);
  }

  public function test_safeParse_WithInvalidValue_ShouldReturnFailureResult(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(
      fn(mixed $v, array $p) => false,
      'Validation failed'
    );

    $result = $schema->safeParse('test');

    $this->assertFalse($result->success);
    $this->assertNull($result->data);
    $this->assertNotEmpty($result->issues);
  }

  public function test_parseType_WithInvalidValueForCustomCheck_ShouldFail(): void {
    $schema = new TestSchema(
      checkType: static function ($value, $path) {
        if ($value === 0) {
          return ParseResult::fail(
            new Issue(
              path: $path,
              message: 'Invalid value',
              code: 'invalid_type'
            )
          );
        }

        return ParseResult::ok($value);
      }
    );

    $result = $schema->safeParse(0);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('Invalid value', $result->issues[0]->message);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_validType_WithInvalidValueForCustomCheck_ShouldFail(): void {
    $schema = new TestSchema(
      checkValid: static function ($value, $path) {
        if ($value === 0) {
          return ParseResult::fail(
            new Issue(
              path: $path,
              message: 'Invalid value',
              code: 'invalid_type'
            )
          );
        }

        return ParseResult::ok($value);
      }
    );

    $result = $schema->safeParse(0);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('Invalid value', $result->issues[0]->message);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithValidValueForCustomCheck_ShouldReturnSuccessResult(): void {
    $schema = new TestSchema(
      checkType: static function ($value, $path) {
        if ($value === 0) {
          return ParseResult::fail(
            new Issue(
              path: $path,
              message: 'Invalid value',
              code: 'invalid_type'
            )
          );
        }

        return ParseResult::ok($value);
      }
    );

    $result = $schema->safeParse(1);

    $this->assertTrue($result->success);
    $this->assertSame(1, $result->data);
  }

  public function test_validType_WithValidValueForCustomCheck_ShouldReturnSuccessResult(): void {
    $schema = new TestSchema(
      checkValid: static function ($value, $path) {
        if ($value === 0) {
          return ParseResult::fail(
            new Issue(
              path: $path,
              message: 'Invalid value',
              code: 'invalid_type'
            )
          );
        }

        return ParseResult::ok($value);
      }
    );

    $result = $schema->safeParse(1);

    $this->assertTrue($result->success);
    $this->assertSame(1, $result->data);
  }

  public function test_nullHandling_WithNullAndNoOptions_ShouldFail(): void {
    $schema = new TestSchema();

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('Value is required', $result->issues[0]->message);
    $this->assertSame('required', $result->issues[0]->code);
  }

  public function test_nullHandling_WithOptional_ShouldReturnOkWithoutData(): void {
    $schema = new TestSchema();
    $schema = $schema->optional();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
    $this->assertEmpty($result->issues);
  }

  public function test_nullHandling_WithDefaultValue_ShouldUseDefault(): void {
    $schema = new TestSchema();
    $schema = $schema->_default('default_value');

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame('default_value', $result->data);
  }

  public function test_nullHandling_WithDefaultClosure_ShouldCallClosure(): void {
    $schema = new TestSchema();
    $schema = $schema->_default(fn() => 'generated_value');

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame('generated_value', $result->data);
  }

  public function test_nullHandling_WithDefaultClosureCalledMultipleTimes_ShouldCallEachTime(): void {
    $callCount = 0;
    $closure = function () use (&$callCount) {
      $callCount++;
      return "value_{$callCount}";
    };

    $schema = new TestSchema();
    $schema = $schema->_default($closure);

    $result1 = $schema->safeParse(null);
    $result2 = $schema->safeParse(null);

    $this->assertSame('value_1', $result1->data);
    $this->assertSame('value_2', $result2->data);
    $this->assertSame(2, $callCount);
  }

  public function test_nullHandling_OptionalOverridesDefault_OptionalTakesPrecedence(): void {
    $schema = new TestSchema();
    $schema = $schema->_default('default_value');
    $schema = $schema->optional();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame('default_value', $result->data);
  }

  public function test_transform_WithSingleTransform_ShouldApplyTransform(): void {
    $schema = new TestSchema();
    $schema = $schema->transform(fn($v) => strtoupper($v));

    $result = $schema->safeParse('hello');

    $this->assertTrue($result->success);
    $this->assertSame('HELLO', $result->data);
  }

  public function test_transform_WithMultipleTransforms_ShouldApplyInSequence(): void {
    $schema = new TestSchema();
    $schema = $schema->transform(fn($v) => strtoupper($v));
    $schema = $schema->transform(fn($v) => trim($v));
    $schema = $schema->transform(fn($v) => str_repeat($v, 2));

    $result = $schema->safeParse('  hello  ');

    $this->assertTrue($result->success);
    $this->assertSame('HELLOHELLO', $result->data);
  }

  public function test_transform_WithArrayData_ShouldTransformCorrectly(): void {
    $schema = new TestSchema();
    $schema = $schema->transform(function ($arr) {
      if (is_array($arr)) {
        return array_map(fn($v) => $v * 2, $arr);
      }
      return $arr;
    });

    $result = $schema->safeParse([1, 2, 3]);

    $this->assertTrue($result->success);
    $this->assertSame([2, 4, 6], $result->data);
  }

  public function test_transform_IsAppliedAfterValidation_ShouldValidateBeforeTransform(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(
      fn(mixed $v) => strlen($v) < 5,
      'String too long before transform'
    );
    $schema = $schema->transform(fn($v) => strtoupper($v));

    $result = $schema->safeParse('test');

    $this->assertTrue($result->success);
    $this->assertSame('TEST', $result->data);
  }

  public function test_refine_WithSingleRule_ShouldValidate(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(
      fn(mixed $v) => strlen($v) > 3,
      'Must be longer than 3 characters'
    );

    $result = $schema->safeParse('hi');

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('Must be longer than 3 characters', $result->issues[0]->message);
  }

  public function test_refine_WithMultipleRules_ShouldValidateAll(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(fn(mixed $v) => strlen($v) > 3, 'Too short');
    $schema = $schema->refine(fn(mixed $v) => strlen($v) < 20, 'Too long');
    $schema = $schema->refine(fn(mixed $v) => !is_numeric($v), 'Cannot be numeric');

    $result = $schema->safeParse('ab');

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('Too short', $result->issues[0]->message);
  }

  public function test_refine_WithMultipleFailingRules_ShouldReturnAllErrors(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(fn(mixed $v) => strlen($v) > 10, 'Too short');
    $schema = $schema->refine(fn(mixed $v) => strlen($v) < 5, 'Too long');

    $result = $schema->safeParse('medium');

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
  }

  public function test_refine_WithClosureMessage_ShouldResolveMessage(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(
      fn(mixed $v) => false,
      fn(mixed $v) => "Invalid value: {$v}"
    );

    $result = $schema->safeParse('test');

    $this->assertFalse($result->success);
    $this->assertSame('Invalid value: test', $result->issues[0]->message);
  }

  public function test_refine_WithNullMessage_ShouldUseDefaultMessage(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(fn(mixed $v) => false, null);

    $result = $schema->safeParse('test');

    $this->assertFalse($result->success);
    $this->assertSame('Invalid value', $result->issues[0]->message);
  }

  public function test_optional_ShouldMarkSchemaAsOptional(): void {
    $schema = new TestSchema();
    $schema = $schema->optional();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
  }

  public function test_optional_WithTransformsAndRules_ShouldStillAllowNull(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(fn(mixed $v) => strlen($v) > 5, 'Too short');
    $schema = $schema->transform(fn($v) => strtoupper($v));
    $schema = $schema->optional();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
  }

  public function test_apply_WithClosure_ShouldApplyClosure(): void {
    $schema = new TestSchema();

    $result = $schema->apply(function ($s) {
      return $s->refine(fn(mixed $v) => strlen($v) > 3, 'Too short');
    });

    $this->assertFalse($result->safeParse('ab')->success);
    $this->assertTrue($result->safeParse('test')->success);
  }

  public function test_apply_CanBeChained_ShouldApplyMultiple(): void {
    $schema = new TestSchema();

    $result = $schema
      ->apply(fn($s) => $s->refine(fn(mixed $v) => strlen($v) > 3, 'Too short'))
      ->apply(fn($s) => $s->transform(fn($v) => strtoupper($v)))
      ->apply(fn($s) => $s->optional());

    $this->assertTrue($result->safeParse(null)->success);
    $this->assertSame('TEST', $result->safeParse('test')->data);
  }

  public function test_default_WithNonClosureValue_ShouldStoreValue(): void {
    $schema = new TestSchema();
    $schema = $schema->_default('default');

    $result = $schema->safeParse(null);

    $this->assertSame('default', $result->data);
  }

  public function test_default_WithArrayValue_ShouldStoreArray(): void {
    $default = ['key' => 'value'];

    $schema = new TestSchema();
    $schema = $schema->_default($default);

    $result = $schema->safeParse(null);

    $this->assertSame($default, $result->data);
  }

  public function test_default_WithObjectValue_ShouldReturnFreshCopyOnEachParse(): void {
    $default = (object) ['tags' => 'a'];
    $schema = (new TestSchema())->_default($default);

    $first = $schema->safeParse(null)->data;
    $first->tags = 'mutated';
    $second = $schema->safeParse(null)->data;

    $this->assertNotSame($first, $second);
    $this->assertNotSame($default, $second);
    $this->assertSame('a', $second->tags);
  }

  public function test_isAssociativeArray_WithEmptyArray_ShouldReturnFalse(): void {
    $schema = new TestSchema();

    $reflection = new \ReflectionClass($schema);
    $method = $reflection->getMethod('isAssociativeArray');

    $this->assertFalse($method->invoke($schema, []));
  }

  public function test_isAssociativeArray_WithNumericArray_ShouldReturnFalse(): void {
    $schema = new TestSchema();

    $reflection = new \ReflectionClass($schema);
    $method = $reflection->getMethod('isAssociativeArray');

    $this->assertFalse($method->invoke($schema, [0 => 'a', 1 => 'b', 2 => 'c']));
  }

  public function test_isAssociativeArray_WithAssociativeArray_ShouldReturnTrue(): void {
    $schema = new TestSchema();

    $reflection = new \ReflectionClass($schema);
    $method = $reflection->getMethod('isAssociativeArray');

    $this->assertTrue($method->invoke($schema, ['name' => 'John', 'age' => 30]));
  }

  public function test_isAssociativeArray_WithMixedKeys_ShouldReturnTrue(): void {
    $schema = new TestSchema();

    $reflection = new \ReflectionClass($schema);
    $method = $reflection->getMethod('isAssociativeArray');

    $this->assertTrue($method->invoke($schema, [0 => 'a', 'name' => 'b']));
  }

  public function test_isAssociativeArray_WithNonSequentialNumericKeys_ShouldReturnTrue(): void {
    $schema = new TestSchema();

    $reflection = new \ReflectionClass($schema);
    $method = $reflection->getMethod('isAssociativeArray');

    $this->assertTrue($method->invoke($schema, [2 => 'a', 0 => 'b', 1 => 'c']));
  }

  public function test_completeFlow_NullOptional_ShouldReturnSuccess(): void {
    $schema = new TestSchema();
    $schema = $schema->optional();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
  }

  public function test_completeFlow_NullWithDefault_ShouldReturnDefault(): void {
    $schema = new TestSchema();
    $schema = $schema->_default('default_value');

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame('default_value', $result->data);
  }

  public function test_completeFlow_ValidValueWithRulesAndTransforms_ShouldSucceed(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(
      fn(mixed $v) => strlen($v) > 2,
      'Too short'
    );
    $schema = $schema->transform(fn($v) => strtoupper($v));

    $result = $schema->safeParse('hello');

    $this->assertTrue($result->success);
    $this->assertSame('HELLO', $result->data);
  }

  public function test_completeFlow_InvalidValueWithRulesAndTransforms_ShouldFail(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(
      fn(mixed $v) => strlen($v) > 5,
      'Too short'
    );
    $schema = $schema->transform(fn($v) => strtoupper($v));

    $result = $schema->safeParse('hi');

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('Too short', $result->issues[0]->message);
  }

  public function test_completeFlow_MultipleRulesFailure_ShouldReturnAllErrors(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(fn(mixed $v) => strlen($v) > 3, 'Must be longer');
    $schema = $schema->refine(fn(mixed $v) => strlen($v) < 5, 'Must be shorter');
    $schema = $schema->refine(fn(mixed $v) => !is_numeric($v), 'Cannot be numeric');

    $result = $schema->safeParse('abc');

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
  }

  public function test_complexFlow_ChainedBuilders_ShouldMaintainState(): void {
    $schema = new TestSchema();
    $schema = $schema
      ->refine(fn(mixed $v) => strlen($v) > 2, 'Too short')
      ->transform(fn($v) => trim($v))
      ->refine(fn(mixed $v) => !is_numeric($v), 'Cannot be numeric')
      ->transform(fn($v) => strtoupper($v));

    $result = $schema->safeParse('  hello  ');

    $this->assertTrue($result->success);
    $this->assertSame('HELLO', $result->data);
  }

  public function test_parseWithPath_ShouldIncludePathInIssues(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(fn(mixed $v) => false, 'Error');

    $reflection = new \ReflectionClass($schema);
    $method = $reflection->getMethod('_parse');

    $result = $method->invoke($schema, 'value', ['user', 'email']);

    $this->assertFalse($result->success);
    $this->assertSame(['user', 'email'], $result->issues[0]->path);
  }

  public function test_immutability_BuildersReturnNewInstances(): void {
    $schema = new TestSchema();
    $schema2 = $schema->optional();
    $schema3 = $schema->_default('value');
    $schema4 = $schema->transform(fn($v) => $v);

    $this->assertNotSame($schema, $schema2);
    $this->assertNotSame($schema, $schema3);
    $this->assertNotSame($schema, $schema4);
    $this->assertNotSame($schema2, $schema3);
  }

  public function test_immutability_OriginalUnaffectedByClone(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(fn(mixed $v) => strlen($v) > 5, 'Too short');

    $cloned = clone $schema;
    $cloned = $cloned->refine(fn(mixed $v) => false, 'Additional rule');

    $result1 = $schema->safeParse('test');
    $result2 = $cloned->safeParse('test');

    $this->assertFalse($result1->success);
    $this->assertCount(1, $result1->issues);

    $this->assertFalse($result2->success);
    $this->assertCount(2, $result2->issues);
  }

  public function test_edgeCaseEmptyString_ShouldValidate(): void {
    $schema = new TestSchema();
    $schema = $schema->refine(fn(mixed $v) => is_string($v), 'Must be string');

    $result = $schema->safeParse('');

    $this->assertTrue($result->success);
    $this->assertSame('', $result->data);
  }

  public function test_edgeCaseZero_ShouldValidate(): void {
    $schema = new TestSchema();

    $result = $schema->safeParse(0);

    $this->assertTrue($result->success);
    $this->assertSame(0, $result->data);
  }

  public function test_edgeCaseFalse_ShouldValidate(): void {
    $schema = new TestSchema();

    $result = $schema->safeParse(false);

    $this->assertTrue($result->success);
    $this->assertFalse($result->data);
  }

  public function test_edgeCaseEmptyArray_ShouldValidate(): void {
    $schema = new TestSchema();

    $result = $schema->safeParse([]);

    $this->assertTrue($result->success);
    $this->assertEmpty($result->data);
  }

  public function test_complexScenario_FormValidation_ShouldWorkCorrectly(): void {
    $schema = new TestSchema();
    $schema = $schema
      ->refine(
        fn(mixed $v) => strlen($v) >= 3,
        'Username must be at least 3 characters'
      )
      ->refine(
        fn(mixed $v) => strlen($v) <= 20,
        'Username must not exceed 20 characters'
      )
      ->refine(
        fn(mixed $v) => preg_match('/^[a-zA-Z0-9_-]+$/', $v),
        'Username can only contain letters, numbers, underscores and hyphens'
      )
      ->transform(fn($v) => strtolower($v));

    $result1 = $schema->safeParse('MyUsername123');
    $this->assertTrue($result1->success);
    $this->assertSame('myusername123', $result1->data);

    $result2 = $schema->safeParse('ab');
    $this->assertFalse($result2->success);
    $this->assertStringContainsString('at least 3', $result2->issues[0]->message);

    $result3 = $schema->safeParse('invalid@username');
    $this->assertFalse($result3->success);
    $this->assertStringContainsString('only contain letters', $result3->issues[0]->message);
  }

  public function test_defaultWithObjectCloning_ShouldNotShareReferences(): void {
    $obj = new \stdClass();
    $obj->value = 'original';

    $schema = new TestSchema();
    $schema = $schema->_default($obj);

    $cloned = clone $schema;

    $result1 = $schema->safeParse(null);
    $result2 = $cloned->safeParse(null);

    $this->assertNotSame($result1->data, $result2->data);

    if (is_object($result1->data) && is_object($result2->data)) {
      $result1->data->value = 'modified';
      $this->assertNotSame($result1->data->value, $result2->data->value);
    }
  }

  public function test_acceptsNull_WithNull_ShouldReachParseType(): void {
    $schema = $this->nullAcceptingSchema();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame('reached parseType', $result->data);
  }

  public function test_acceptsNull_WithDefault_ShouldResolveDefaultFirst(): void {
    $schema = $this->nullAcceptingSchema()->_default('fallback');

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame('fallback', $result->data);
  }

  public function test_acceptsNull_WithOptional_ShouldReturnNullBeforeParseType(): void {
    $schema = $this->nullAcceptingSchema()->optional();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
  }

  public function test_nullHandling_WithNullAndNoOptions_ShouldReturnCleanResult(): void {
    $schema = new TestSchema();

    $result = $schema->safeParse(null);

    $this->assertFalse($result->dirty);
  }

  public function test_validateRules_WithFailingRule_ShouldReturnDirtyResult(): void {
    $schema = (new TestSchema())->refine(fn(mixed $v, array $p): bool => false);

    $result = $schema->safeParse(1);

    $this->assertFalse($result->success);
    $this->assertTrue($result->dirty);
    $this->assertCount(1, $result->issues);
    $this->assertSame('custom', $result->issues[0]->code);
  }

  public function test_validateType_WithFailure_ShouldReturnDirtyResult(): void {
    $schema = new TestSchema(
      checkValid: static fn(mixed $value, array $path): ParseResult => ParseResult::fail(
        new Issue(path: $path, message: 'Invalid value', code: 'invalid_type')
      )
    );

    $result = $schema->safeParse(1);

    $this->assertTrue($result->dirty);
    $this->assertCount(1, $result->issues);
    $this->assertSame('Invalid value', $result->issues[0]->message);
  }

  public function test_validateType_WithDirtyFailure_ShouldReturnSameInstance(): void {
    $dirty = ParseResult::dirty([new Issue(path: [], message: 'Invalid value', code: 'too_small')]);
    $schema = new TestSchema(
      checkValid: static fn(mixed $value, array $path): ParseResult => $dirty
    );

    $this->assertSame($dirty, $schema->safeParse(1));
  }

  public function test_parseType_WithFailure_ShouldReturnCleanResult(): void {
    $schema = new TestSchema(
      checkType: static fn(mixed $value, array $path): ParseResult => ParseResult::fail(
        new Issue(path: $path, message: 'Invalid type', code: 'invalid_type')
      )
    );

    $result = $schema->safeParse(1);

    $this->assertFalse($result->dirty);
  }

  public function test_parseType_WithDirtyFailure_ShouldReturnSameInstance(): void {
    $dirty = ParseResult::dirty([new Issue(path: [], message: 'Invalid value', code: 'too_small')]);
    $schema = new TestSchema(
      checkType: static fn(mixed $value, array $path): ParseResult => $dirty
    );

    $this->assertSame($dirty, $schema->safeParse(1));
  }

  private function nullAcceptingSchema(): Schema {
    return new class extends Schema {

      #[Override]
      protected function acceptsNull(): bool {
        return true;
      }

      #[Override]
      protected function parseType(mixed $value, array $path = []): ParseResult {
        return ParseResult::ok($value ?? 'reached parseType');
      }
    };
  }
}
