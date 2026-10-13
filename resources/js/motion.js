/**
 * Gerak ringan Aksaraloka (GSAP inti, dimuat lazy).
 *
 * Aturan (design-system.md bagian 9 dan skill gsap-performance):
 * - hanya opacity dan transform (x, y, scale), tidak pernah width/height/top/left/margin;
 * - durasi ~0,35 dtk, ease power2.out, jeda antar elemen 0,05 dtk, maksimum 12 elemen;
 * - konten tidak pernah tertahan: bila GSAP belum siap dalam 600 ms, elemen langsung tampil;
 * - mati total bila prefers-reduced-motion atau Save-Data;
 * - animasi masuk hanya sekali per halaman, tidak diulang pada pembaruan Livewire.
 */
const MAX_ENTRANCE = 12;
const MAX_DECORATIONS = 24;
const FALLBACK_MS = 600;

const html = document.documentElement;
const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');

let gsapPromise = null;

const loadGsap = () => (gsapPromise ??= import('gsap').then((module) => module.gsap ?? module.default));

const canAnimate = () => !reducedMotion.matches && !navigator.connection?.saveData;

const reveal = () => html.classList.remove('motion');

/**
 * Elemen yang dianimasikan: anak langsung akar halaman. Bila anaknya sebuah grid (kartu, tile),
 * anak-anak grid itulah yang diberi jeda berurutan.
 */
function collectTargets(root) {
    const targets = [];

    for (const child of root.children) {
        if (targets.length >= MAX_ENTRANCE) {
            break;
        }

        if (['SCRIPT', 'TEMPLATE', 'STYLE'].includes(child.tagName) || child.hidden || child.hasAttribute('x-cloak')) {
            continue;
        }

        if (child.matches('.grid') && child.children.length > 1) {
            for (const cell of child.children) {
                if (targets.length < MAX_ENTRANCE) {
                    targets.push(cell);
                }
            }

            continue;
        }

        targets.push(child);
    }

    return targets;
}

function countUp(gsap, element) {
    const match = element.textContent.trim().match(/^(\d{1,6})(%?)$/);

    if (!match || Number(match[1]) === 0) {
        return;
    }

    const [, digits, suffix] = match;
    const state = { value: 0 };

    gsap.to(state, {
        value: Number(digits),
        duration: 0.8,
        ease: 'power1.out',
        delay: 0.1,
        onUpdate: () => {
            element.textContent = Math.round(state.value) + suffix;
        },
        onComplete: () => {
            element.textContent = digits + suffix;
        },
    });
}

function decorate(gsap, main) {
    main.querySelectorAll('[data-countup]').forEach((element, index) => {
        if (index < MAX_DECORATIONS) {
            countUp(gsap, element);
        }
    });

    const bars = [...main.querySelectorAll('[data-grow]')].slice(0, MAX_DECORATIONS);

    if (bars.length) {
        gsap.from(bars, { scaleX: 0, transformOrigin: '0 50%', duration: 0.7, ease: 'power2.out', delay: 0.2, stagger: 0.04, clearProps: 'transform' });
    }

    const charts = [...main.querySelectorAll('[data-pop]')].slice(0, MAX_DECORATIONS);

    if (charts.length) {
        gsap.from(charts, { scale: 0.85, opacity: 0, transformOrigin: '50% 50%', duration: 0.5, ease: 'back.out(1.5)', delay: 0.15, stagger: 0.05, clearProps: 'transform,opacity' });
    }
}

export async function enter() {
    const main = document.getElementById('konten');
    const root = main?.firstElementChild;

    if (!root || root.dataset.entered || !canAnimate()) {
        reveal();

        return;
    }

    root.dataset.entered = '1';

    const timer = setTimeout(reveal, FALLBACK_MS);
    let gsap;

    try {
        gsap = await loadGsap();
    } catch (error) {
        clearTimeout(timer);
        reveal();

        return;
    }

    clearTimeout(timer);

    // Batas 600 ms sudah lewat dan konten sudah ditampilkan: jangan menyembunyikannya lagi.
    if (!html.classList.contains('motion')) {
        return;
    }

    const targets = collectTargets(root);

    if (targets.length) {
        gsap.fromTo(
            targets,
            { opacity: 0, y: 12 },
            { opacity: 1, y: 0, duration: 0.35, ease: 'power2.out', stagger: 0.05, clearProps: 'opacity,transform' },
        );
    }

    reveal();
    decorate(gsap, main);
}

// Sembunyikan konten baru hanya selama GSAP bersiap, dengan pengaman waktu.
document.addEventListener('livewire:navigating', () => {
    if (canAnimate()) {
        html.classList.add('motion');
        setTimeout(reveal, FALLBACK_MS * 2);
    }
});

document.addEventListener('livewire:navigated', enter);
document.addEventListener('DOMContentLoaded', enter);
