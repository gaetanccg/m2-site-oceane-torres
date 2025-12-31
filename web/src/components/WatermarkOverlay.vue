<template>
    <div class="watermark-container" @contextmenu.prevent>
        <slot />
        <div class="watermark-overlay" aria-hidden="true">
            <span v-for="n in 20" :key="n">@ oceane torres</span>
        </div>
    </div>
</template>

<script setup lang="ts">
</script>

<style scoped>
.watermark-container {
    position: relative;
    display: inline-block;
    line-height: 0;
    user-select: none;
    -webkit-user-drag: none;
}

.watermark-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 3rem;
    padding: 2rem;
    pointer-events: none;
    user-select: none;
    z-index: 10;
    overflow: hidden;
}

.watermark-overlay span {
    font-size: 0.85rem;
    font-weight: 300;
    color: rgba(255, 255, 255, 0.25);
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
    letter-spacing: 0.05em;
    white-space: nowrap;
    transform: rotate(-25deg);
    text-transform: lowercase;
}

@media (max-width: 768px) {
    .watermark-overlay {
        gap: 1.5rem;
        padding: 1rem;
    }

    .watermark-overlay span {
        font-size: 0.6rem;
    }
}
</style>
