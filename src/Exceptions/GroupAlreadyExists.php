<?php

namespace Spatie\Permission\Exceptions;

use InvalidArgumentException;

class GroupAlreadyExists extends InvalidArgumentException
{
    public static function create(string $groupName, string $guardName)
    {
        return new static("A group `{$groupName}` already exists for guard `{$guardName}`.");
    }
}
