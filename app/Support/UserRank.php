<?php

namespace App\Support;

class UserRank
{
    public static function fromCount(int $count): string
    {
        return match (true) {
            $count >= 1000 => 'VAULT LEGEND',
            $count >= 500  => 'VAULT SOVEREIGN',
            $count >= 250  => 'VAULT MASTER',
            $count >= 100  => 'VAULT KEEPER',
            $count >= 50   => 'COLLECTOR',
            $count >= 20   => 'CURATOR',
            $count >= 5    => 'ARCHIVIST',
            default        => 'INITIATE',
        };
    }
}
