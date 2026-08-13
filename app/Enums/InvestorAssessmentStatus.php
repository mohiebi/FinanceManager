<?php

namespace App\Enums;

enum InvestorAssessmentStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
