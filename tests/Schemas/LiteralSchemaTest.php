<?php

namespace Esliph\Validator\Tests\Schemas;

use PHPUnit\Framework\Attributes\DataProvider;

use Esliph\Validator\V;
use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\Schemas\LiteralSchema;
use Esliph\Validator\Tests\Fixtures\StringBacked;

use Closure;
use InvalidArgumentException;
use Override;
use UnitEnum;

class LiteralSchemaTest extends BaseSchemaTestCase {

  #[Override]
  protected function createSchema(): Schema {
    return new LiteralSchema('active');
  }

  #[Override]
  protected function getValidValue(): mixed {
    return 'active';
  }

  #[Override]
  protected function getInvalidValue(): mixed {
    return 'inactive';
  }

  #[Override]
  protected function getDefaultValue(): mixed {
    return 'active';
  }

  #[Override]
  protected function getTransformFunction(): Closure {
    return fn(string $value): string => strtoupper($value);
  }

  #[Override]
  protected function getRefineCheck(): Closure {
    return fn(mixed $value, array $params): bool => $value === 'active';
  }

  /**
   * @return array<string, array{string|int|float|bool|null|UnitEnum, string}>
   */
  public static function provideLiteralMessages(): array {
    return [
      'string' => ['a', "Expected literal 'a'"],
      'float' => [1.5, 'Expected literal 1.5'],
      'bool' => [true, 'Expected literal true'],
      'null' => [null, 'Expected literal NULL'],
      'enum_case' => [StringBacked::Active, 'Expected literal Esliph\Validator\Tests\Fixtures\StringBacked::Active'],
    ];
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithAllInputTypes_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = new LiteralSchema(42);

    $result = $schema->safeParse($value);

    if ($value === 42) {
      $this->assertTrue($result->success, "Type $typeName should be valid");
      $this->assertSame(42, $result->data);
      return;
    }

    $this->assertFalse($result->success, "Type $typeName should be invalid");
    $this->assertCount(1, $result->issues);

    if ($value === null) {
      $this->assertSame('required', $result->issues[0]->code);
      return;
    }

    $this->assertSame('invalid_literal', $result->issues[0]->code);
    $this->assertSame('Expected literal 42', $result->issues[0]->message);
  }

  public function test_parseType_WithIntLiteral_ShouldRejectFloatAndNumericString(): void {
    $schema = V::literal(1);

    foreach ([1.0, '1'] as $value) {
      $result = $schema->safeParse($value);

      $this->assertFalse($result->success);
      $this->assertSame('invalid_literal', $result->issues[0]->code);
    }
  }

  public function test_parseType_WithEnumCase_ShouldAcceptOnlySameCase(): void {
    $schema = V::literal(StringBacked::Active);

    $result = $schema->safeParse(StringBacked::Active);

    $this->assertTrue($result->success);
    $this->assertSame(StringBacked::Active, $result->data);
    $this->assertFalse($schema->safeParse(StringBacked::Inactive)->success);
    $this->assertFalse($schema->safeParse('active')->success);
  }

  public function test_parseType_WithNullLiteral_ShouldAcceptNull(): void {
    $schema = V::literal(null);

    $valid = $schema->safeParse(null);
    $invalid = $schema->safeParse(0);

    $this->assertTrue($valid->success);
    $this->assertNull($valid->data);
    $this->assertFalse($invalid->success);
    $this->assertSame('invalid_literal', $invalid->issues[0]->code);
    $this->assertSame('Expected literal NULL', $invalid->issues[0]->message);
  }

  public function test_parseType_WithInfLiteral_ShouldAcceptInf(): void {
    $this->assertTrue(V::literal(INF)->safeParse(INF)->success);
  }

  #[DataProvider('provideLiteralMessages')]
  public function test_parseType_WithFailure_ShouldFormatLiteralInMessage(string|int|float|bool|null|UnitEnum $literal, string $message): void {
    $result = V::literal($literal)->safeParse([]);

    $this->assertFalse($result->success);
    $this->assertSame($message, $result->issues[0]->message);
  }

  public function test_construct_WithNan_ShouldThrowInvalidArgumentException(): void {
    $this->expectException(InvalidArgumentException::class);

    new LiteralSchema(NAN);
  }

  public function test_getValue_ShouldReturnLiteral(): void {
    $this->assertSame(StringBacked::Active, V::literal(StringBacked::Active)->getValue());
  }

  public function test_default_WithEnumCase_ShouldReturnSameCase(): void {
    $schema = V::literal(StringBacked::Active)->_default(StringBacked::Active)->optional();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame(StringBacked::Active, $result->data);
  }

  public function test_literal_FromFactory_ShouldReturnLiteralSchema(): void {
    $this->assertInstanceOf(LiteralSchema::class, V::literal('a'));
  }
}
