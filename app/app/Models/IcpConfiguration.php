<?php

namespace App\Models;

use Database\Factories\IcpConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product', 'target_industries', 'location', 'company_size_min', 'company_size_max', 'ideal_customer_description'])]
class IcpConfiguration extends Model
{
    /** @use HasFactory<IcpConfigurationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_industries' => 'array',
            'company_size_min' => 'integer',
            'company_size_max' => 'integer',
        ];
    }
}
