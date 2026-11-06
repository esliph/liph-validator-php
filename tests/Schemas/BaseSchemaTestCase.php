<?php

namespace Esliph\Validator\Tests\Schemas;

use Closure;
use InvalidArgumentException;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\Errors\ValidatorException;

abstract class BaseSchemaTestCase extends TestCase {

  abstract protected function createSchema(): Schema;

  public static function provideAllInputTypes(): array {
    return [
      'null' => [null, 'null'],
      'boolean_true' => [true, 'boolean'],
      'boolean_false' => [false, 'boolean'],
      'integer_zero' => [0, 'integer'],
      'integer_positive' => [42, 'integer'],
      'integer_negative' => [-100, 'integer'],
      'integer_large' => [PHP_INT_MAX, 'integer'],
      'float_zero' => [0.0, 'double'],
      'float_positive' => [3.14159, 'double'],
      'float_negative' => [-2.71828, 'double'],
      'float_infinity' => [INF, 'double'],
      'float_negative_infinity' => [-INF, 'double'],
      'float_nan' => [NAN, 'double'],
      'string_empty' => ['', 'string'],
      'string_numeric' => ['123', 'string'],
      'string_numeric_float' => ['45.67', 'string'],
      'string_alpha' => ['abc', 'string'],
      'string_special' => ['!@#$%', 'string'],
      'string_whitespace' => ['   ', 'string'],
      'array_empty' => [[], 'array'],
      'array_indexed' => [['a', 'b', 'c'], 'array'],
      'array_associative' => [['key' => 'value'], 'array'],
      'object_stdclass' => [new \stdClass(), 'object'],
      'object_closure' => [fn() => null, 'object'],
      'resource' => [fopen('php://memory', 'r'), 'resource'],
    ];
  }

  public function test_schema_WithNullValue_ShouldFailRequiredValidation(): void {
    $schema = $this->createSchema();

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertEmpty($result->data);
    $this->assertNotEmpty($result->issues);
    $this->assertSame('required', $result->issues[0]->code);
  }

  public function test_schema_WithNullAndOptional_ShouldSucceed(): void {
    $schema = $this->createSchema()->optional();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
    $this->assertEmpty($result->issues);
  }

  public function test_schema_WithNullAndDefaultValue_ShouldUseDefault(): void {
    $defaultValue = $this->getDefaultValue();
    $schema = $this->createSchema()->_default($defaultValue);

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertEquals($defaultValue, $result->data);
  }

  public function test_schema_Clone_ShouldCreateIndependentCopy(): void {
    $schema = $this->createSchema();
    $cloned = clone $schema;

    $this->assertNotSame($schema, $cloned);
  }

  public function test_schema_WithTransform_ShouldModifyValue(): void {
    $schema = $this->createSchema();
    $schema = $schema->transform($this->getTransformFunction());

    $result = $schema->safeParse($this->getValidValue());

    $this->assertTrue($result->success);
  }

  public function test_schema_ParseWithInvalidType_ShouldThrowValidatorException(): void {
    $this->expectException(ValidatorException::class);

    $schema = $this->createSchema();
    $schema->parse($this->getInvalidValue());
  }

  public function test_schema_SafeParseWithInvalidType_ShouldReturnFailedResult(): void {
    $schema = $this->createSchema();

    $result = $schema->safeParse($this->getInvalidValue());

    $this->assertFalse($result->success);
    $this->assertNotEmpty($result->issues);
  }

  public function test_schema_IsValidWithValidValue_ShouldReturnTrue(): void {
    $schema = $this->createSchema();

    $this->assertTrue($schema->isValid($this->getValidValue()));
  }

  public function test_schema_IsValidWithInvalidValue_ShouldReturnFalse(): void {
    $schema = $this->createSchema();

    $this->assertFalse($schema->isValid($this->getInvalidValue()));
  }

