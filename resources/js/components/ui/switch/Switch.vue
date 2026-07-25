<script setup lang="ts">
import type { SwitchRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { SwitchRoot, SwitchThumb } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<SwitchRootProps & {
  class?: HTMLAttributes["class"]
  checked?: boolean
}>()
const emits = defineEmits<{
  "update:modelValue": [value: boolean]
  "update:checked": [value: boolean]
}>()

const delegatedProps = reactiveOmit(props, "class", "checked")

function updateChecked(value: boolean) {
  emits("update:modelValue", value)
  emits("update:checked", value)
}
</script>

<template>
  <SwitchRoot
    data-slot="switch"
    v-bind="delegatedProps"
    :model-value="checked ?? modelValue"
    :class="
      cn('peer inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors outline-none focus-visible:ring-2 focus-visible:ring-[#02CD86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#1a1a1a] disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-[#02CD86] data-[state=unchecked]:bg-white/15',
         props.class)"
    @update:model-value="updateChecked"
  >
    <SwitchThumb
      data-slot="switch-thumb"
      class="pointer-events-none block size-4 rounded-full bg-white shadow-lg ring-0 transition-transform data-[state=unchecked]:translate-x-0 data-[state=checked]:translate-x-4 rtl:data-[state=checked]:-translate-x-4"
    />
  </SwitchRoot>
</template>
