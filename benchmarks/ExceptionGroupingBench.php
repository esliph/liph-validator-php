<?php

namespace Esliph\Validator\Benchmarks;

use Esliph\Validator\Errors\Issue;
use Esliph\Validator\Errors\ValidatorException;
use Generator;
use LogicException;
use PhpBench\Attributes as Bench;

/**
 * Cost of `ValidatorException::getMessagesByPath()` over N issues spread across
 * 5 paths. The exception is built in the setUp and is not measured: only the
 * grouping is.
 *
 * Sizes grow 10x per step: a ~10x ratio between steps means linear.
 */
final class ExceptionGroupingBench {

  private ValidatorException $exception;

  /**
   * @return Generator<string, array{size: int}>
   */
  public function provideSizes(): Generator {
    yield 'n=10' => ['size' => 10];
    yield 'n=100' => ['size' => 100];
    yield 'n=1000' => ['size' => 1000];
  }

  /**
   * @param array{size: int} $params
   */
  public function setUp(array $params): void {
    $issues = [];

    for ($i = 0; $i < $params['size']; $i++) {
      $issues[] = new Issue(path: ['items', 'field' . ($i % 5)], message: "message {$i}", code: 'custom');
    }

    $this->exception = new ValidatorException($issues);

    if (count($this->exception->getMessagesByPath()) !== 5) {
      throw new LogicException('Scenario expected 5 paths');
    }
  }

  /**
   * @return array<string, string[]>
   */
  #[Bench\ParamProviders('provideSizes')]
  #[Bench\BeforeMethods('setUp')]
  public function benchMessagesByPath(): array {
    return $this->exception->getMessagesByPath();
  }
}
