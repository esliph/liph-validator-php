<?php

namespace Esliph\Validator\Tests\Schemas;

use PHPUnit\Framework\Attributes\DataProvider;

use Esliph\Validator\V;
use Esliph\Validator\Errors\ValidatorException;
use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\Schemas\MixedSchema;

use Closure;
use InvalidArgumentException;
use Override;
use stdClass;

class MixedSchemaTest extends BaseSchemaTestCase {

  #[Override]
  protected function createSchema(): Schema {
    return new MixedSchema();
  }

  #[Override]
  protected function getValidValue(): mixed {
    return ['key' => 'value'];
  }

  #[Override]
  protected function getInvalidValue(): mixed {
    return null;
  }

  #[Override]
  protected function getDefaultValue(): mixed {
    return 'default';
  }

  #[Override]
  protected function getTransformFunction(): Closure {
    return fn(mixed $value): mixed => $value;
  }

  #[Override]
  protected function getRefineCheck(): Closure {
    return fn(mixed $value, array $params): bool => is_array($value);
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithAllInputTypes_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = new MixedSchema();

    $result = $schema->safeParse($value);

    if ($value === null) {
      $this->assertFalse($result->success, "Type $typeName should be invalid");
      $this->assertSame('required', $result->issues[0]->code);
      $this->assertSame([], $result->issues[0]->path);
      return;
    }

    $this->assertTrue($result->success, "Type $typeName should be valid");
    $this->assertEmpty($result->issues);

    if (is_float($value) && is_nan($value)) {
      $this->assertNan($result->data);
    } else {
      $this->assertSame($value, $result->data, "Type $typeName should be returned unchanged");
    }
  }

  public function test_parseType_WithFalsyNonNullValues_ShouldNotTreatThemAsMissing(): void {
    $schema = new MixedSchema();

    foreach ([false, 0, 0.0, '', '0', []] as $value) {
      $result = $schema->safeParse($value);

      $this->assertTrue($result->success, 'Falsy value ' . var_export($value, true) . ' should be accepted');
      $this->assertSame($value, $result->data);
    }
  }

  public function test_parseType_WithObject_ShouldReturnSameInstance(): void {
    $schema = new MixedSchema();
    $object = new stdClass();
    $object->name = 'John';

    $result = $schema->safeParse($object);

    $this->assertTrue($result->success);
    $this->assertSame($object, $result->data);
  }

  public function test_parseType_WithNestedArray_ShouldReturnItUntouched(): void {
    $schema = new MixedSchema();
    $value = ['a' => [1, 2, ['b' => null]], 'c' => new stdClass()];

    $result = $schema->safeParse($value);

    $this->assertTrue($result->success);
    $this->assertSame($value, $result->data);
  }

  public function test_parseType_WithNumericString_ShouldNotCoerce(): void {
    $schema = new MixedSchema();

    $result = $schema->safeParse('123');

    $this->assertTrue($result->success);
    $this->assertSame('123', $result->data);
  }

