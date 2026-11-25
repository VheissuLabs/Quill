<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\QuillTool;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

#[Name('Quill')]
#[Version('1.0.0')]
#[Instructions('Quill runs projects, issues (tasks), sprints, boards, feedback, support tickets, clients and invoices for organizations. Every tool acts as the signed-in user with their permissions. Tools take an optional organization slug and default to the user\'s current organization. Issues are what the user calls tasks. Time is tracked by logging hours on an issue with log-time, which adds them to the client\'s open draft invoice.')]
class QuillServer extends Server
{
    protected function boot(): void
    {
        $this->tools = collect(Finder::create()->files()->in(app_path('Mcp/Tools'))->name('*Tool.php'))
            ->map(fn ($file) => 'App\\Mcp\\Tools\\'.Str::of($file->getRelativePathname())->beforeLast('.php')->replace('/', '\\'))
            ->filter(fn (string $class) => is_subclass_of($class, QuillTool::class) && ! (new ReflectionClass($class))->isAbstract())
            ->sort()
            ->values()
            ->all();
    }
}
