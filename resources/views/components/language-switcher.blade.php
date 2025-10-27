<div>
    <form method="GET" action="{{ url()->current() }}">
        <select name="locale" onchange="this.form.submit()" class="border rounded px-3 py-2">
            <option value="en" {{ app()->getLocale() == 'en' ? 'selected' : '' }}>English</option>
            <option value="fr" {{ app()->getLocale() == 'fr' ? 'selected' : '' }}>Français</option>
        </select>
        @foreach (request()->except('locale') as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
    </form>
</div>
