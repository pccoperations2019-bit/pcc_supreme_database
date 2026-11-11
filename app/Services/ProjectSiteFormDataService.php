<?php

namespace App\Services;

use App\Enums\AddressDistrictEnum;
use App\Enums\ConstructionTypeEnum;
use App\Enums\ContractTypeEnum;
use App\Enums\DairyBoxEnum;
use App\Enums\EquipmentNameEnum;
use App\Enums\OwnershipTypeEnum;
use App\Enums\PartnerCategoryEnum;
use App\Enums\ValueChainStatusEnum;
use App\Models\Center;
use App\Models\DairyBox;
use App\Models\Partner;
use App\Models\Project;
use App\Models\ProjectSite;
use App\Models\User;
use App\Support\Centers;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Builds the reference data (dropdown options, enum labels, etc.) shared by
 * every ProjectSite Inertia page. Every project-type controller (CBIN, CCDP, ...)
 * can reuse this instead of duplicating the same five queries.
 */
class ProjectSiteFormDataService
{
    public function centers(): Collection
    {
        return Center::query()
            ->excludingHidden(Centers::hiddenCenters())
            ->visibleTo($this->user())
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    public function partners(): Collection
    {
        return Partner::query()
            ->with('address')
            ->visibleTo($this->user())
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'center_id']);
    }

    public function projects(?string $program = null): Collection
    {
        return Project::query()
            ->when($program, fn ($query) => $query->where('program', $program))
            ->orderBy('name')
            ->get(['id', 'name', 'program', 'project_involvement']);
    }

    public function dairyBoxes(): Collection
    {
        return DairyBox::query()
            ->with(['address', 'partner'])
            ->visibleTo($this->user())
            ->orderBy('dairy_box_id')
            ->get(['id', 'partner_id', 'dairy_box_id', 'business_name', 'manager', 'status', 'launched_date']);
    }

    /**
     * Distinct farmer-organization names previously stored in
     * `project_sites.metadata -> partner_farmer_org` (mostly CCDP imports).
     * Used as suggestions for the Farmer Organization field; free text is
     * still allowed since these are open-ended names, not a lookup table.
     *
     * @return array<int, string>
     */
    public function farmerOrgs(): array
    {
        return ProjectSite::query()
            ->visibleTo($this->user())
            ->whereNotNull('metadata->partner_farmer_org')
            ->pluck('metadata')
            ->map(fn ($metadata) => trim((string) ($metadata['partner_farmer_org'] ?? '')))
            ->filter()
            ->reject(fn (string $name): bool => is_numeric($name))
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Whether the signed-in user is confined to a single center, so the create
     * form can disable the center field and pre-fill it.
     */
    public function isLockedToOwnCenter(): bool
    {
        return $this->user()?->isCenterScoped() === true;
    }

    public function defaultCenterId(): ?int
    {
        return $this->isLockedToOwnCenter() ? $this->user()?->center_id : null;
    }

    protected function user(): ?User
    {
        return Auth::user();
    }

    public function enums(): array
    {
        return [
            'constructionTypes' => ConstructionTypeEnum::labels(),
            'valueChainStatuses' => ValueChainStatusEnum::labels(),
            'addressDistrict' => AddressDistrictEnum::labels(),
            'dairyBoxStatuses' => DairyBoxEnum::labels(),
            'equipmentNames' => EquipmentNameEnum::labels(),
            'partnerCategories' => PartnerCategoryEnum::labels(),
            'ownershipTypes' => OwnershipTypeEnum::labels(),
            'contractTypes' => ContractTypeEnum::labels(),
        ];
    }

    /**
     * Full prop bag consumed by ->render() calls.
     *
     * @param  bool  $scopeProjectsToProgram  Create page filters the project list to the
     *                                        current program; index/show/edit show all projects
     *                                        (matches the original controller's behaviour).
     */
    public function sharedProps(?string $program = null, bool $scopeProjectsToProgram = false): array
    {
        return [
            'centers' => $this->centers(),
            'partners' => $this->partners(),
            'projects' => $this->projects($scopeProjectsToProgram ? $program : null),
            'dairyBoxes' => $this->dairyBoxes(),
            'farmerOrgs' => $this->farmerOrgs(),
            'enums' => $this->enums(),
            // A centre-level account cannot choose a center: center_id is pinned
            // server-side, so the field is disabled rather than merely pre-filled.
            'disableCenterField' => $this->isLockedToOwnCenter(),
            'defaultCenterId' => $this->defaultCenterId(),
        ];
    }
}
