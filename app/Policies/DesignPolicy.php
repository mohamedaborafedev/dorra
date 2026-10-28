<?php

namespace App\Policies;

use App\Enums\DesignStatus;
use App\Enums\UserRole;
use App\Models\Design;
use App\Models\User;

class DesignPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Viewer], true);
    }

    public function view(User $user, Design $design): bool
    {
        return $this->viewAny($user) && ($user->role === UserRole::Admin || $design->status === DesignStatus::Published);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Design $design): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Design $design): bool
    {
        return $this->create($user);
    }
}
