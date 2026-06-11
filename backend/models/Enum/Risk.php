<?php

namespace app\models\Enum;

enum Risk: string
{
    case NONE = 'none';
    case PRIVACY = 'privacy';
    case SECURITY = 'security';
    case MONEY_MOVEMENT = 'money_movement';
    case EXTERNAL_SEND = 'external_send';
    case DESTRUCTIVE_ACTION = 'destructive_action';
}
