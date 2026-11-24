<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Billing\ListInvoicesTool;
use App\Mcp\Tools\Billing\LogTimeTool;
use App\Mcp\Tools\Billing\ShowInvoiceTool;
use App\Mcp\Tools\Issues\CreateIssueTool;
use App\Mcp\Tools\Issues\ListIssuesTool;
use App\Mcp\Tools\Issues\UpdateIssueTool;
use App\Mcp\Tools\Organizations\ListOrganizationsTool;
use App\Mcp\Tools\Projects\ListProjectsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Quill')]
#[Version('1.0.0')]
#[Instructions('Quill runs projects, issues (tasks), sprints, feedback, support tickets, clients and invoices for organizations. Every tool acts as the signed-in user with their permissions. Tools take an optional organization slug and default to the user\'s current organization. Issues are what the user calls tasks. Time is tracked by logging hours on an issue with log-time, which adds them to the client\'s open draft invoice.')]
class QuillServer extends Server
{
    protected array $tools = [
        ListOrganizationsTool::class,
        ListProjectsTool::class,
        ListIssuesTool::class,
        CreateIssueTool::class,
        UpdateIssueTool::class,
        LogTimeTool::class,
        ListInvoicesTool::class,
        ShowInvoiceTool::class,
    ];
}
