<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'journal_number' => $this->journal_number,
            'journal_date' => $this->journal_date,
            'type' => $this->type,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'description' => $this->description,
            'is_posted' => $this->is_posted,
            'lines' => $this->whenLoaded('lines'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
