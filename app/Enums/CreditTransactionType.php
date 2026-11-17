<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case Grant = 'grant';                   // free credit, such as the signup bonus
    case Purchase = 'purchase';
    case Charge = 'charge';                 // a succeeded generation
}
