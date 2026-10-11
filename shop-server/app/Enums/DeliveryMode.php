<?php

namespace App\Enums;

enum DeliveryMode: string
{
    case GtgRider = 'gtg_rider';
    case SellerOwnRider = 'seller_own_rider';
    case CustomerPickup = 'customer_pickup';
}
