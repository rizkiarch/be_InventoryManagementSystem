<?php

namespace App\Policies;

use App\Models\User;

class ProductPolicy extends BasePolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
        return $this->hasPermission($user, 'read');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Product $product)
    {
        return $this->hasPermission($user, 'read');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Product $product)
    {
        return $this->hasPermission($user, 'update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Product $product)
    {
        return $this->hasPermission($user, 'delete');
    }
}
