<?php

namespace App\Enums;

enum SettlementStatus: string
{
    case Queued = 'queued';
    case Submitted = 'submitted';
    case Processing = 'processing';
    case Completed = 'completed';
    case RetryableFailure = 'retryable_failure';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';
}
