<?php

namespace Esliph\Validator\Tests\Schemas\Primitive;

use Closure;
use PHPUnit\Framework\TestCase;

use Esliph\Validator\Schemas\Primitive\FloatSchema;
use Esliph\Validator\Schemas\Primitive\IntegerSchema;
use Esliph\Validator\Schemas\Primitive\NumberSchema;

/**
 * O oráculo são as regexes `INT_PATTERN` e `FLOAT_PATTERN` do NumberSchema, copiadas aqui:
 * uma troca da coerção no src/ é comparada contra elas.
 */
class NumericCoercionEquivalenceTest extends TestCase {

  private const string INT_PATTERN = '/^[+-]?\d+\z/';

  private const string FLOAT_PATTERN = '/^[+-]?(\d+(\.\d+)?|\.\d+)([eE][+-]?\d+)?\z/';

  private const string OUT_OF_RANGE_MESSAGE = 'Expected int, received number outside the int range';

  public function test_coerceInput_WithIntegerGrid_ShouldMatchRegexOracle(): void {
    $schema = new IntegerSchema();

    $mismatches = self::coercionMismatches($schema, self::integerOracle(...));

    $this->assertSame([], $mismatches);
  }

  public function test_coerceInput_WithFloatGrid_ShouldMatchRegexOracle(): void {
    $schema = new FloatSchema();

    $mismatches = self::coercionMismatches($schema, self::floatOracle(...));

    $this->assertSame([], $mismatches);
  }

  public function test_coerceInput_WithNumberGrid_ShouldMatchRegexOracle(): void {
    $schema = new NumberSchema();

    $mismatches = self::coercionMismatches($schema, self::numberOracle(...));

    $this->assertSame([], $mismatches);
  }

  public function test_parse_WithIntegerGrid_ShouldReportOutOfRangeExactlyWhenOracleDoes(): void {
    $schema = (new IntegerSchema())->coerce();

    $mismatches = self::mismatches(
      static fn(string $input): bool => self::isOutOfRangeOracle($input),
      static function (string $input) use ($schema): bool {
        $result = $schema->safeParse($input);

        return !$result->success && $result->issues[0]->message === self::OUT_OF_RANGE_MESSAGE;
      }
    );

    $this->assertSame([], $mismatches);
  }

  /**
   * @param Closure(string): mixed $oracle
   *
   * @return list<array{string, mixed, mixed}>
   */
  private static function coercionMismatches(NumberSchema $schema, Closure $oracle): array {
    $coerce = Closure::bind(
      static fn(NumberSchema $schema, mixed $value): mixed => $schema->coerceInput($value),
      null,
      NumberSchema::class
    );

    return self::mismatches($oracle, static fn(string $input): mixed => $coerce($schema, $input));
  }

  /**
   * @param Closure(string): mixed $expected
   * @param Closure(string): mixed $actual
   *
   * @return list<array{string, mixed, mixed}>
   */
  private static function mismatches(Closure $expected, Closure $actual): array {
    $mismatches = [];

    foreach (self::grid() as $input) {
      $want = $expected($input);
      $got = $actual($input);

      if ($want !== $got) {
        $mismatches[] = [$input, $want, $got];
      }
    }

    return $mismatches;
  }

  /**
   * @return list<string>
   */
  private static function grid(): array {
    $grid = [];

    foreach (['', '+', '-'] as $sign) {
      foreach (['', '0', '007', '123'] as $integer) {
        foreach (['', '.', '.5', '.50'] as $fraction) {
          foreach (['', 'e', 'e5', 'E+5', 'e-05'] as $exponent) {
            $number = $sign . $integer . $fraction . $exponent;

            array_push($grid, $number, 'x' . $number, $number . 'x');
          }
        }
      }
    }

    $extras = ["\f1", "\v1", ' 12 ', '1 2', "1\x002", '1_000', '0x1A', 'INF', 'NAN', '1e999', '١٢', '１２'];

    $long = [];

    foreach (['123456789012345678', '1234567890123456789', '12345678901234567890'] as $digits) {
      array_push($long, $digits, '+' . $digits, '-' . $digits);
    }

    $bounds = [
      '9223372036854775807',
      '9223372036854775808',
      '-9223372036854775808',
      '-9223372036854775809',
      '0000000000000000001',
      '-0009223372036854775808',
    ];

    return [...$grid, ...$extras, ...$long, ...$bounds];
  }

  private static function integerOracle(string $input): mixed {
    $trimmed = trim($input);

    if ($trimmed === '') {
      return null;
    }

    if (preg_match(self::INT_PATTERN, $trimmed) === 1) {
      return self::exactInt($trimmed) ?? $input;
    }

    return $input;
  }

  private static function floatOracle(string $input): mixed {
    $trimmed = trim($input);

    if ($trimmed === '') {
      return null;
    }

    return preg_match(self::FLOAT_PATTERN, $trimmed) === 1 ? (float) $trimmed : $input;
  }

  private static function numberOracle(string $input): mixed {
    $trimmed = trim($input);

    if ($trimmed === '') {
      return null;
    }

    if (preg_match(self::FLOAT_PATTERN, $trimmed) !== 1) {
      return $input;
    }

    if (preg_match(self::INT_PATTERN, $trimmed) === 1) {
      return self::exactInt($trimmed) ?? (float) $trimmed;
    }

    return (float) $trimmed;
  }

  private static function isOutOfRangeOracle(string $input): bool {
    $trimmed = trim($input);

    return strlen($input) >= 19
      && preg_match(self::INT_PATTERN, $trimmed) === 1
      && self::exactInt($trimmed) === null;
  }

  /**
   * Recebe uma string que casa com INT_PATTERN. O int vale só se a volta para string
   * dá a forma canônica: sem zeros à esquerda e com `-` só para negativo não zero.
   */
  private static function exactInt(string $trimmed): ?int {
    $digits = ltrim(ltrim($trimmed, '+-'), '0');
    $canonical = $digits === '' ? '0' : ($trimmed[0] === '-' ? '-' : '') . $digits;

    return (string) (int) $trimmed === $canonical ? (int) $trimmed : null;
  }
}
