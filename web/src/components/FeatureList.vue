<template>
    <ul :class="['space-y-2', listClass]">
        <li
            v-for="(feature, index) in features"
            :key="index"
            :class="['flex items-start', itemClass]"
        >
            <IconCheck :class="`mr-2 mt-0.5 flex-shrink-0 ${checkClass}`" />
            <span :class="textClass">{{ feature }}</span>
        </li>
    </ul>
</template>

<script setup lang="ts">
import {IconCheck} from './icons'

interface Props {
    features: string[]
    listClass?: string
    itemClass?: string
    textClass?: string
    checkClass?: string
}

withDefaults(defineProps<Props>(), {
    listClass: '',
    itemClass: '',
    textClass: 'text-sm text-gray-600',
    checkClass: 'w-4 h-4 text-gold',
})
</script>
