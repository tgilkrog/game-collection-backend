<?php

namespace App\Support;

class UserRank
{
    public static function fromCount(int $count): string
    {
        return match (true) {
            $count >= 100 => 'VAULT MASTER',
            $count >= 50  => 'COLLECTOR',
            $count >= 20  => 'CURATOR',
            $count >= 5   => 'ARCHIVIST',
            default       => 'INITIATE',
        };
    }
}
