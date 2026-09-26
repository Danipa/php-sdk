<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/** One field-level validation failure from the `ApiStatus.fieldErrors[]` envelope. */
final class FieldError
{
    public function __construct(
        public readonly ?string $field,
        public readonly ?string $code,
        public readonly ?string $message,
    ) {
    }
}
