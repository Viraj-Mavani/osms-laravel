<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'OSMS'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-spotlight min-vh-100 d-flex flex-column">
    <main class="flex-grow-1 d-flex align-items-center justify-content-center py-5 px-3">
        <div class="w-100 animate-fade-up" style="max-width: 26rem;">
            <div class="text-center mb-4">
                <a href="{{ route('home') }}" class="text-decoration-none d-inline-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-3"
                          style="width:2.25rem;height:2.25rem;">
                        <i class="bi bi-eyeglasses fs-5"></i>
                    </span>
                    <span class="fs-4 fw-semibold font-display text-dark">OSMS</span>
                </a>
            </div>

            <div class="card glass border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-sm-5">
                    @yield('content')
                </div>
            </div>

            <p class="text-center text-muted-foreground mt-4 mb-0" style="font-size:.8rem;">
                Optical Store Management System
            </p>
        </div>
    </main>
</body>
</html>
