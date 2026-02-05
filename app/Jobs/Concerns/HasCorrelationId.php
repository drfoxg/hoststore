<?php

namespace App\Jobs\Concerns;

use Illuminate\Support\Facades\Log;

trait HasCorrelationId
{
    public ?string $correlationId = null;

    // SET — записать в свойство (перед dispatch)
    public function setCorrelationId(?string $correlationId): static
    {
        $this->correlationId = $correlationId;
        return $this;
    }

    // SETUP — применить к логгеру (в handle)
    protected function setupCorrelationId(): void
    {
        if ($this->correlationId) {
            Log::shareContext([
                'correlation_id' => $this->correlationId,
            ]);
        }
    }
}
