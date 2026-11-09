<?php

namespace Esliph\Validator\Schemas;

abstract class CoercibleSchema extends Schema {

  public function coerce(): static {
    $clone = clone $this;
    $clone->coerce = true;

    return $clone;
  }
}
