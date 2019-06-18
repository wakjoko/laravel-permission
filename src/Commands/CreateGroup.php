<?php

namespace Spatie\Permission\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Contracts\Group as GroupContract;
use Spatie\Permission\Contracts\Role as RoleContract;

class CreateGroup extends Command
{
    protected $signature = 'permission:create-group
        {name : The name of the group}
        {guard? : The name of the guard}
        {roles? : A list of roles to assign to the group, separated by | }';

    protected $description = 'Create a group';

    public function handle()
    {
        $groupClass = app(GroupContract::class);

        $group = $groupClass::findOrCreate($this->argument('name'), $this->argument('guard'));

        $group->giveRoleTo($this->makeRoles($this->argument('roles')));

        $this->info("Group `{$group->name}` created");
    }

    protected function makeRoles($string = null)
    {
        if (empty($string)) {
            return;
        }

        $roleClass = app(RoleContract::class);

        $roles = explode('|', $string);

        $models = [];

        foreach ($roles as $role) {
            $models[] = $roleClass::findOrCreate(trim($role), $this->argument('guard'));
        }

        return collect($models);
    }
}
