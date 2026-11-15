<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Preview paused</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f9fafb; color: #111827; font: 15px/1.5 ui-sans-serif, system-ui, sans-serif; padding: 24px; box-sizing: border-box; }
        div { max-width: 420px; text-align: center; }
        h1 { font-size: 17px; margin: 0 0 6px; }
        ul { text-align: left; color: #b91c1c; padding-left: 20px; }
    </style>
</head>
<body>
<div>
    <h1>Preview paused</h1>
    <p>Fix this and the preview comes back:</p>
    <ul>@foreach ($messages as $message)<li>{{ $message }}</li>@endforeach</ul>
</div>
</body>
</html>
