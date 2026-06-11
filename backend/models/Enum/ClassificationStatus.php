<?php

namespace app\models\Enum;

enum ClassificationStatus: string
{
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
}
