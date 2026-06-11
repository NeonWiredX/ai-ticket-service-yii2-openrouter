<?php

namespace app\models\Enum;

enum ClassificationStatus: string
{
    case COMPLETED = 'completed';
    case FAILED = 'failed';

}