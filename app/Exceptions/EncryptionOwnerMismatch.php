<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A model was encrypted under one user's data key but saved against another user.
 *
 * This is caught at save time because the alternative is silent, unrecoverable
 * corruption: the row would be readable by nobody.
 */
class EncryptionOwnerMismatch extends RuntimeException {}
