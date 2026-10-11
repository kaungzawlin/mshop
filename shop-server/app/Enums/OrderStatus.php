<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Placed = 'placed';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
}
