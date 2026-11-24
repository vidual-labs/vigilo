{{-- Runs before first paint so the page never flashes the wrong theme.
     The inline style paints the right background (slate-50 / slate-950) and
     color-scheme until app.css arrives; without it Firefox on a dark OS shows
     its own dark canvas on light pages (and vice versa) while the CSS loads. --}}
<style>
    html { background-color: #f8fafc; color-scheme: light; }
    html.dark { background-color: #020617; color-scheme: dark; }
</style>
<script>
    (function () {
        var theme = 'system';
        try { theme = localStorage.getItem('vigilo-theme') || 'system'; } catch (e) {}
        if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    })();
</script>
