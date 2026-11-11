<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the LLM provider is unavailable or returns something we can't use.
 * Jobs let it bubble up so the queue retries them instead of silently dropping data.
 */
class AiRequestException extends RuntimeException {}
