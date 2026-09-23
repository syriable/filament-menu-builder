{{-- Structural styles and the small dropdown script, printed once per page. --}}
@once
    @php($nonce = \Illuminate\Support\Facades\Vite::cspNonce())
    <style @if ($nonce) nonce="{{ $nonce }}" @endif>{!! \Syriable\Filament\Plugins\MenuBuilder\Support\FrontendAssets::css() !!}</style>
    <script @if ($nonce) nonce="{{ $nonce }}" @endif>{!! \Syriable\Filament\Plugins\MenuBuilder\Support\FrontendAssets::js() !!}</script>
@endonce
