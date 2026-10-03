@props(['manifestUrl' => null, 'appName' => null])

@php($apariencia = app(\App\Services\AparienciaSistemaService::class))

<link rel="manifest" href="{{ $manifestUrl ?: route('pwa.manifest') }}">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ $apariencia->colorPrimario() }}">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#101828">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $appName ?: $apariencia->nombre() }}">
<link rel="apple-touch-icon" href="{{ $apariencia->logoAppMovilUrl() }}">
