<?php

namespace App\Models;

use Database\Factories\BuyingSignalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'evidence', 'source_url', 'strength'])]
class BuyingSignal extends Model
{
    /** @use HasFactory<BuyingSignalFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Prospect, $this>
     */
    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
