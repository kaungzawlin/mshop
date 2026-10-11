<?php

namespace App\Enums;

enum ResellerStatus: string
{
    case Applied = 'applied';
    case Approved = 'approved';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
