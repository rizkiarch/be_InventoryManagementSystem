<?php

namespace App\Policies;

use App\Models\User;

class BasePolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    protected function hasPermission(User $user, string $action)
    {
        return $user->can("*.$action");
    }

    public function create(User $user)
    {
        return $this->hasPermission($user, 'create');
    }

    public function read(User $user)
    {
        return $this->hasPermission($user, 'read');
    }

    public function update(User $user)
    {
        return $this->hasPermission($user, 'update');
    }

    public function delete(User $user)
    {
        return $this->hasPermission($user, 'delete');
    }

    public function import(User $user)
    {
        return $this->hasPermission($user, 'import');
    }

    public function export(User $user)
    {
        return $this->hasPermission($user, 'export');
    }

    public function print(User $user)
    {
        return $this->hasPermission($user, 'print');
    }

    public function upload(User $user)
    {
        return $this->hasPermission($user, 'upload');
    }

    public function download(User $user)
    {
        return $this->hasPermission($user, 'download');
    }
}
