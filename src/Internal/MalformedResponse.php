<?php

declare(strict_types=1);

namespace Danipa\Sdk\Internal;

/**
 * Raised by {@see Json} and the model `fromArray()` factories when a response
 * body does not have the shape the SDK expects. It never escapes the SDK: the
 * HTTP layer converts it into a {@see \Danipa\Sdk\Errors\ResponseParseError}
 * that carries the status, request id and idempotency key of the call.
 *
 * @internal
 */
final class MalformedResponse extends \UnexpectedValueException
{
}
