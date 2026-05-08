<?php

namespace App\Enums;

enum CommissionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Cancelled = 'cancelled';
}
