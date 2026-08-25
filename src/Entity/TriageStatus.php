<?php

namespace App\Entity;

enum TriageStatus: string
{
    case New = 'new';
    case Addressing = 'addressing';
    case Dismissed = 'dismissed';
}
