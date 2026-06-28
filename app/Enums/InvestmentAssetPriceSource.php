<?php

namespace App\Enums;

enum InvestmentAssetPriceSource: string
{
    case Builtin = 'builtin';
    case Manual = 'manual';
    case Formula = 'formula';
    case Json = 'json';
    case Xml = 'xml';
}
