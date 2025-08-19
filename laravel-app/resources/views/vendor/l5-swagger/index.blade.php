<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ config('l5-swagger.documentations.'.$documentation.'.api.title') }}</title>
    <link rel="stylesheet" type="text/css" href="{{ l5_swagger_asset($documentation, 'swagger-ui.css') }}">
    <link rel="icon" type="image/png" href="{{ l5_swagger_asset($documentation, 'favicon-32x32.png') }}" sizes="32x32"/>
    <link rel="icon" type="image/png" href="{{ l5_swagger_asset($documentation, 'favicon-16x16.png') }}" sizes="16x16"/>
    <style>
    html {
        box-sizing: border-box;
        overflow: -moz-scrollbars-vertical;
        overflow-y: scroll;
    }
    *, *:before, *:after {
        box-sizing: inherit;
    }
    body {
        margin:0;
        background: #fafafa;
    }
    </style>
</head>

<body>
<div id="swagger-ui"></div>

<script src="{{ l5_swagger_asset($documentation, 'swagger-ui-bundle.js') }}"></script>
<script src="{{ l5_swagger_asset($documentation, 'swagger-ui-standalone-preset.js') }}"></script>
<script>
window.onload = function() {
    // Build a system
    const ui = SwaggerUIBundle({
        url: "/api-docs.json",
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIStandalonePreset
        ],
        plugins: [
            SwaggerUIBundle.plugins.DownloadUrl
        ],
        layout: "StandaloneLayout",
        docExpansion: "{{ config('l5-swagger.defaults.ui.display.doc_expansion', 'none') }}",
        filter: {{ config('l5-swagger.defaults.ui.display.filter') ? 'true' : 'false' }},
        validatorUrl: null,
        persistAuthorization: {{ config('l5-swagger.defaults.ui.authorization.persist_authorization') ? 'true' : 'false' }},
        operationsSorter: "{{ config('l5-swagger.defaults.operations_sort', 'alpha') }}",
    })

    window.ui = ui
}
</script>
</body>
</html>
