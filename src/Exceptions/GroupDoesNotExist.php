<?php

namespace Spatie\Permission\Exceptions;

use InvalidArgumentException;

class GroupDoesNotExist extends InvalidArgumentException
{
    public static function named(string $groupName)
    {
        return new static("There is no group named `{$groupName}`.");
    }

    public static function withId(int $groupId)
    {
        return new static("There is no group with id `{$groupId}`.");
    }
}
