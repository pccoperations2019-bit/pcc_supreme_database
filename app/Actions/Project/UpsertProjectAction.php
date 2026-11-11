<?php

namespace App\Actions\Project;

use App\Enums\ProjectProgramEnum;
use App\Models\Project;

class UpsertProjectAction
{
    public function execute(
        string $name,
        ProjectProgramEnum $program,
        bool $isFunded,
        ?string $description = null,
        ?array $projectInvolvement = null,
    ): Project {
        return Project::updateOrCreate(
            [
                'program' => $program,
                'name' => $name,
            ],
            array_filter([
                'is_funded' => $isFunded,
                'description' => $description,
                'project_involvement' => $projectInvolvement,
            ])
        );
    }
}
