<?php

namespace App\Support\Tenancy;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class TenantContext
{
    protected ?User $user;

    protected ?string $selectedCenterId;

    public function __construct()
    {
        $this->user = Auth::user();
        $this->selectedCenterId = session('selected_center_id');
    }

    /**
     * Check if user operates at agency level.
     */
    public function isAgencyLevel(): bool
    {
        return $this->user?->isPccLevel() ?? false;
    }

    /**
     * Check if user operates at center level.
     */
    public function isCenterLevel(): bool
    {
        return $this->user?->isCenterScoped() ?? false;
    }

    /**
     * Get the user's agency ID.
     */
    public function agencyId(): ?string
    {
        return $this->user?->agency_id;
    }

    /**
     * Get the user's center ID.
     */
    public function centerId(): ?string
    {
        return $this->user?->center_id;
    }

    /**
     * Get the selected center ID (from session or fallback to user’s own center).
     */
    public function selectedCenterId(): ?string
    {
        return $this->selectedCenterId ?? $this->centerId();
    }

    /**
     * Determine if the user has access to a specific agency or center.
     */
    public function canAccessAgency(string $agencyId): bool
    {
        return $this->agencyId() === $agencyId;
    }

    public function canAccessCenter(string $centerId): bool
    {
        return $this->centerId() === $centerId || $this->selectedCenterId() === $centerId;
    }
}
