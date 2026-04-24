<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stats · MyWasser</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <style>
        html, body { margin: 0; padding: 0; font-family: system-ui, sans-serif; background: #f5f7fa; color: #222; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 24px 16px 48px; }
        header { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 20px; }
        header h1 { margin: 0; font-size: 22px; }
        header a { color: #1976d2; text-decoration: none; font-size: 14px; }
        header a:hover { text-decoration: underline; }

        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .card {
            background: #fff; border-radius: 10px; padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        .card .label { color: #666; font-size: 13px; }
        .card .value { font-size: 28px; font-weight: 600; margin-top: 4px; }
        .card .hint { color: #888; font-size: 12px; margin-top: 4px; }

        section { background: #fff; border-radius: 10px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        section h2 { margin: 0 0 12px; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        td { padding: 8px 0; border-bottom: 1px solid #eee; }
        td:last-child { text-align: right; font-variant-numeric: tabular-nums; color: #333; }
        tr:last-child td { border-bottom: none; }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <h1>MyWasser · Stats</h1>
            <a href="{{ route('home') }}">← Back to map</a>
        </header>

        <div class="cards">
            <div class="card">
                <div class="label">Drinking fountains</div>
                <div class="value">{{ number_format($fountains_total) }}</div>
            </div>
            <div class="card">
                <div class="label">With a picture</div>
                <div class="value">{{ number_format($fountains_with_photos) }}</div>
                @if ($fountains_total > 0)
                    <div class="hint">{{ number_format($fountains_with_photos / $fountains_total * 100, 1) }}% of all fountains</div>
                @endif
            </div>
            <div class="card">
                <div class="label">Public toilets</div>
                <div class="value">{{ number_format($toilets_total) }}</div>
            </div>
        </div>

        <section>
            <h2>Fountains by category</h2>
            <table>
                <tbody>
                    @foreach ($categories as $category)
                        <tr>
                            <td>
                                {{ $category['name'] }}
                                @if ($category['name_en'])
                                    <span style="color:#888;">({{ $category['name_en'] }})</span>
                                @endif
                            </td>
                            <td>{{ number_format($category['count']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>
</body>
</html>
