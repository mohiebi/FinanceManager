<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A write was attempted against an encrypted column while the owning user's vault
 * is armed, so the server has no key to encrypt with.
 *
 * This throws rather than degrading: silently persisting plaintext into a column
 * everything else treats as ciphertext is a far worse failure than a 500.
 */
class VaultLocked extends RuntimeException {}
