<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/** HTTP 5xx — the server failed. 502/503/504 were already retried; 500/501 are never retried. */
final class ServerError extends DanipaApiError
{
}
