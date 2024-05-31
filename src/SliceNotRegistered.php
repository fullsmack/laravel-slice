<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use LogicException;

class SliceNotRegistered extends LogicException
{
    public static function becauseNameIsNotDefined(): self
    {
        return new static(
            'This slice does not have a name.'.
            'You can set one with `$slice->setName("slice-name")`'
        );
    }
}
