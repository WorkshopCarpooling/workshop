<link rel="stylesheet" href="{{ asset('css/system-theme.css') }}?v={{ filemtime(public_path('css/system-theme.css')) }}">
<link rel="stylesheet" href="{{ asset('css/workspace-experience.css') }}?v={{ filemtime(public_path('css/workspace-experience.css')) }}">
<script src="{{ asset('js/interface-motion.js') }}?v={{ filemtime(public_path('js/interface-motion.js')) }}" defer></script>
@include('partials.login-arrival')
<link rel="stylesheet" href="{{ asset('css/sidebar-compact.css') }}?v={{ is_file(public_path('css/sidebar-compact.css'))?filemtime(public_path('css/sidebar-compact.css')):'compact' }}">
<script src="{{ asset('js/sidebar-compact.js') }}?v={{ is_file(public_path('js/sidebar-compact.js'))?filemtime(public_path('js/sidebar-compact.js')):'compact' }}" defer></script>
<link rel="stylesheet" href="{{ asset('css/interface-contrast.css') }}?v={{ is_file(public_path('css/interface-contrast.css'))?filemtime(public_path('css/interface-contrast.css')):'contrast' }}">
