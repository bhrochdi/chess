<?php

declare(strict_types=1);

namespace LeCercle\Admin;

/** Chiffres du tableau de bord. */
interface DashboardCounts
{
    /** @return array{accounts: int, online: int, open: int, playing: int, finished: int, roomMessages: int} */
    public function counts(\DateTimeImmutable $onlineSince): array;
}
