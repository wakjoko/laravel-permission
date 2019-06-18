<?php

namespace Spatie\Permission\Traits;

use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Group;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasGroups
{
    use HasRoles;

    private $groupClass;

    public static function bootHasGroups()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->groups()->detach();
        });
    }

    public function getGroupClass()
    {
        if (! isset($this->groupClass)) {
            $this->groupClass = app(PermissionRegistrar::class)->getGroupClass();
        }

        return $this->groupClass;
    }

    /**
     * A model may have multiple groups.
     */
    public function groups(): MorphToMany
    {
        return $this->morphToMany(
            config('permission.models.group'),
            'model',
            config('permission.table_names.model_has_groups'),
            config('permission.column_names.model_morph_key'),
            'group_id'
        );
    }

    /**
     * Scope the model query to certain groups only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array|\Spatie\Permission\Contracts\Group|\Illuminate\Support\Collection $groups
     * @param string $guard
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeGroup(Builder $query, $groups, $guard = null): Builder
    {
        if ($groups instanceof Collection) {
            $groups = $groups->all();
        }

        if (! is_array($groups)) {
            $groups = [$groups];
        }

        $groups = array_map(function ($group) use ($guard) {
            if ($group instanceof Group) {
                return $group;
            }

            $method = is_numeric($group) ? 'findById' : 'findByName';
            $guard = $guard ?: $this->getDefaultGuardName();

            return $this->getGroupClass()->{$method}($group, $guard);
        }, $groups);

        return $query->whereHas('groups', function ($query) use ($groups) {
            $query->where(function ($query) use ($groups) {
                foreach ($groups as $group) {
                    $query->orWhere(config('permission.table_names.groups').'.id', $group->id);
                }
            });
        });
    }

    /**
     * Assign the given group to the model.
     *
     * @param array|string|\Spatie\Permission\Contracts\Group ...$groups
     *
     * @return $this
     */
    public function assignGroup(...$groups)
    {
        $groups = collect($groups)
            ->flatten()
            ->map(function ($group) {
                if (empty($group)) {
                    return false;
                }

                return $this->getStoredGroup($group);
            })
            ->filter(function ($group) {
                return $group instanceof Group;
            })
            ->each(function ($group) {
                $this->ensureModelSharesGuard($group);
            })
            ->map->id
            ->all();

        $model = $this->getModel();

        if ($model->exists) {
            $this->groups()->sync($groups, false);
            $model->load('groups');
        } else {
            $class = \get_class($model);

            $class::saved(
                function ($object) use ($groups, $model) {
                    static $modelLastFiredOn;
                    if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                        return;
                    }
                    $object->groups()->sync($groups, false);
                    $object->load('groups');
                    $modelLastFiredOn = $object;
                });
        }

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Revoke the given group from the model.
     *
     * @param string|\Spatie\Permission\Contracts\Group $group
     */
    public function removeGroup($group)
    {
        $this->groups()->detach($this->getStoredGroup($group));

        $this->load('groups');

        return $this;
    }

    /**
     * Remove all current groups and set the given ones.
     *
     * @param array|\Spatie\Permission\Contracts\Group|string ...$groups
     *
     * @return $this
     */
    public function syncGroups(...$groups)
    {
        $this->groups()->detach();

        return $this->assignGroup($groups);
    }

    /**
     * Determine if the model has (one of) the given group(s).
     *
     * @param string|int|array|\Spatie\Permission\Contracts\Group|\Illuminate\Support\Collection $groups
     *
     * @return bool
     */
    public function hasGroup($groups): bool
    {
        if (is_string($groups) && false !== strpos($groups, '|')) {
            $groups = $this->convertPipeToArray($groups);
        }

        if (is_string($groups)) {
            return $this->groups->contains('name', $groups);
        }

        if (is_int($groups)) {
            return $this->groups->contains('id', $groups);
        }

        if ($groups instanceof Group) {
            return $this->groups->contains('id', $groups->id);
        }

        if (is_array($groups)) {
            foreach ($groups as $group) {
                if ($this->hasGroup($group)) {
                    return true;
                }
            }

            return false;
        }

        return $groups->intersect($this->groups)->isNotEmpty();
    }

    /**
     * Determine if the model has any of the given group(s).
     *
     * @param string|array|\Spatie\Permission\Contracts\Group|\Illuminate\Support\Collection $groups
     *
     * @return bool
     */
    public function hasAnyGroup($groups): bool
    {
        return $this->hasGroup($groups);
    }

    /**
     * Determine if the model has all of the given group(s).
     *
     * @param string|\Spatie\Permission\Contracts\Group|\Illuminate\Support\Collection $groups
     *
     * @return bool
     */
    public function hasAllGroups($groups): bool
    {
        if (is_string($groups) && false !== strpos($groups, '|')) {
            $groups = $this->convertPipeToArray($groups);
        }

        if (is_string($groups)) {
            return $this->groups->contains('name', $groups);
        }

        if ($groups instanceof Group) {
            return $this->groups->contains('id', $groups->id);
        }

        $groups = collect()->make($groups)->map(function ($group) {
            return $group instanceof Group ? $group->name : $group;
        });

        return $groups->intersect($this->getGroupNames()) == $groups;
    }

    /**
     * Return all roles directly coupled to the model.
     */
    public function getDirectRoles(): Collection
    {
        return $this->roles;
    }

    public function getGroupNames(): Collection
    {
        return $this->groups->pluck('name');
    }

    protected function getStoredGroup($group): Group
    {
        $groupClass = $this->getGroupClass();

        if (is_numeric($group)) {
            return $groupClass->findById($group, $this->getDefaultGuardName());
        }

        if (is_string($group)) {
            return $groupClass->findByName($group, $this->getDefaultGuardName());
        }

        return $group;
    }

    protected function convertPipeToArray(string $pipeString)
    {
        $pipeString = trim($pipeString);

        if (strlen($pipeString) <= 2) {
            return $pipeString;
        }

        $quoteCharacter = substr($pipeString, 0, 1);
        $endCharacter = substr($quoteCharacter, -1, 1);

        if ($quoteCharacter !== $endCharacter) {
            return explode('|', $pipeString);
        }

        if (! in_array($quoteCharacter, ["'", '"'])) {
            return explode('|', $pipeString);
        }

        return explode('|', trim($pipeString, $quoteCharacter));
    }
}
