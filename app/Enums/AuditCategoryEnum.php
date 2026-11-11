<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;
use App\Models\User;

enum AuditCategoryEnum: string implements WithLabels
{
    use HasLabels;

    case DOCUMENTS = 'documents';
    case PROFILES = 'profiles';
    case GOALS = 'goals';
    case PRODUCTS = 'products';
    case PROJECTS = 'projects';
    case SERVICES = 'services';
    case DATA = 'data';
    case USERS = 'users';
    case REPORTS = 'reports';
    case SYSTEM = 'system';

    /**
     * Subject morph aliases that belong to each category. Anything unlisted falls
     * through to SYSTEM rather than being hidden.
     *
     * `user` is listed alongside the FQCN because User is deliberately absent
     * from the morph map, so causer_type is stored as `App\Models\User`.
     *
     * @var array<string, list<string>>
     */
    private const SUBJECT_TYPES = [
        self::DOCUMENTS->value => ['document'],
        self::PROFILES->value => ['center', 'partner', 'farmer', 'dairybox', 'herd'],
        self::GOALS->value => ['center_production_goal', 'center_sales_goal'],
        self::PRODUCTS->value => ['product'],
        self::PROJECTS->value => ['project', 'project_site'],
        self::SERVICES->value => ['service', 'servicerecord'],
        self::DATA->value => [
            'milk_production', 'milk_sale', 'milk_cost', 'milk_usage', 'milk_wastage',
            'milk_traded', 'milk_consolidation', 'meat_production', 'meat_sales',
            'manure_production', 'manure_sales', 'dairy_box_sale',
            'monthly_task',

            'pl480', 'epahp_gatasang_kalabaw', 'epahp_capacity_building',
            'milk_utilization_monthly_summary',
        ],
        self::USERS->value => [User::class, 'user'],
        self::REPORTS->value => ['milk_feeding_program'],
        self::SYSTEM->value => [],
    ];

    private const REPORT_KEYWORDS = ['export', 'report'];

    public static function resolve(?string $subjectType, ?string $event = null, ?string $description = null): self
    {
        $wording = str($event ?? ' '.$description)->lower()->toString();

        foreach (self::REPORT_KEYWORDS as $keyword) {
            if (str($wording)->contains($keyword)) {
                return self::REPORTS;
            }
        }

        foreach (self::SUBJECT_TYPES as $category => $types) {
            if ($subjectType !== null && in_array($subjectType, $types, true)) {
                return self::from($category);
            }
        }

        return self::SYSTEM;
    }

    /**
     * The subject morph types a category matches, as an `whereIn` ready list.
     * SYSTEM deliberately matches nothing here - it is expressed as the absence
     * of every other category instead, so newly morphed models keep landing in
     * it without needing a code change.
     *
     * @return list<string>
     */
    public static function subjectTypes(self $category): array
    {
        return self::SUBJECT_TYPES[$category->value] ?? [];
    }

    /**
     * Categories that can be filtered on, in the order the filter panel lists
     * them.
     *
     * @return list<string>
     */
    public static function filterable(): array
    {
        return [
            self::DOCUMENTS->value,
            self::PROFILES->value,
            self::GOALS->value,
            self::PRODUCTS->value,
            self::PROJECTS->value,
            self::SERVICES->value,
            self::DATA->value,
            self::USERS->value,
            self::REPORTS->value,
            self::SYSTEM->value,
        ];
    }

    public static function labels(): array
    {
        return [
            self::DOCUMENTS->value => 'Documents',
            self::PROFILES->value => 'Profiles',
            self::GOALS->value => 'Goals',
            self::PRODUCTS->value => 'Products',
            self::PROJECTS->value => 'Projects',
            self::SERVICES->value => 'Services',
            self::DATA->value => 'Production & Sales Data',
            self::USERS->value => 'Users',
            self::REPORTS->value => 'Reports',
            self::SYSTEM->value => 'System',
        ];
    }
}
