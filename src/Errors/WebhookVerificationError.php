<?php

declare(strict_types=1);

namespace Danipa\Sdk\Errors;

/**
 * Thrown by {@see \Danipa\Sdk\DanipaWebhook::constructEvent()} when a webhook
 * is missing a required header, fails signature / timestamp verification, or
 * carries a body that is not a JSON object. Respond 400/401 and do not act
 * on the payload.
 */
final class WebhookVerificationError extends DanipaError
{
}
