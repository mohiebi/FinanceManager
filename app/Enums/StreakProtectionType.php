<?php

namespace App\Enums;

enum StreakProtectionType: string
{
    case WeeklyGrace = 'weekly_grace';
    case Freeze = 'freeze';
    case Repair = 'repair';
    case Administrative = 'administrative';
}
