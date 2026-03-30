<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { InterestGroup } from '@/types';

const props = defineProps<{
    groups: InterestGroup[];
    modelValue: number[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number[]]
}>();

const searchQuery = ref('');

const filteredGroups = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.groups;

    return props.groups
        .map((group) => {
            if (group.name.toLowerCase().includes(q)) {
                return group;
            }
            return {
                ...group,
                children: group.children.filter((child) =>
                    child.name.toLowerCase().includes(q),
                ),
            };
        })
        .filter((group) =>
            group.name.toLowerCase().includes(q) || group.children.length > 0,
        );
});

function clearSearch(): void {
    searchQuery.value = '';
}

function selectedCountInGroup(group: InterestGroup): number {
    return group.children.filter((child) => props.modelValue.includes(child.id)).length;
}

function allSelectedInGroup(group: InterestGroup): boolean {
    return group.children.length > 0 && group.children.every((child) => props.modelValue.includes(child.id));
}

function someSelectedInGroup(group: InterestGroup): boolean {
    const count = selectedCountInGroup(group);
    return count > 0 && count < group.children.length;
}

function toggleGroup(group: InterestGroup): void {
    const childIds = group.children.map((c) => c.id);
    const allSelected = childIds.every((id) => props.modelValue.includes(id));

    const updated = allSelected
        ? props.modelValue.filter((id) => !childIds.includes(id))
        : [...new Set([...props.modelValue, ...childIds])];

    emit('update:modelValue', updated);
}

function toggleSubInterest(id: number): void {
    const updated = props.modelValue.includes(id)
        ? props.modelValue.filter((interestId) => interestId !== id)
        : [...props.modelValue, id];

    emit('update:modelValue', updated);
}
</script>

<template>
    <div class="space-y-4">
        <!-- Search bar -->
        <div class="relative">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
                v-model="searchQuery"
                type="text"
                placeholder="Search interests..."
                class="h-11 w-full rounded-xl border border-slate-200 pl-10 pr-9 text-sm text-slate-800 placeholder-slate-400 focus:border-[#E8956D] focus:outline-none focus:ring-2 focus:ring-[#FDF7F4]"
            />
            <button
                v-if="searchQuery"
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                @click="clearSearch"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <!-- Empty search state -->
        <p v-if="filteredGroups.length === 0" class="py-4 text-center text-sm text-slate-500">
            No interests match "{{ searchQuery }}" — try a different word.
        </p>

        <!-- Card grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-1">
            <div
                v-for="group in filteredGroups"
                :key="group.id"
                class="rounded-2xl border-2 bg-white p-5 transition-colors"
                :class="
                    allSelectedInGroup(group)
                        ? 'border-[#C4623A] bg-[#FDF7F4]/50'
                        : someSelectedInGroup(group)
                            ? 'border-[#E8956D] bg-[#FDF7F4]/30'
                            : 'border-slate-200'
                "
            >
                <!-- Group header -->
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl leading-none">{{ group.emoji }}</span>
                        <span
                            class="font-medium"
                            :class="someSelectedInGroup(group) || allSelectedInGroup(group) ? 'text-[#1C1109]' : 'text-slate-800'"
                        >{{ group.name }}</span>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 text-xs font-medium transition-colors"
                        :class="someSelectedInGroup(group) || allSelectedInGroup(group) ? 'text-[#A84E2C] hover:text-[#6B4535]' : 'text-slate-400 hover:text-slate-600'"
                        @click="toggleGroup(group)"
                    >
                        {{ allSelectedInGroup(group) ? 'Deselect' : 'Select all' }}
                    </button>
                </div>

                <!-- Sub-interest pills -->
                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-for="sub in group.children"
                        :key="sub.id"
                        type="button"
                        class="rounded-full border px-3 py-1.5 text-sm transition-colors"
                        :class="modelValue.includes(sub.id)
                            ? 'border-[#C4623A] bg-[#FDF7F4] font-medium text-[#6B4535]'
                            : 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
                        @click="toggleSubInterest(sub.id)"
                    >
                        {{ sub.name }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
