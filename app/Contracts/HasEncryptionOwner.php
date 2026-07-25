<?php

namespace App\Contracts;

/**
 * A model whose encrypted attributes belong to a specific user's data key.
 *
 * Declared explicitly rather than sniffed for a `user_id` attribute, so that a
 * model which stores encrypted data under a different ownership shape has a
 * documented place to say so.
 */
interface HasEncryptionOwner
{
    /**
     * The id of the user whose data key protects this model's encrypted columns.
     */
    public function encryptionOwnerId(): ?int;
}
