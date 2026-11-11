<?php

namespace App\Actions\Document;

use App\Enums\ComplianceStatusEnum;
use App\Enums\DocumentEnum;
use App\Enums\DocumentKindEnum;
use App\Enums\ProgramEnum;
use App\Models\Center;
use App\Models\DairyBox;
use App\Models\Document;
use App\Models\Farmer;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Describes an uploaded document the way a person reads it, not the way it is stored.
 *
 * An upload's facts are split across three places: the document row, its tags,
 * and the record a tag points at. None of them is wrong on its own - `key` is
 * `document`, `value` is `fda-lto`, and the tag's owner is a center code - so
 * read raw they say nothing about the file in front of you. Resolved here once,
 * both the audit trail and the Documents page speak about an upload the same way.
 */
class DescribeDocumentAction
{
    /**
     * Tag keys, named the way the upload form names them.
     *
     * @var array<string, string>
     */
    private const TAG_LABELS = [
        'template' => 'Template',
        'document' => 'Document Type',
        'center' => 'Center',
        'partner' => 'Partner',
        'farmer' => 'Farmer',
        'dairy_box' => 'Dairy Box',
        'entity_type' => 'Entity Type',
        'program' => 'Program',
        'report_type' => 'Report Type',
    ];

    /**
     * Tag keys that say what an upload is, in the order the workflows prefer them.
     *
     * A bulk import names its template, a compliance file names its document
     * kind, and a generated file names its report - three different families,
     * each complete on its own. An upload is only unidentified when it carries
     * none of them.
     *
     * @var array<int, string>
     */
    private const TYPE_TAG_KEYS = ['template', 'document', 'report_type'];

    /**
     * Tag keys that name what a document is attached to, in the order the upload
     * form prefers them.
     *
     * @var array<int, string>
     */
    private const OWNER_TAG_KEYS = ['center', 'partner', 'farmer', 'dairy_box'];

    /**
     * @return array{
     *     details: list<array{label: string, value: string}>,
     *     tags: list<array{key: string, label: string, value: string, label_value: string}>,
     *     missing_tags: list<string>
     * }
     */
    public function execute(Document $document): array
    {
        return [
            'details' => $this->details($document),
            'tags' => $this->tags($document),
            'missing_tags' => $this->missingTags($document),
        ];
    }

    /**
     * The document's own facts, as labelled pairs.
     *
     * A document upload logs one thing: the status it started in. Everything a
     * reviewer actually wants - which kind of document, what it is attached to,
     * its registration number and validity - lives on the document and on its
     * tags, so without this the file reads as "Draft" and nothing more.
     *
     * @return list<array{label: string, value: string}>
     */
    public function details(Document $document): array
    {
        $details = [
            'Document Type' => $this->documentType($document),
            'Attached To' => $this->documentOwner($document),
            'Registration Number' => $document->registration_number,
            'Validity' => $document->validity?->toDateString(),
            'Compliance Status' => $document->compliance_status instanceof ComplianceStatusEnum
                ? ComplianceStatusEnum::labels()[$document->compliance_status->value] ?? $document->compliance_status->value
                : null,
            'File' => $this->documentFile($document),
        ];

        return array_values(array_filter(array_map(
            fn (string $label, mixed $value) => $value === null || $value === ''
                ? null
                : ['label' => $label, 'value' => (string) $value],
            array_keys($details),
            $details,
        )));
    }

    /**
     * Every tag on the document, named and resolved, so a reviewer can check the
     * upload was tagged correctly without opening the edit form.
     *
     * @return list<array{key: string, label: string, value: string, label_value: string}>
     */
    public function tags(Document $document): array
    {
        $generated = $this->isGenerated($document);

        return $document->tags
            // `document=processing` is a marker the export workflows write so a
            // generated file can be recognised; it is not a type a person chose,
            // so it is left out rather than shown as "Document Type: Processing".
            ->reject(fn ($tag) => $generated && $tag->key === 'document' && $tag->value === 'processing')
            ->map(fn ($tag) => [
                'key' => $tag->key,
                'label' => self::TAG_LABELS[$tag->key] ?? Str::headline($tag->key),
                'value' => (string) $tag->value,
                'label_value' => $this->tagValueLabel($tag->key, (string) $tag->value),
            ])
            ->values()
            ->all();
    }

