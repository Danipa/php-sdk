<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/**
 * Base class for every error this SDK throws. Catch this once, then narrow
 * with `instanceof DanipaApiError` for HTTP failures or `instanceof
 * DanipaNetworkError` for transport failures.
 */
abstract class DanipaError extends \RuntimeException
{
}
