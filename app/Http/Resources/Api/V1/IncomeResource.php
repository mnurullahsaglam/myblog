<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\Ability;
use App\Models\Income;
use App\Support\Access\AccessProfile;
use Illuminate\Http\Request;
use Override;

/**
 * @mixin Income
 */
final class IncomeResource extends ApiResource
{
    /**
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
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
