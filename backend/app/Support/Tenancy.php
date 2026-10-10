<?php

namespace App\Support;

use App\Models\Tenant;

/** Holds the tenant of the current request/job. Never set from client input. */
class Tenancy
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->id;
    }

    public function run(Tenant $tenant, callable $fn): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;
        try {
            return $fn();
        } finally {
            $this->tenant = $previous;
        }
    }
}
