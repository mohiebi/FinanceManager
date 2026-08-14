<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * No trustworthy USD rate could be obtained for a settlement asset.
 *
 * This throws rather than degrading, and the distinction matters: the app's
 * other price path, CurrencyConverter, returns zero when quotes are unavailable.
 * The same behaviour here would quote somebody a plan at nothing. Refusing to
 * open the payment at all is the only safe failure.
 */
class QuoteUnavailable extends RuntimeException {}
