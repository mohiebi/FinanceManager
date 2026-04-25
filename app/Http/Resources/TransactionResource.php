<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'amount' => $this->amount,
            'currency' => $this->currency->value,
            'title' => $this->title,
            'description' => $this->description,
            'occurred_at' => $this->occurred_at->toDateString(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'category_id' => $this->category_id,
        ];
    }
}
