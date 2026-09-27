<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/**
 * The client was configured in a way the SDK refuses to guess about, e.g. an
 * API key whose prefix names no known environment and no explicit base URL.
 * Thrown when the client is built, before anything is sent.
 */
final class ConfigurationError extends \InvalidArgumentException
{
}
