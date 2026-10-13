import './theme';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('qrCode', () => ({
        async draw(payload) {
            if (!payload) {
                return;
            }
            const { default: QRCode } = await import('qrcode');
            this.$el.innerHTML = await QRCode.toString(payload, { type: 'svg', margin: 0, errorCorrectionLevel: 'M' });
        },
    }));

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
