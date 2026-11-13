<?php

namespace Esliph\Validator\Tests\Schemas\Primitive;

use Closure;
use PHPUnit\Framework\TestCase;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\V;

/**
 * O oráculo é o mbstring: `mb_check_encoding` mais `mb_strlen`, que é como o
 * StringSchema contava antes de qualquer atalho por bytes.
 */
class StringCountEquivalenceTest extends TestCase {

  public function test_min_WithStringAndBoundGrid_ShouldMatchMbstringOracle(): void {
    $mismatches = self::mismatches(
      static fn(int $n): Schema => V::string()->min($n),
      static fn(int $length, int $n): bool => $length >= $n
    );

    $this->assertSame([], $mismatches);
  }

  public function test_max_WithStringAndBoundGrid_ShouldMatchMbstringOracle(): void {
    $mismatches = self::mismatches(
      static fn(int $n): Schema => V::string()->max($n),
      static fn(int $length, int $n): bool => $length <= $n
    );

    $this->assertSame([], $mismatches);
  }

  public function test_length_WithStringAndBoundGrid_ShouldMatchMbstringOracle(): void {
    $mismatches = self::mismatches(
      static fn(int $n): Schema => V::string()->length($n),
      static fn(int $length, int $n): bool => $length === $n
    );

    $this->assertSame([], $mismatches);
  }

  /**
   * @param Closure(int): Schema $build
   * @param Closure(int, int): bool $holds recebe o tamanho em caracteres e o limite
   * @return list<array{string, int, bool, bool}>
   */
  private static function mismatches(Closure $build, Closure $holds): array {
    $mismatches = [];

    foreach (self::strings() as $value) {
      $valid = mb_check_encoding($value, 'UTF-8');
      $length = $valid ? mb_strlen($value, 'UTF-8') : 0;

      for ($n = 0; $n <= strlen($value) + 2; $n++) {
        $expected = $valid && $holds($length, $n);
        $actual = $build($n)->isValid($value);

        if ($actual !== $expected) {
          $mismatches[] = [$value, $n, $expected, $actual];
        }
      }
    }

    return $mismatches;
  }

  /**
   * @return list<string>
   */
  private static function strings(): array {
    return [
      'abc',
      'ação',
      '€uro',
      '𝄞x',
      '',
      "\x80",
      "\xC0\xAF",
      "\xED\xA0\x80",
      "\xE2\x82",
      "a\xC0\xAFb",
    ];
  }
}
