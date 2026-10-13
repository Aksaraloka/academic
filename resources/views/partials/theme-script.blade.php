<script>
    (function () {
        var choice = 'system';
        try { choice = localStorage.getItem('_x_theme') || 'system'; } catch (e) {}
        var dark = choice === 'dark' || (choice === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
    })();
</script>
