<?php

namespace app\models\Enum;

enum Category: string
{
    case BILLING = 'billing';
    case ACCOUNT = 'account';
    case TECHNICAL = 'technical';
    case PRIVACY = 'privacy';
    case SECURITY = 'security';
    case GENERAL = 'general';
}