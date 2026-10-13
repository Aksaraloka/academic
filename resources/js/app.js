import './theme';
import './motion';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('toaster', () => ({
        items: [],
        push({ message, type = 'success' }) {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, type });
            setTimeout(() => this.remove(id), 3500);
        },
        remove(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    }));
});
