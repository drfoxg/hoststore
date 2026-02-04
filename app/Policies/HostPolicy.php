<?php

namespace App\Policies;

use App\Models\Host;
use App\Models\User;

class HostPolicy
{
    /**
     * Просмотр списка хостов — все авторизованные
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Просмотр одного хоста — все авторизованные
     */
    public function view(User $user, Host $host): bool
    {
        return true;
    }

    /**
     * Создание хоста — все авторизованные
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Переименование хоста — только admin
     */
    public function rename(User $user, Host $host): bool
    {
        return $user->isAdmin();
    }

    /**
     * Удаление хоста — только admin
     */
    public function delete(User $user, Host $host): bool
    {
        return $user->isAdmin();
    }
}
