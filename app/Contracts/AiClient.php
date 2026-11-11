<?php

namespace App\Contracts;

use App\Exceptions\AiRequestException;

interface AiClient
{
    /**
     * Send a prompt and get the model answer decoded from JSON.
     *
     * @throws AiRequestException
     */
    public function json(string $prompt): array;

    /**
     * Send a prompt and get a plain text (markdown) answer.
     *
     * @throws AiRequestException
     */
    public function text(string $prompt): string;
}
