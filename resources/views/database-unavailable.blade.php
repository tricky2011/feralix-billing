<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database Unavailable</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f7f8fa;
            color: #111827;
            margin: 0;
            padding: 2rem;
        }

        main {
            max-width: 680px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }

        h1 {
            margin-top: 0;
            font-size: 1.75rem;
        }

        p {
            line-height: 1.6;
        }

        code {
            background: #f3f4f6;
            border-radius: 6px;
            padding: 0.15rem 0.35rem;
        }
    </style>
</head>
<body>
    <main>
        <h1>{{ $message }}</h1>
        <p>{{ $details }}</p>
        <p>{{ $hint }}</p>

        @if (! empty($connection))
            <p>Configured connection: <code>{{ $connection }}</code></p>
        @endif
    </main>
</body>
</html>
