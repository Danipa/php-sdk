<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

/**
 * Invoice lifecycle, mirroring the server's `Invoice.InvoiceStatus`. A value
 * the server adds later parses as {@see self::UNKNOWN} instead of throwing;
 * the original string is kept on `Invoice::$statusRaw`.
 */
enum InvoiceStatus: string
{
    case DRAFT = 'DRAFT';
    case SENT = 'SENT';
    case PAID = 'PAID';
    case OVERDUE = 'OVERDUE';
    case VOIDED = 'VOIDED';
    case UNKNOWN = 'UNKNOWN';

    public static function parse(?string $value): self
    {
        return $value === null ? self::UNKNOWN : (self::tryFrom($value) ?? self::UNKNOWN);
    }
}
