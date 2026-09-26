<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/** HTTP 403 — the key may not make this call (`API_KEY_SCOPE_DENIED`, `IP_NOT_ALLOWED`, `REFERER_NOT_ALLOWED`, `AMBIGUOUS_CREDENTIALS`, ...). */
final class PermissionError extends DanipaApiError
{
}
