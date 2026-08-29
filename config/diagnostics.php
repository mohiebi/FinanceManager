<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client Diagnostics
    |--------------------------------------------------------------------------
    |
    | Temporary instrumentation for the post-OAuth SSR/hydration investigation.
    |
    | Hydration happens entirely in the browser, so no ordinary server log can
    | see it. When this is on, the client ships hydration mismatches, uncaught
    | errors and a one-line boot report to the server, which writes them to the
    | normal log channel — stderr in the container, so they land in
    | `docker logs` beside the `[ssr]` lines.
    |
    | Turn this off (CLIENT_DIAGNOSTICS_ENABLED=false) once the cause is found.
    |
    */

    'client_enabled' => (bool) env('CLIENT_DIAGNOSTICS_ENABLED', true),

    /*
    | Server-side counterpart: log one line for every full-document (non-Inertia)
    | render, recording whether SSR markup actually made it into the HTML. This
    | is the request the OAuth callback redirects into.
    */

    'log_document_renders' => (bool) env('DIAGNOSTICS_LOG_DOCUMENT_RENDERS', true),

];
