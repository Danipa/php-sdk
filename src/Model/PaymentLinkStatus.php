<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

/**
 * Payment-link state, mirroring the server's `PaymentLink.PaymentLinkStatus`.
 * Unrecognised values parse as {@see self::UNKNOWN}; the original string is
 * kept on `PaymentLink::$statusRaw`.
 */
enum PaymentLinkStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case EXPIRED = 'EXPIRED';
    case UNKNOWN = 'UNKNOWN';

    public static function parse(?string $value): self
    {
        return $value === null ? self::UNKNOWN : (self::tryFrom($value) ?? self::UNKNOWN);
    }
}
