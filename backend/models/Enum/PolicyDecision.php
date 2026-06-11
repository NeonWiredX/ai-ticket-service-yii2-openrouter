<?php

namespace app\models\Enum;

enum PolicyDecision: string
{
    case ALLOWED = 'allowed';
    case REQUIRES_APPROVAL = 'requires_approval';
    case BLOCKED = 'blocked';
}
