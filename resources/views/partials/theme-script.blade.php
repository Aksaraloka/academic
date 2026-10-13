<script>
    (function () {
        var choice = 'system';
        try { choice = localStorage.getItem('_x_theme') || 'system'; } catch (e) {}
        var dark = choice === 'dark' || (choice === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        // Gerak: aktif hanya bila pengguna tidak meminta pengurangan gerakan; selalu dicabut agar konten tidak tertahan.
        if (matchMedia('(prefers-reduced-motion: no-preference)').matches && !(navigator.connection && navigator.connection.saveData)) {
            document.documentElement.classList.add('motion');
            setTimeout(function () { document.documentElement.classList.remove('motion'); }, 1500);
        }
    })();
</script>
