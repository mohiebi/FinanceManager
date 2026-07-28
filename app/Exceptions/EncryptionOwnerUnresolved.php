<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An encrypted attribute was written on a model whose owning user could not be
 * determined, so there is no way to pick the right data key.
 */
class EncryptionOwnerUnresolved extends RuntimeException {}
