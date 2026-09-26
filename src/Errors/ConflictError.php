<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/** HTTP 409 — e.g. `IDEMPOTENCY_KEY_COLLISION`: the key was already used with a different body. */
final class ConflictError extends DanipaApiError
{
}
