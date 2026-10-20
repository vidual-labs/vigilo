{{-- Runs before first paint so dark mode never flashes white. --}}
<script>
    (function () {
        var theme = 'system';
        try { theme = localStorage.getItem('vigilo-theme') || 'system'; } catch (e) {}
        if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    })();
</script>
