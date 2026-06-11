<?php

namespace app\models\Enum;

enum EnvelopeStatus: string
{
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case PARTIAL = 'partial';
    case DUPLICATE = 'duplicate';
    case REQUIRES_HUMAN_APPROVAL = 'requires_human_approval';

}