    /**
     * The tags an upload needs in order to say what it is, named for the warning.
     *
     * All three families are optional at upload time - the file is accepted
     * either way - so a document missing them is not an error, it is just a
     * document nobody can place. Surfaced rather than enforced.
     *
     * @return list<string>
     */
    public function missingTags(Document $document): array
    {
        $present = $document->tags->pluck('key')->all();
        $missing = [];

        if (array_intersect(self::TYPE_TAG_KEYS, $present) === []) {
            $missing[] = 'Type (template, document or report)';
        }

        if (array_intersect(self::OWNER_TAG_KEYS, $present) === []) {
            $missing[] = 'Attachment';
        }

        return $missing;
    }

    /**
     * The kind of document, from the tag that names it.
     *
     * A generated file answers none of this: it is not a kind of document, it is a
     * report a workflow produced, which the `report_type` tag already names. So
     * the fact is dropped rather than filled in with the marker tag the export
     * workflows write.
     */
    private function documentType(Document $document): ?string
    {
        if ($this->isGenerated($document)) {
            return null;
        }

        $type = $document->tags->firstWhere('key', 'document')?->value;

        if (blank($type)) {
            return null;
        }

        return DocumentEnum::labels()[$type] ?? Str::headline($type);
    }

    /**
     * Whether a report workflow generated this file, per the tag the Documents
     * list itself splits on (DocumentKindEnum::EXPORT_TAG_KEY).
     */
    private function isGenerated(Document $document): bool
    {
        return $document->tags->contains(
            fn ($tag) => $tag->key === DocumentKindEnum::EXPORT_TAG_KEY
        );
    }

    /**
     * What the document is attached to, named the way the attachment names itself.
     */
    private function documentOwner(Document $document): ?string
    {
        foreach (self::OWNER_TAG_KEYS as $key) {
            $value = $document->tags->firstWhere('key', $key)?->value;

            if (blank($value)) {
                continue;
            }

            $owner = match ($key) {
                'center' => Center::query()->where('code', $value)->orWhere('id', $value)->first(),
                'partner' => Partner::query()->where('code', $value)->orWhere('id', $value)->first(),
                'farmer' => Farmer::query()->find((int) $value),
                'dairy_box' => DairyBox::query()->find((int) $value),
            };

            if ($owner instanceof Model) {
                return $this->ownerLabel($owner);
            }
        }

        return null;
    }

    /**
     * The stored file, so a reviewer can tell a re-upload from the original.
     */
    private function documentFile(Document $document): ?string
    {
        $file = $document->file;

        if ($file === null) {
            return null;
        }

        $size = $file->size ? ' · '.number_format((int) $file->size / 1024).' KB' : '';

        return trim(($file->name ?? $document->title).$size);
    }

    /**
     * A tag value as a person reads it, for the tag keys whose values are known.
     */
    private function tagValueLabel(string $key, string $value): string
    {
        if ($key === 'document') {
            return DocumentEnum::labels()[$value] ?? Str::headline($value);
        }

        if ($key === 'program') {
            return ProgramEnum::labels()[$value] ?? Str::headline($value);
        }

        if (in_array($key, self::OWNER_TAG_KEYS, true)) {
            return $this->owner($key, $value)?->{$this->ownerNameColumn($key)} ?? $value;
        }

        return Str::headline($value);
    }

    /**
     * A tag that points at a record, looked up the same way for both the tag
     * list and the "Attached To" detail.
     */
    private function owner(string $key, string $value): ?Model
    {
        return match ($key) {
            'center' => Center::query()->where('code', $value)->orWhere('id', $value)->first(),
            'partner' => Partner::query()->where('code', $value)->orWhere('id', $value)->first(),
            'farmer' => Farmer::query()->find((int) $value),
            'dairy_box' => DairyBox::query()->find((int) $value),
            default => null,
        };
    }

    /**
     * The attribute a record is named by - a farmer keeps their name in two
     * columns and a dairy box is named by the business it runs, so neither has
     * a `name` to read.
     */
    private function ownerNameColumn(string $key): string
    {
        return match (true) {
            $key === 'farmer' => 'first_name',
            $key === 'dairy_box' => 'business_name',
            default => 'name',
        };
    }

    private function ownerLabel(Model $owner): ?string
    {
        $key = match (true) {
            $owner instanceof Center => 'center',
            $owner instanceof Partner => 'partner',
            $owner instanceof Farmer => 'farmer',
            $owner instanceof DairyBox => 'dairy_box',
            default => null,
        };

        if ($key === null) {
            return null;
        }

        $label = trim($owner->{$this->ownerNameColumn($key)}.(($key === 'farmer') ? ' '.$owner->last_name : ''));

        return $label !== '' ? $label : null;
    }
}
