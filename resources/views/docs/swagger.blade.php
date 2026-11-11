<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dokumentasi API Internet Banking · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>
        body { margin: 0; background: #fafafa; }
        .kop { background: #0f5f55; color: #fff; padding: 14px 24px; font-family: system-ui, sans-serif; }
        .kop strong { font-size: 18px; }
        .kop span { opacity: .8; margin-left: 8px; font-size: 14px; }
        .swagger-ui .topbar { display: none; }
    </style>
</head>
<body>
    <div class="kop"><strong>{{ config('app.name') }}</strong><span>Dokumentasi API Internet Banking nasabah</span></div>
    <div id="swagger-ui"></div>

    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js" crossorigin></script>
    <script>
        // Try it out memakai sesi cookie Sanctum: kirim cookie dan header X-XSRF-TOKEN dari cookie XSRF-TOKEN.
        function bacaCookie(nama) {
            const baris = document.cookie.split('; ').find((c) => c.startsWith(nama + '='));
            return baris ? decodeURIComponent(baris.split('=').slice(1).join('=')) : null;
        }

        window.ui = SwaggerUIBundle({
            url: '{{ route('docs.api.spec') }}',
            dom_id: '#swagger-ui',
            deepLinking: true,
            persistAuthorization: false,
            displayRequestDuration: true,
            docExpansion: 'list',
            requestInterceptor: (req) => {
                req.credentials = 'include';
                req.headers['Accept'] = req.headers['Accept'] || 'application/json';
                req.headers['X-Requested-With'] = 'XMLHttpRequest';
                const xsrf = bacaCookie('XSRF-TOKEN');
                if (xsrf) req.headers['X-XSRF-TOKEN'] = xsrf;
                return req;
            },
        });
    </script>
</body>
</html>
