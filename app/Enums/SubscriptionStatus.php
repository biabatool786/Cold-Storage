<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'TRIAL';
    case Active = 'ACTIVE';
    case PastDue = 'PAST_DUE';
    case Suspended = 'SUSPENDED';
    case Cancelled = 'CANCELLED';
    case Expired = 'EXPIRED';
}
