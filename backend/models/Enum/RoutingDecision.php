<?php

namespace app\models\Enum;

enum RoutingDecision: string
{
    case AUTO_REPLY_CANDIDATE = 'auto_reply_candidate';
    case SUPPORT_QUEUE = 'support_queue';
    case ENGINEERING_INCIDENT = 'engineering_incident';
    case SECURITY_ESCALATION = 'security_escalation';
    case PRIVACY_REVIEW = 'privacy_review';
    case HUMAN_REVIEW = 'human_review';

    case MANUAL_TRIAGE = 'manual_triage';

}
