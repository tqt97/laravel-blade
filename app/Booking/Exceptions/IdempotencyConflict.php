<?php

namespace App\Booking\Exceptions;

use RuntimeException;

class IdempotencyConflict extends RuntimeException {}
