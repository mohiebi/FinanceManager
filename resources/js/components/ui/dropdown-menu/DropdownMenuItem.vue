<script setup lang="ts">
import type { DropdownMenuItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { DropdownMenuItem, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = withDefaults(defineProps<DropdownMenuItemProps & {
  class?: HTMLAttributes["class"]
  inset?: boolean
  variant?: "default" | "destructive"
}>(), {
  variant: "default",
})

const delegatedProps = reactiveOmit(props, "inset", "variant", "class")

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <DropdownMenuItem
    data-slot="dropdown-menu-item"
    :data-inset="inset ? '' : undefined"
    :data-variant="variant"
    v-bind="forwardedProps"
    :class="cn('text-white/80 transition-colors duration-150 focus:bg-white/10 focus:text-white hover:bg-white/10 hover:text-white data-[variant=destructive]:text-[#E94E50] data-[variant=destructive]:focus:bg-[#E94E50]/10 data-[variant=destructive]:focus:text-[#E94E50] data-[variant=destructive]:*:[svg]:!text-[#E94E50] [&_svg:not([class*=\'text-\'])]:text-white/50 relative flex cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-9 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=\'size-\'])]:size-4', props.class)"
  >
    <slot />
  </DropdownMenuItem>
</template>