  public function test_schema_WithRefine_ShouldApplyCustomRule(): void {
    $schema = $this->createSchema();
    $schema = $schema->refine(
      $this->getRefineCheck(),
      'Custom validation failed'
    );

    $result = $schema->safeParse($this->getValidValue());

    $this->assertTrue($result->success);
  }

  public function test_schema_WithMultipleTransforms_ShouldChain(): void {
    $schema = $this->createSchema();
    $schema = $schema->transform($this->getTransformFunction());
    $schema = $schema->transform($this->getTransformFunction());

    $result = $schema->safeParse($this->getValidValue());

    $this->assertTrue($result->success);
  }

  /**
   * Código que o schema emite e uma entrada que o dispara, usados pelos testes
   * de `messages()` que não dependem do `required`.
   *
   * @return array{string, mixed}
   */
  protected function messageProbe(): array {
    return ['required', null];
  }

  public function test_messages_WithRequiredString_ShouldUseCustomMessage(): void {
    $result = $this->createSchema()->messages(['required' => 'Informe o valor'])->safeParse(null);

    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame('Informe o valor', $result->issues[0]->message);
  }

  public function test_messages_WithRequiredClosure_ShouldReceiveNullAndEmptyParams(): void {
    $received = null;
    $schema = $this->createSchema()->messages([
      'required' => function (mixed $value, array $params) use (&$received): string {
        $received = [$value, $params];
        return 'custom';
      },
    ]);

    $result = $schema->safeParse(null);

    $this->assertSame('custom', $result->issues[0]->message);
    $this->assertSame([null, []], $received);
  }

  public function test_messages_WithUnknownCode_ShouldThrowListingAllowedCodes(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("Unknown message code 'nope'; allowed: ");

    $this->createSchema()->messages(['nope' => 'x']);
  }

  public function test_messages_WithNonStringNonClosureValue_ShouldThrow(): void {
    [$code] = $this->messageProbe();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("Message for code '$code' must be a string or Closure");

    /** @phpstan-ignore argument.type */
    $this->createSchema()->messages([$code => 42]);
  }

  public function test_messages_ShouldNotMutateOriginalSchema(): void {
    [$code, $input] = $this->messageProbe();
    $base = $this->createSchema();

    $custom = $base->messages([$code => 'custom']);

    $this->assertSame('custom', $custom->safeParse($input)->issues[0]->message);
    $this->assertNotSame('custom', $base->safeParse($input)->issues[0]->message);
  }

  /**
   * Falso nos schemas que não emitem `invalid_type` (Mixed, Literal, Enum, Union).
   */
  protected function emitsInvalidType(): bool {
    return true;
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_messages_WithInvalidTypeClosure_ShouldReceiveDebugTypeOfInput(mixed $value, string $typeName): void {
    if (!$this->emitsInvalidType()) {
      $this->expectNotToPerformAssertions();
      return;
    }

    $schema = $this->createSchema()->messages([
      'invalid_type' => fn(mixed $input, array $params): string => $params['received'],
    ]);

    $result = $schema->safeParse($value);

    $issue = $result->issues[0] ?? null;

    // Issues de filhos (path não vazio) usam as mensagens dos próprios filhos.
    if ($issue === null || $issue->code !== 'invalid_type' || $issue->path !== []) {
      $this->expectNotToPerformAssertions();
      return;
    }

    $this->assertSame(get_debug_type($value), $issue->message, "Type $typeName");
  }

  public function test_messages_CalledTwiceWithSameCode_ShouldUseLastMessage(): void {
    [$code, $input] = $this->messageProbe();

    $schema = $this->createSchema()->messages([$code => 'first'])->messages([$code => 'second']);

    $this->assertSame('second', $schema->safeParse($input)->issues[0]->message);
  }

  abstract protected function getValidValue(): mixed;

  abstract protected function getInvalidValue(): mixed;

  abstract protected function getDefaultValue(): mixed;

  abstract protected function getTransformFunction(): Closure;

  abstract protected function getRefineCheck(): Closure;
}
