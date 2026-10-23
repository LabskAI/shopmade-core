<?php

namespace App\Core\Agents;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class AgentRefusedException extends RuntimeException implements ShouldntReport
{
    public function __construct(string $message, public readonly string $reason, public readonly ?string $hint = null)
    {
        parent::__construct($message);
    }

    public function mcpCode(): int
    {
        return match ($this->reason) {
            'path_refused' => -32005,
            'budget_exhausted' => -32006,
            'forbidden', 'risk_gate' => -32002,
            'invalid_params' => -32602,
            default => -32004,
        };
    }
}
