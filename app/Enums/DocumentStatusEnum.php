<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum DocumentStatusEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = self::DRAFT;

    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case PENDING_CENTER_REVIEW = 'pending_center_review';
    case PENDING_AGENCY_REVIEW = 'pending_agency_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REJECTED_BY_CENTER = 'rejected_by_center';
    case REJECTED_BY_AGENCY = 'rejected_by_agency';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case FAILED = 'failed';

    /**
     * Statuses a document sits in while it waits for a review decision — by the
     * center's own reviewer or by a PCC administrator. A document in one of
     * these must never be handed to an import workflow.
     *
     * @return array<int, string>
     */
    public static function awaitingReview(): array
    {
        return [
            self::DRAFT->value,
            self::PENDING_REVIEW->value,
            self::PENDING_CENTER_REVIEW->value,
            self::PENDING_AGENCY_REVIEW->value,
            self::REJECTED->value,
            self::REJECTED_BY_CENTER->value,
            self::REJECTED_BY_AGENCY->value,
        ];
    }

    public static function labels(): array
    {
        return [
            self::DRAFT->value => 'Draft',
            self::PENDING_REVIEW->value => 'Pending Review',
            self::PENDING_CENTER_REVIEW->value => 'Pending Center Review',
            self::PENDING_AGENCY_REVIEW->value => 'Pending Agency Review',
            self::APPROVED->value => 'Approved',
            self::REJECTED->value => 'Rejected',
            self::REJECTED_BY_CENTER->value => 'Rejected by Center',
            self::REJECTED_BY_AGENCY->value => 'Rejected by Agency',
            self::PROCESSING->value => 'Processing',
            self::PROCESSED->value => 'Processed',
            self::FAILED->value => 'Failed',
        ];
    }
}
