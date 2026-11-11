<?php

namespace App\Actions\Tag;

use App\Events\EntityTagged;
use App\Models\Tag;

class UpsertTagAction
{
    public function execute(
        string $key,
        string $value,
        ?string $taggableType,
        ?string $taggableId
    ) {
        $tag = Tag::updateOrCreate(
            [
                'key' => $key,
                'value' => $value,
                'taggable_type' => $taggableType,
                'taggable_id' => $taggableId,
            ],
            []
        );

        if ($tag->wasRecentlyCreated) {
            event(new EntityTagged(
                tagKey: $key,
                tagValue: $value,
                taggableType: $taggableType,
                taggableId: $taggableId
            ));
        }

        return $tag;
    }
}
