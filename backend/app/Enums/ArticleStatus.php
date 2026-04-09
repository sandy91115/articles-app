<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';
}
