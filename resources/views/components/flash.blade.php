@if (session('status'))
    <div class="mb-4 rounded-md border border-teal-200 bg-teal-50 px-3 py-2 text-sm text-teal-900 dark:border-teal-800 dark:bg-teal-950 dark:text-teal-100" role="status">
        {{ session('status') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-100" role="alert">
        {{ session('error') }}
    </div>
@endif
