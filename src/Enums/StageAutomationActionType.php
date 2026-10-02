<?php

declare(strict_types=1);

namespace Odden\Sales\Enums;

enum StageAutomationActionType: string
{
    case CreateTask = 'create_task';
    case RequireAssociatedContact = 'require_associated_contact';
    case RequireDealProducts = 'require_deal_products';
    case RequireActiveQuote = 'require_active_quote';
    case RequireAcceptedQuote = 'require_accepted_quote';
    case NotifyOwner = 'notify_owner';

    public function label(): string
    {
        return match ($this) {
            self::CreateTask => 'Create Follow-up Task',
            self::RequireAssociatedContact => 'Require Associated Contact',
            self::RequireDealProducts => 'Require Line Items / Products',
            self::RequireActiveQuote => 'Require Active / Sent Quote',
            self::RequireAcceptedQuote => 'Require Accepted & Signed Quote',
            self::NotifyOwner => 'Notify Deal Owner',
        };
    }
}
