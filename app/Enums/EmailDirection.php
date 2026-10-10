<?php

namespace App\Enums;

enum EmailDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
