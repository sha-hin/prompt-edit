<?php

namespace App\Policies;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class GenerationPolicy
{
    /**
     * Owners see their own generations and operators see all of them. Anyone else gets a 404,
     * so a generation id never reveals that it exists.
     */
    public function view(User $user, Generation $generation): Response
    {
        return $generation->user_id === $user->id || $user->is_admin
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
