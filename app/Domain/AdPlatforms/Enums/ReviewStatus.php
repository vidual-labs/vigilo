<?php

namespace App\Domain\AdPlatforms\Enums;

/** Platform-neutral policy review state of an ad. */
enum ReviewStatus: string
{
    case Approved = 'approved';
    case Limited = 'limited';
    case Disapproved = 'disapproved';
    case UnderReview = 'under_review';
    case Unknown = 'unknown';
}
