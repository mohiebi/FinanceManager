<?php

namespace App\Enums;

enum ReferralStage: string
{
    case Activated = 'activated';
    case Retained = 'retained';
    case Habit = 'habit';
}
