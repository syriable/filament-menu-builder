{{--
    Opens the form of an item that uses POST, PUT, PATCH or DELETE. The form
    is display: contents, so the item keeps its place in the layout; the
    item itself is the submit button. Closed by frontend.form-close.
--}}
<form
    class="mb-form"
    method="{{ $item->method->formMethod() }}"
    action="{{ $item->url }}"
    @if ($item->openInNewTab) target="_blank" @endif
>
    @csrf
    @if ($spoofed = $item->method->spoofed())
        <input type="hidden" name="_method" value="{{ $spoofed }}">
    @endif
