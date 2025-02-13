<?php

if (!function_exists('abort_if_user_cannot')) {

    function abort_if_user_cannot($abilities, $arguments = [])
    {
        $user = user();

        if (!$user) {
            throw new \App\Exceptions\LoginFailedException(
                401,
                "Silahkan login terlebih dahulu"
            );
        }

        if (!$user->can($abilities, $arguments)) {
            throw new \App\Exceptions\AccessDeniedException(
                403,
                "Anda tidak memiliki akses, silahkan hubungi administrator"
            );
        }
    }
}
