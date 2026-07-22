<?php

use App\Http\Middleware\EnsureMcpUserIsReady;
use App\Mcp\Servers\FinanceServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp/finance', FinanceServer::class)
    ->middleware(['auth:api', EnsureMcpUserIsReady::class, 'throttle:mcp']);
