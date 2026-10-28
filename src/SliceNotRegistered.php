<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use LogicException;

class SliceNotRegistered extends LogicException implements SliceException
{
    public static function becauseIdentifierIsNotDefined(): self
    {
        return new self(
            'This slice does not have an identifier. '.
            'You can set one with `$slice->setIdentifier("slice-identifier")`'
        );
    }

    public static function becauseSliceIsNotAddedToRegistry(string $identifier): self
    {
        return new self(
            "Slice '{$identifier}' is not registered. " .
                'Make sure the slice exists (see make:slice) and that its service provider is loaded.'
        );
    }
}
