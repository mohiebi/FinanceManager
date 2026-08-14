<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The chain could not be reached, or answered with something unusable.
 *
 * Strictly an operator-side failure, and kept distinct from every buyer-side
 * verdict for one reason: a payment must never be marked failed because our
 * node was down. Real money can be genuinely paid and still land here, so the
 * only correct response is to retry and, failing that, to ask a human.
 */
class ExplorerUnavailable extends RuntimeException {}
