<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/** HTTP 400 / 422 — the request was rejected; inspect `$fieldErrors`. */
final class ValidationError extends DanipaApiError
{
}
