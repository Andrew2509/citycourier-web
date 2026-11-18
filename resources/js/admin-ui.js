/**
 * City Courier — Admin UI motion layer (Blade + Vite).
 *
 * Pembagian tugas (sesuai master prompt):
 * - Motion.dev (API vanilla, bukan motion/react) → animasi interaksi UI:
 *   entrance kartu, dropdown, notifikasi, donut chart SVG.
 * - Anime.js v4 → animasi berbasis timeline/nilai: counter statistik
 *   dan pertumbuhan bar chart.
 *
 * Tidak ada dua library yang mengendalikan elemen DOM yang sama.
 * Semua animasi dimatikan bila `prefers-reduced-motion: reduce`.
 */
import { animate, stagger } from "motion";
import { animate as animeAnimate, stagger as animeStagger } from "animejs";
import { initIcons } from "./cc-icons";

// Konversi ikon dijalankan lebih dulu (bukan bagian dari layer animasi),
// agar placeholder ikon tidak sempat terlihat dan animasi kartu bekerja
// pada markup final.
initIcons();

const EASE_OUT_EXPO = [0.22, 1, 0.36, 1];
const REDUCED = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

const fmtID = (n) => new Intl.NumberFormat("id-ID").format(n);

/** Kartu: fade-in + naik kecil, staggered sesuai urutan DOM. */
function initCards() {
    const cards = document.querySelectorAll(".card");
    if (!cards.length) return;
    animate(
        cards,
        { opacity: [0, 1], transform: ["translateY(12px)", "translateY(0px)"] },
        { duration: 0.45, ease: EASE_OUT_EXPO, delay: stagger(0.05) },
    );
}

/** Dropdown topbar: pop halus saat dibuka (class hidden di-toggle inline script). */
function initDropdowns() {
    ["menuNotif", "menuProfile"].forEach((id) => {
        const menu = document.getElementById(id);
        if (!menu) return;
        new MutationObserver(() => {
            if (menu.classList.contains("hidden")) return;
            menu.style.transformOrigin = "top right";
            animate(
                menu,
                {
                    opacity: [0, 1],
                    transform: [
                        "scale(0.96) translateY(-4px)",
                        "scale(1) translateY(0px)",
                    ],
                },
                { duration: 0.16, ease: "ease-out" },
            );
        }).observe(menu, { attributes: true, attributeFilter: ["class"] });
    });
}

/** Notifikasi flash: slide-down masuk (fade-out keluar tetap dari inline script). */
function initFlash() {
    ["flashSuccess", "flashError"].forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        animate(
            el,
            {
                opacity: [0, 1],
                transform: ["translateY(-10px)", "translateY(0px)"],
            },
            { duration: 0.3, ease: "ease-out" },
        );
    });
}

/**
 * Counter angka statistik (Anime.js): elemen dengan atribut data-cc-count
 * berisi nilai akhir mentah. Teks awal Blade sudah terformat — tanpa JS
 * angka tetap benar; dengan JS angka berhitung 0 → nilai akhir.
 */
function initCounters() {
    document.querySelectorAll("[data-cc-count]").forEach((el, i) => {
        const target =
            parseInt(String(el.dataset.ccCount).replace(/\D/g, ""), 10) || 0;
        if (target <= 0) return;
        const state = { v: 0 };
        animeAnimate(state, {
            v: target,
            duration: 950,
            delay: i * 70,
            ease: "outExpo",
            onUpdate: () => {
                el.textContent = fmtID(Math.round(state.v));
            },
        });
    });
}

/**
 * Bar chart 7 hari (Anime.js): bar tumbuh dari scaleY 0 (transform-only,
 * tidak memicu layout), label nilainya fade-in setelah bar selesai tumbuh.
 */
function initBars() {
    const bars = document.querySelectorAll("[data-cc-bar]");
    if (!bars.length) return;
    animeAnimate(bars, {
        scaleY: [0, 1],
        duration: 750,
        ease: "outExpo",
        delay: animeStagger(45),
    });
    const labels = document.querySelectorAll("[data-cc-bar] > span");
    if (labels.length) {
        animeAnimate(labels, { opacity: [0, 1], duration: 300, delay: 700 });
    }
}

/**
 * Donut chart SVG (Motion): tiap segmen menggambar arc dari 0 ke panjang
 * final via stroke-dasharray. Nilai attr Blade adalah state final sehingga
 * tanpa JS donut tetap tampil utuh.
 */
function initDonuts() {
    document.querySelectorAll("[data-cc-donut-seg]").forEach((seg, i) => {
        const len = parseFloat(seg.dataset.ccDonutSeg) || 0;
        const c = parseFloat(seg.dataset.ccDonutC) || 0;
        if (len <= 0 || c <= 0) return;
        animate(
            seg,
            { strokeDasharray: [`0 ${c}`, `${len} ${c - len}`] },
            { duration: 0.8, ease: EASE_OUT_EXPO, delay: 0.15 + i * 0.12 },
        );
    });
}

function init() {
    if (REDUCED) {
        document.documentElement.classList.remove("cc-anim");
        return;
    }
    try {
        initCards();
        initDropdowns();
        initFlash();
        initCounters();
        initBars();
        initDonuts();
        window.__ccReady = true;
    } catch (e) {
        // Fallback: tampilkan semua state final bila modul gagal.
        document.documentElement.classList.remove("cc-anim");
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}
