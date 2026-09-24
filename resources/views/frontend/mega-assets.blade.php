{{-- Styles of the "mega" variant, printed once per page. Its script is an Alpine component loaded on demand with Filament's x-load. --}}
@once
    @php($nonce = \Illuminate\Support\Facades\Vite::cspNonce())
    <style @if ($nonce) nonce="{{ $nonce }}" @endif>{!! \Syriable\Filament\Plugins\MenuBuilder\Support\FrontendAssets::megaCss() !!}</style>
@endonce
