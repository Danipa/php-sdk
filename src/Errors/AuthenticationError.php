<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/** HTTP 401 — the API key is missing, invalid or expired (`INVALID_API_KEY`). */
final class AuthenticationError extends DanipaApiError
{
}
