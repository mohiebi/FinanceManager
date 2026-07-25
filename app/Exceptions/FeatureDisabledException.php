<?php

namespace App\Exceptions;

use App\Enums\Feature;
use RuntimeException;

/**
 * Thrown when a change is attempted against a module the user has switched off.
 */
class FeatureDisabledException extends RuntimeException
{
    public function __construct(public readonly Feature $feature)
    {
        parent::__construct(__('modules.locked', ['module' => $feature->label()]));
    }
}
