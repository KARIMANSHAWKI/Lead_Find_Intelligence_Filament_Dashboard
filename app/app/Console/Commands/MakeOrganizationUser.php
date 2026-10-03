<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Filament\Commands\MakeUserCommand;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class MakeOrganizationUser extends MakeUserCommand
{
    /** @return array<InputOption> */
    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            new InputOption('platform-admin', null, InputOption::VALUE_NONE, 'Grant platform administrator access to the landlord panel'),
            new InputOption('organization', null, InputOption::VALUE_REQUIRED, 'An existing organization ID; omit to create an organization for the new user'),
        ];
    }

    protected function createUser(): Model&Authenticatable
    {
        $data = $this->getUserData();

        return DB::transaction(function () use ($data): Model&Authenticatable {
            $organization = filled($this->option('organization'))
                ? Organization::query()->findOrFail($this->option('organization'))
                : Organization::create(['name' => Str::limit($data['name']."'s Organization", 255, '')]);

            $user = $organization->users()->create($data);
            if ($this->option('platform-admin')) {
                $user->forceFill(['is_platform_admin' => true])->save();
            }

            return $user;
        });
    }
}
