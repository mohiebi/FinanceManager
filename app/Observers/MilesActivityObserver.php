<?php

namespace App\Observers;

use App\Actions\Miles\AwardDailyActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MilesActivityObserver
{
    public function __construct(private readonly AwardDailyActivity $awardDailyActivity) {}

    public function created(Model $model): void
    {
        $this->awardDailyActivity->forUserId(
            (int) $model->getAttribute('user_id'),
            Str::snake(class_basename($model)),
        );
    }
}
