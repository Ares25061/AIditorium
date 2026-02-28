<!doctype html>
<html lang="en" data-theme="{{ $config->get('ui.theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="color-scheme" content="{{ $config->get('ui.theme', 'light') }}">
    <title>{{ $config->get('ui.title') ?? config('app.name') . ' - API Docs' }}</title>

    <script src="https://unpkg.com/@stoplight/elements@8.4.2/web-components.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements@8.4.2/styles.min.css">

    <script>
        const originalFetch = window.fetch;

        // intercept TryIt requests and add the XSRF-TOKEN header,
        // which is necessary for Sanctum cookie-based authentication to work correctly
        window.fetch = (url, options) => {
            const CSRF_TOKEN_COOKIE_KEY = "XSRF-TOKEN";
            const CSRF_TOKEN_HEADER_KEY = "X-XSRF-TOKEN";
            const getCookieValue = (key) => {
                const cookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith(key));
                return cookie?.split("=")[1];
            };

            const updateFetchHeaders = (
                headers,
                headerKey,
                headerValue,
            ) => {
                if (headers instanceof Headers) {
                    headers.set(headerKey, headerValue);
                } else if (Array.isArray(headers)) {
                    headers.push([headerKey, headerValue]);
                } else if (headers) {
                    headers[headerKey] = headerValue;
                }
            };
            const csrfToken = getCookieValue(CSRF_TOKEN_COOKIE_KEY);
            if (csrfToken) {
                const { headers = new Headers() } = options || {};
                updateFetchHeaders(headers, CSRF_TOKEN_HEADER_KEY, decodeURIComponent(csrfToken));
                return originalFetch(url, {
                    ...options,
                    headers,
                });
            }

            return originalFetch(url, options);
        };
    </script>

    <style>
        html, body { margin:0; height:100%; }
        body { background-color: var(--color-canvas); }
        /* issues about the dark theme of stoplight/mosaic-code-viewer using web component:
         * https://github.com/stoplightio/elements/issues/2188#issuecomment-1485461965
         */
        [data-theme="dark"] .token.property {
            color: rgb(128, 203, 196) !important;
        }
        [data-theme="dark"] .token.operator {
            color: rgb(255, 123, 114) !important;
        }
        [data-theme="dark"] .token.number {
            color: rgb(247, 140, 108) !important;
        }
        [data-theme="dark"] .token.string {
            color: rgb(165, 214, 255) !important;
        }
        [data-theme="dark"] .token.boolean {
            color: rgb(121, 192, 255) !important;
        }
        [data-theme="dark"] .token.punctuation {
            color: #dbdbdb !important;
        }
    </style>
</head>
<body style="height: 100vh; overflow-y: hidden">
<elements-api
    id="docs"
    tryItCredentialsPolicy="{{ $config->get('ui.try_it_credentials_policy', 'include') }}"
    router="hash"
    @if($config->get('ui.hide_try_it')) hideTryIt="true" @endif
    @if($config->get('ui.hide_schemas')) hideSchemas="true" @endif
    @if($config->get('ui.logo')) logo="{{ $config->get('ui.logo') }}" @endif
    @if($config->get('ui.layout')) layout="{{ $config->get('ui.layout') }}" @endif
/>
<script>
    (async () => {
        const docs = document.getElementById('docs');
        docs.apiDescriptionDocument = @json($spec);
    })();
</script>

@if($config->get('ui.theme', 'light') === 'system')
    <script>
        var mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

        function updateTheme(e) {
            if (e.matches) {
                window.document.documentElement.setAttribute('data-theme', 'dark');
                window.document.getElementsByName('color-scheme')[0].setAttribute('content', 'dark');
            } else {
                window.document.documentElement.setAttribute('data-theme', 'light');
                window.document.getElementsByName('color-scheme')[0].setAttribute('content', 'light');
            }
        }

        mediaQuery.addEventListener('change', updateTheme);
        updateTheme(mediaQuery);
    </script>
@endif
<!-- Замените ваш блок переключателя на этот -->
<div id="language-switcher" style="position: fixed; top: 10px; right: 260px; z-index: 10000; display: flex; gap: 5px; background: #f3f4f6; padding: 4px; border-radius: 8px; border: 1px solid #d1d5db; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <button onclick="setLanguage('ru')" id="btn-ru" style="cursor:pointer; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 500; font-size: 12px; transition: all 0.2s;">RU</button>
    <button onclick="setLanguage('en')" id="btn-en" style="cursor:pointer; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 500; font-size: 12px; transition: all 0.2s;">EN</button>
</div>

<script>
    function setLanguage(lang) {
        const url = new URL(window.location.href);
        url.searchParams.set('lang', lang);
        // Используем assign, чтобы перезагрузить страницу с новым параметром lang
        window.location.assign(url.toString());
    }

    // Логика подсветки активной кнопки
    const urlParams = new URLSearchParams(window.location.search);
    const currentLang = urlParams.get('lang') || '{{ App::getLocale() }}';

    const activeBtn = document.getElementById('btn-' + currentLang);
    if (activeBtn) {
        activeBtn.style.backgroundColor = '#ffffff';
        activeBtn.style.color = '#1f2937';
        activeBtn.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';
    }

    // Дополнительный стиль для неактивных кнопок
    ['ru', 'en'].forEach(l => {
        if (l !== currentLang) {
            const btn = document.getElementById('btn-' + l);
            if (btn) {
                btn.style.backgroundColor = 'transparent';
                btn.style.color = '#6b7280';
            }
        }
    });
</script>
</body>
</html>
