<script setup>
import { cn } from "@/lib/utils";

const props = defineProps({
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});
</script>

<template>
  <div
    data-slot="card-content"
    :class="cn('min-w-0 max-w-full px-4 group-data-[size=sm]/card:px-3', props.class)"
  >
    <slot />
  </div>
</template>
