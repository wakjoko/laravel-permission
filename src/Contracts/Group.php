<?php

namespace Spatie\Permission\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface Group
{
    /**
     * A group may be given various roles.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles(): BelongsToMany;

    /**
     * Find a group by its name and guard name.
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @return \Spatie\Permission\Contracts\Group
     *
     * @throws \Spatie\Permission\Exceptions\GroupDoesNotExist
     */
    public static function findByName(string $name, $guardName): self;

    /**
     * Find a group by its id and guard name.
     *
     * @param int $id
     * @param string|null $guardName
     *
     * @return \Spatie\Permission\Contracts\Group
     *
     * @throws \Spatie\Permission\Exceptions\GroupDoesNotExist
     */
    public static function findById(int $id, $guardName): self;

    /**
     * Find or create a group by its name and guard name.
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @return \Spatie\Permission\Contracts\Group
     */
    public static function findOrCreate(string $name, $guardName): self;

    /**
     * Determine if the user may perform the given role.
     *
     * @param string|\Spatie\Permission\Contracts\Role $role
     *
     * @return bool
     */
    public function hasRoleTo($role): bool;
}
