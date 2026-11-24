<?php

use App\Mcp\Servers\QuillServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp', QuillServer::class)->middleware('auth:api');