  public function test_parseType_WithNull_ShouldFailWithRequiredMessage(): void {
    $schema = new MixedSchema();

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame('Value is required', $result->issues[0]->message);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_messages_WithInvalidTypeCode_ShouldThrow(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("Unknown message code 'invalid_type'; allowed: required");

    (new MixedSchema())->messages(['invalid_type' => 'x']);
  }

  public function test_mixed_FromFactory_ShouldReturnMixedSchema(): void {
    $schema = V::mixed();

    $this->assertInstanceOf(MixedSchema::class, $schema);
    $this->assertNotSame($schema, V::mixed());
  }

  public function test_parse_WithAnyNonNullValue_ShouldReturnValue(): void {
    $schema = new MixedSchema();

    $this->assertSame(42, $schema->parse(42));
    $this->assertSame('text', $schema->parse('text'));
    $this->assertSame([1, 2], $schema->parse([1, 2]));
  }

  public function test_parse_WithNull_ShouldThrowWithRequiredIssue(): void {
    $schema = new MixedSchema();

    try {
      $schema->parse(null);
      $this->fail('Expected ValidatorException');
    } catch (ValidatorException $exception) {
      $issues = $exception->getIssues();

      $this->assertCount(1, $issues);
      $this->assertSame('required', $issues[0]->code);
      $this->assertSame([], $issues[0]->path);
    }
  }

  public function test_isValid_WithAnyNonNullValue_ShouldReturnTrue(): void {
    $schema = new MixedSchema();

    $this->assertTrue($schema->isValid(false));
    $this->assertTrue($schema->isValid(0));
    $this->assertTrue($schema->isValid(''));
    $this->assertTrue($schema->isValid([]));
    $this->assertTrue($schema->isValid(new stdClass()));
    $this->assertFalse($schema->isValid(null));
  }

  public function test_optional_WithNull_ShouldSucceedWithNullData(): void {
    $schema = (new MixedSchema())->optional();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
    $this->assertEmpty($result->issues);
  }

  public function test_optional_WithNonNullValue_ShouldReturnValue(): void {
    $schema = (new MixedSchema())->optional();

    $result = $schema->safeParse('value');

    $this->assertTrue($result->success);
    $this->assertSame('value', $result->data);
  }

  public function test_optional_WithNullAndRefineAndTransform_ShouldSkipBoth(): void {
    $schema = (new MixedSchema())
      ->optional()
      ->refine(fn(mixed $value, array $params): bool => false, 'never passes')
      ->transform(fn(mixed $value): string => 'transformed');

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
  }

  public function test_optional_ShouldNotMutateOriginalSchema(): void {
    $base = new MixedSchema();

    $optional = $base->optional();

    $this->assertTrue($optional->isValid(null));
    $this->assertFalse($base->isValid(null));
  }

  public function test_default_WithNull_ShouldReturnDefaultValue(): void {
    $schema = (new MixedSchema())->_default(['fallback' => true]);

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame(['fallback' => true], $result->data);
  }

  public function test_default_WithFalsyDefault_ShouldReturnFalsyDefault(): void {
    $schema = (new MixedSchema())->_default(false);

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertFalse($result->data);
  }

  public function test_default_WithNonNullValue_ShouldIgnoreDefault(): void {
    $schema = (new MixedSchema())->_default('fallback');

    $result = $schema->safeParse(0);

    $this->assertTrue($result->success);
    $this->assertSame(0, $result->data);
  }

  public function test_default_WithClosure_ShouldReturnClosureResult(): void {
    $schema = (new MixedSchema())->_default(fn(): array => ['generated']);

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame(['generated'], $result->data);
  }

  public function test_default_WithClosure_ShouldCallItOnEveryParse(): void {
    $calls = 0;
    $schema = (new MixedSchema())->_default(function () use (&$calls): int {
      return ++$calls;
    });

    $first = $schema->safeParse(null);
    $second = $schema->safeParse(null);

    $this->assertSame(1, $first->data);
    $this->assertSame(2, $second->data);
  }

  public function test_default_WithOptional_ShouldPreferDefault(): void {
    $schema = (new MixedSchema())->optional()->_default('fallback');

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame('fallback', $result->data);
  }

  public function test_default_ShouldRunThroughRulesAndTransforms(): void {
    $schema = (new MixedSchema())
      ->_default(5)
      ->transform(fn(mixed $value): int => $value * 2);

    $failing = $schema->refine(fn(mixed $value, array $params): bool => $value > 10, 'too low');

    $this->assertSame(10, $schema->safeParse(null)->data);
    $this->assertFalse($failing->safeParse(null)->success);
    $this->assertSame('custom', $failing->safeParse(null)->issues[0]->code);
  }

  public function test_default_ShouldNotMutateOriginalSchema(): void {
    $base = new MixedSchema();

    $withDefault = $base->_default('fallback');

    $this->assertSame('fallback', $withDefault->safeParse(null)->data);
    $this->assertFalse($base->safeParse(null)->success);
  }

  public function test_refine_WithPassingCheck_ShouldReturnValue(): void {
    $schema = (new MixedSchema())->refine(fn(mixed $value, array $params): bool => is_int($value));

    $result = $schema->safeParse(7);

    $this->assertTrue($result->success);
    $this->assertSame(7, $result->data);
  }

  public function test_refine_WithFailingCheck_ShouldFailWithCustomCode(): void {
    $schema = (new MixedSchema())->refine(
      fn(mixed $value, array $params): bool => is_int($value),
      'Must be an integer'
    );

    $result = $schema->safeParse('seven');

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('custom', $result->issues[0]->code);
    $this->assertSame('Must be an integer', $result->issues[0]->message);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_refine_WithClosureMessage_ShouldResolveMessageFromValue(): void {
    $schema = (new MixedSchema())->refine(
      fn(mixed $value, array $params): bool => false,
      fn(mixed $value, array $params): string => 'Rejected ' . gettype($value)
    );

    $result = $schema->safeParse([1]);

    $this->assertFalse($result->success);
    $this->assertSame('Rejected array', $result->issues[0]->message);
  }

  public function test_refine_WithoutMessage_ShouldUseDefaultMessage(): void {
    $schema = (new MixedSchema())->refine(fn(mixed $value, array $params): bool => false);

    $result = $schema->safeParse('anything');

    $this->assertFalse($result->success);
    $this->assertSame('Invalid value', $result->issues[0]->message);
  }

  public function test_refine_ShouldReceiveOriginalValueAndEmptyParams(): void {
    $received = [];
    $object = new stdClass();
    $schema = (new MixedSchema())->refine(function (mixed $value, array $params) use (&$received): bool {
      $received = [$value, $params];
      return true;
    });

    $schema->safeParse($object);

    $this->assertSame($object, $received[0]);
    $this->assertSame([], $received[1]);
  }

  public function test_refine_WithMultipleFailingChecks_ShouldReportAllIssues(): void {
    $schema = (new MixedSchema())
      ->refine(fn(mixed $value, array $params): bool => is_int($value), 'not int')
      ->refine(fn(mixed $value, array $params): bool => $value !== 'bad', 'is bad')
      ->refine(fn(mixed $value, array $params): bool => true, 'never shown');

    $result = $schema->safeParse('bad');

    $this->assertFalse($result->success);
    $this->assertCount(2, $result->issues);
    $this->assertSame('not int', $result->issues[0]->message);
    $this->assertSame('is bad', $result->issues[1]->message);
  }

  public function test_refine_WithFailingCheck_ShouldNotApplyTransforms(): void {
    $called = false;
    $schema = (new MixedSchema())
      ->transform(function (mixed $value) use (&$called): mixed {
        $called = true;
        return $value;
      })
      ->refine(fn(mixed $value, array $params): bool => false, 'fail');

    $result = $schema->safeParse('value');

    $this->assertFalse($result->success);
    $this->assertFalse($called);
  }

  public function test_refine_ShouldNotMutateOriginalSchema(): void {
    $base = new MixedSchema();

    $refined = $base->refine(fn(mixed $value, array $params): bool => false, 'fail');

    $this->assertFalse($refined->isValid('value'));
    $this->assertTrue($base->isValid('value'));
  }

  public function test_transform_WithValue_ShouldReturnTransformedData(): void {
    $schema = (new MixedSchema())->transform(fn(mixed $value): string => gettype($value));

    $result = $schema->safeParse(3.5);

    $this->assertTrue($result->success);
    $this->assertSame('double', $result->data);
  }

  public function test_transform_WithMultipleTransforms_ShouldApplyInOrder(): void {
    $schema = (new MixedSchema())
      ->transform(fn(mixed $value): array => [$value])
      ->transform(fn(array $value): array => [...$value, 'second'])
      ->transform(fn(array $value): string => implode(',', $value));

    $result = $schema->safeParse('first');

    $this->assertTrue($result->success);
    $this->assertSame('first,second', $result->data);
  }

  public function test_transform_ShouldRunAfterRefine(): void {
    $schema = (new MixedSchema())
      ->transform(fn(mixed $value): string => 'transformed')
      ->refine(fn(mixed $value, array $params): bool => $value === 'original', 'refine saw transformed value');

    $result = $schema->safeParse('original');

    $this->assertTrue($result->success);
    $this->assertSame('transformed', $result->data);
  }

  public function test_transform_ShouldNotMutateOriginalSchema(): void {
    $base = new MixedSchema();

    $transformed = $base->transform(fn(mixed $value): string => 'changed');

    $this->assertSame('changed', $transformed->parse('value'));
    $this->assertSame('value', $base->parse('value'));
  }

  public function test_clone_WithRulesAndTransforms_ShouldKeepBehaviorIndependent(): void {
    $schema = (new MixedSchema())
      ->refine(fn(mixed $value, array $params): bool => is_string($value), 'not string')
      ->transform(fn(string $value): string => strtoupper($value));

    $cloned = clone $schema;
    $extended = $cloned->refine(fn(mixed $value, array $params): bool => strlen($value) > 3, 'too short');

    $this->assertSame('ABC', $schema->parse('abc'));
    $this->assertSame('ABC', $cloned->parse('abc'));
    $this->assertFalse($extended->isValid('abc'));
    $this->assertFalse($schema->isValid(1));
  }

  public function test_clone_WithObjectDefault_ShouldNotShareDefaultInstance(): void {
    $default = new stdClass();
    $default->value = 'original';
    $schema = (new MixedSchema())->_default($default)->optional();

    $cloned = clone $schema;
    $cloned->safeParse(null)->data->value = 'mutated';

    $this->assertSame('original', $schema->safeParse(null)->data->value);
  }

  public function test_apply_WithClosure_ShouldReturnConfiguredSchema(): void {
    $schema = (new MixedSchema())->apply(
      fn(MixedSchema $schema): MixedSchema => $schema->optional()
    );

    $this->assertInstanceOf(MixedSchema::class, $schema);
    $this->assertTrue($schema->isValid(null));
  }

  public function test_validateType_AsObjectField_ShouldKeepValueUntouched(): void {
    $payload = new stdClass();
    $payload->meta = ['any' => [1, 'two', null]];
    $schema = V::object(['meta' => V::mixed()]);

    $result = $schema->safeParse($payload);

    $this->assertTrue($result->success);
    $this->assertSame(['any' => [1, 'two', null]], $result->data->meta);
  }

  public function test_validateType_AsMissingObjectField_ShouldReportRequiredWithFieldPath(): void {
    $schema = V::object([
      'outer' => V::object(['meta' => V::mixed()]),
    ]);
    $payload = new stdClass();
    $payload->outer = new stdClass();

    $result = $schema->safeParse($payload);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame(['outer', 'meta'], $result->issues[0]->path);
  }

  public function test_validateType_AsObjectFieldWithFailingRefine_ShouldReportFieldPath(): void {
    $schema = V::object([
      'meta' => V::mixed()->refine(fn(mixed $value, array $params): bool => is_array($value), 'must be array'),
    ]);
    $payload = new stdClass();
    $payload->meta = 'scalar';

    $result = $schema->safeParse($payload);

    $this->assertFalse($result->success);
    $this->assertSame('custom', $result->issues[0]->code);
    $this->assertSame(['meta'], $result->issues[0]->path);
  }

  #[Override]
  protected function emitsInvalidType(): bool {
    return false;
  }
}
