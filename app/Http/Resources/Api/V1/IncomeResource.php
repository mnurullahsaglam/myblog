<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\Ability;
use App\Models\Income;
use App\Support\Access\AccessProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin Income
 */
final class IncomeResource extends JsonResource
{
    /**
     * client_id is gated on the same ability the panel's column is, because this
     * is a serialisation path that the table and form filtering does not reach.
     *
     * source needs no guard: the model's accessor already degrades it, which is
     * why the check was put there rather than in the table.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $profile = resolve(AccessProfile::class);

        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency->value,
            'date' => $this->date->toDateString(),
            'description' => $this->description,
            'source' => $this->source,
            'income_category_id' => $this->income_category_id,
            'invoice_id' => $this->invoice_id,
            'debt_id' => $this->debt_id,
            'client_id' => $this->when($profile->allows(Ability::SeeClientIdentity), fn (): mixed => $this->client_id),
            'editable' => $this->client_id === null || $profile->allows(Ability::SeeClientIdentity),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
