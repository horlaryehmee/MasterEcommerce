<?php

namespace App\Exceptions;

use RuntimeException;

/** A coupon that can't be used; the message is shown to the customer. */
class CouponException extends RuntimeException {}
