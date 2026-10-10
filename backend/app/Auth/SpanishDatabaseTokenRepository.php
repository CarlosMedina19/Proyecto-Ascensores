<?php

namespace App\Auth;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Carbon;

class SpanishDatabaseTokenRepository extends DatabaseTokenRepository implements TokenRepositoryInterface
{
    public function exists(CanResetPassword $user, #[\SensitiveParameter] $token)
    {
        $record = $this->findTokenRecord($user);

        return $record
            && ! $this->tokenExpired($record['created_at'])
            && $this->hasher->check($token, $record['token']);
    }

    public function recentlyCreatedToken(CanResetPassword $user)
    {
        $record = $this->findTokenRecord($user);

        return $record && $this->tokenRecentlyCreated($record['created_at']);
    }

    private function findTokenRecord(CanResetPassword $user): array
    {
        $record = $this->getTable()
            ->selectRaw('"creado_en" AS created_at, "token" AS token')
            ->whereRaw('"correo_electronico" = ?', [$user->getEmailForPasswordReset()])
            ->first();

        return $record === null ? [] : (array) $record;
    }
}
