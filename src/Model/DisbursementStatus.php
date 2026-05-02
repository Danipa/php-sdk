<?php

declare(strict_types=1);

namespace Danipa\Sdk\Model;

enum DisbursementStatus: string
{
    case PENDING = 'PENDING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
}
