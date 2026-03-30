<script setup lang="ts">
import { onMounted, ref } from 'vue';

export interface HeroCard {
    img: string;
    categoryEmoji: string;
    category: string;
    title: string;
    venue: string;
    date: string;
    distance: string;
}

/**
 * Each card has a unique position/size/rotation preset so they look
 * scattered organically around the hero text rather than in a tidy column.
 */
export interface CardPlacement {
    top: string; // CSS top position
    offset: string; // CSS left/right offset (always from the inner edge toward the text)
    rotate: number; // degrees
    scale: number; // 1 = normal, <1 = smaller, >1 = bigger
    width: string; // explicit width
    delay: number; // entrance animation delay in ms
    zIndex: number;
}

const props = defineProps<{
    cards: HeroCard[];
    side: 'left' | 'right';
    placements: CardPlacement[];
}>();

const reducedMotion = ref(false);
const mounted = ref(false);

onMounted(() => {
    reducedMotion.value = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    requestAnimationFrame(() => {
        mounted.value = true;
    });
});

function cardPositionStyle(p: CardPlacement): Record<string, string> {
    const style: Record<string, string> = {
        position: 'absolute',
        top: p.top,
        width: p.width,
        transform: `rotate(${p.rotate}deg) scale(${p.scale})`,
        zIndex: String(p.zIndex),
    };

    // Position from the side's outer edge
    if (props.side === 'left') {
        style.right = p.offset;
    } else {
        style.left = p.offset;
    }

    return style;
}

function entranceClass(index: number): string {
    if (reducedMotion.value) return 'is-visible';
    if (!mounted.value) return 'is-hidden';
    const p = props.placements[index];
    const dir = props.side === 'left' ? 'from-left' : 'from-right';
    return `card-entered ${dir}`;
}

function entranceDelay(index: number): string {
    const p = props.placements[index];
    return `${p?.delay ?? 200}ms`;
}
</script>

<template>
    <div class="hero-float-area" aria-hidden="true">
        <div
            v-for="(card, i) in cards.slice(0, placements.length)"
            :key="card.title"
            class="float-card-wrap"
            :class="entranceClass(i)"
            :style="{
                ...cardPositionStyle(placements[i]),
                animationDelay: entranceDelay(i),
            }"
        >
            <div class="hero-card">
                <div class="card-photo">
                    <img :src="card.img" :alt="''" loading="lazy" />
                    <div class="card-tint" />
                </div>
                <div class="card-body">
                    <span class="card-badge"
                        >{{ card.categoryEmoji }} {{ card.category }}</span
                    >
                    <p class="card-title">{{ card.title }}</p>
                    <p class="card-venue">{{ card.venue }}</p>
                    <div class="card-meta">
                        <span class="card-date">{{ card.date }}</span>
                        <span class="card-dist">📍 {{ card.distance }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ── Float area — fills the hero column, positions cards absolutely ── */
.hero-float-area {
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 480px;
}

/* ── Card wrapper (handles entrance animation) ── */
.float-card-wrap {
    position: absolute;
    transition:
        transform 0.3s ease,
        box-shadow 0.3s ease;
}

/* ── Card ── */
.hero-card {
    width: 100%;
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid #e8d5c8;
    box-shadow: 0 4px 20px rgba(196, 98, 58, 0.1);
    overflow: hidden;
    transition:
        transform 0.3s ease,
        box-shadow 0.3s ease;
    will-change: transform;
}

.float-card-wrap:hover .hero-card {
    box-shadow: 0 8px 28px rgba(196, 98, 58, 0.18);
}

.float-card-wrap:hover {
    transform: rotate(0deg) scale(1.04) translateY(-4px) !important;
    z-index: 50 !important;
}

/* ── Card internals ── */
.card-photo {
    position: relative;
    aspect-ratio: 16 / 10;
    overflow: hidden;
}

.card-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    filter: saturate(1.05);
}

.card-tint {
    position: absolute;
    inset: 0;
    background: rgba(196, 98, 58, 0.04);
    pointer-events: none;
}

.card-body {
    padding: 8px 10px 10px;
}

.card-badge {
    display: inline-block;
    background: #f5eae3;
    color: #c4623a;
    font-size: 9px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 99px;
    margin-bottom: 4px;
}

.card-title {
    font-family: var(--font-heading, Poppins, sans-serif);
    font-size: 11px;
    font-weight: 600;
    color: #1c1109;
    line-height: 1.3;
    margin: 0 0 2px;
}

.card-venue {
    font-size: 10px;
    color: #9c6b54;
    margin: 0 0 3px;
}

.card-meta {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
}

.card-date {
    font-size: 9px;
    color: #6b4535;
}

.card-dist {
    font-size: 9px;
    background: #fdf7f4;
    color: #6b4535;
    padding: 1px 5px;
    border-radius: 99px;
}

/* ── Entrance animations ── */
@keyframes from-left {
    from {
        opacity: 0;
        translate: -40px 0;
    }
    to {
        opacity: 1;
        translate: 0px 0;
    }
}

@keyframes from-right {
    from {
        opacity: 0;
        translate: 40px 0;
    }
    to {
        opacity: 1;
        translate: 0px 0;
    }
}

.is-hidden {
    opacity: 0;
}
.is-visible {
    opacity: 1;
}

.card-entered {
    animation-duration: 550ms;
    animation-timing-function: cubic-bezier(0.22, 1, 0.36, 1);
    animation-fill-mode: both;
}

.card-entered.from-left {
    animation-name: from-left;
}
.card-entered.from-right {
    animation-name: from-right;
}
</style>
