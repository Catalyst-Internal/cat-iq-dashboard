<?php

namespace App\Enums;

enum CiState: string
{
    case Passing = 'passing';
    case Failing = 'failing';
    case Pending = 'pending';
    case None = 'none';
}
