<?php

namespace App\Ai\Exceptions;

use RuntimeException;

/**
 * The AI could not answer: missing key, every model failed or the answer was invalid.
 */
class AiException extends RuntimeException {}
