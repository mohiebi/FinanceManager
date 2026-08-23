<?php

namespace App\Enums;

enum ScreeningStage: string
{
    case Settlement = 'settlement';
    case Sweep = 'sweep';
    case PreCheck = 'pre_check';
}
