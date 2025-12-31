<template>
    <div :class="['page-header', wrapperClass]">
        <div class="page-header-inner">
            <h1 :class="['page-title', titleClass]">
                <span class="title-text">{{ title }}</span>
            </h1>
            <p v-if="subtitle" :class="['page-subtitle', subtitleClass]">{{ subtitle }}</p>
        </div>
    </div>
</template>

<script setup lang="ts">
interface Props {
    title: string
    subtitle?: string
    wrapperClass?: string
    titleClass?: string
    subtitleClass?: string
}

withDefaults(defineProps<Props>(), {
    subtitle: '',
    wrapperClass: 'text-center mb-16',
    titleClass: 'text-5xl md:text-6xl font-light mb-4',
    subtitleClass: 'text-gray-600 font-light text-lg'
})
</script>

<style scoped>
.page-header{
    --gold: #D4AF37;
    --text: #0b0b0b;
    --muted: rgba(11, 11, 11, 0.6);
    background: #ffffff;
    color: var(--text);
    display: flex;
    justify-content: center;
    padding: 1rem 1rem;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

.page-header-inner{
    position: relative;
    max-width: 980px;
    width: 100%;
    text-align: center;
    padding: 0 1rem;
}

/* titre fin et aéré */
.page-title{
    margin: 0;
    display: inline-block;
    line-height: 1.05;
    font-weight: 300;
    font-family: ui-serif, Georgia, "Times New Roman", serif;
    position: relative;
}

/* texte du titre — l'accent doré est une barre fine sous le titre */
.title-text{
    color: var(--text);
    display: inline-block;
    padding-bottom: 1rem;
    position: relative;
}

.title-text::after{
    content: "";
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    bottom: 0;
    width: 46%;
    height: 6px;
    border-radius: 4px;
    background: linear-gradient(90deg, var(--gold), rgba(212, 175, 55, 0.9));
    opacity: 0.98;
    filter: drop-shadow(0 2px 6px rgba(212, 175, 55, 0.08));
}

/* sous-titre discret */
.page-subtitle{
    margin: 0.9rem auto 0;
    color: var(--muted);
    font-weight: 300;
    font-size: 1.05rem;
    max-width: 780px;
    line-height: 1.5;
}

/* responsive: ajustements pour petits écrans */
@media (max-width: 640px){
    .page-header{
        padding: 2.5rem 0.75rem;
    }

    .page-header-inner::before{
        top: -0.7rem;
        width: 44px;
        height: 3px;
    }

    .title-text::after{
        width: 60%;
        height: 5px;
    }

    .page-subtitle{
        font-size: 1rem;
        padding: 0 0.5rem;
    }
}
</style>
