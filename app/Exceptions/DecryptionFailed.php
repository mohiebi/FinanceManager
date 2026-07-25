<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A ciphertext could not be authenticated or decoded.
 *
 * Deliberately carries no detail about which check failed — callers get "it did
 * not decrypt", never an oracle.
 */
class DecryptionFailed extends RuntimeException {}
