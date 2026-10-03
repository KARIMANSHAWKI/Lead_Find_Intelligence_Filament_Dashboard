<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasOne<IcpConfiguration, $this> */
    public function icpConfiguration(): HasOne
    {
        return $this->hasOne(IcpConfiguration::class);
    }

    /** @return HasMany<AgentRun, $this> */
    public function agentRuns(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }

    /** @return HasMany<Prospect, $this> */
    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }
}
