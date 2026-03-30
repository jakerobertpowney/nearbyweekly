# Scope: Interest Picker Fixes + Disabled Button State

Three targeted changes across two files.

---

## 1. Search matches group titles as well as sub-interest names

**File:** `resources/js/components/InterestGroupPicker.vue`

**Current behaviour:** `filteredGroups` maps over groups and filters `children` by name match only. Searching "music" returns nothing because "Music" is a group title, not a sub-interest name.

**New behaviour:** If the query matches the group name, show the entire group (all children). If the query doesn't match the group name but does match some children, show those children only (existing behaviour). If neither matches, hide the group.

**Updated `filteredGroups` computed:**

```ts
const filteredGroups = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.groups;

    return props.groups
        .map((group) => {
            if (group.name.toLowerCase().includes(q)) {
                return group; // group title matched — show all children unfiltered
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
```

The empty state message (`No interests match "..."`) already covers the case where neither group titles nor sub-interests match — no change needed there.

---

## 2. "Select all" selects all even when one sub-interest is already selected

**File:** `resources/js/components/InterestGroupPicker.vue`

**Current behaviour:** `toggleGroup()` checks `anySelected` — if even one child is ticked, clicking "Select all" *deselects* everything. This is the wrong action when the label reads "Select all".

**Root cause:** The function uses `anySelected` as the branch condition, but the label only says "Deselect" when `allSelectedInGroup()` is true. The label and the behaviour are out of sync when the group is in a partial state.

**Fix:** Replace the `anySelected` check with `allSelected`. Deselect only when every child is already ticked; otherwise always select all.

```ts
function toggleGroup(group: InterestGroup): void {
    const childIds = group.children.map((c) => c.id);
    const allSelected = childIds.every((id) => props.modelValue.includes(id));

    const updated = allSelected
        ? props.modelValue.filter((id) => !childIds.includes(id))
        : [...new Set([...props.modelValue, ...childIds])];

    emit('update:modelValue', updated);
}
```

The template label `{{ allSelectedInGroup(group) ? 'Deselect' : 'Select all' }}` is already correct and needs no change.

---

## 3. Continue and Submit buttons disabled until validation is met

**File:** `resources/js/pages/Onboarding/Start.vue`

**Current behaviour:** The Continue and Submit buttons are always visually active. Clicking them when conditions aren't met triggers `validateCurrentStep()` which populates `stepErrors`, but the button itself gives no signal that it's not ready.

**Add a `canProceed` computed** that evaluates the active step's readiness without side effects:

```ts
const canProceed = computed<boolean>(() => {
    if (currentStep.value === 1) {
        return form.interests.length > 0;
    }
    if (currentStep.value === 2) {
        return (
            form.postcode.trim() !== '' &&
            postcodePattern.test(form.postcode.trim())
        );
    }
    if (currentStep.value === 3) {
        return form.radius_miles !== null;
    }
    if (currentStep.value === 4) {
        return (
            form.email.trim() !== '' &&
            emailPattern.test(form.email.trim())
        );
    }
    return true;
});
```

`postcodePattern` and `emailPattern` are already defined in the component's script — reuse them here, no duplication.

**Apply to the footer buttons** using `:disabled` and the shadcn `Button`'s built-in disabled styling (`disabled:opacity-50 disabled:cursor-not-allowed` is applied automatically by the component):

```html
<!-- Continue button (steps 1–3) -->
<Button
    v-if="currentStep < 4"
    type="button"
    class="rounded-xl bg-orange-500 px-6 text-white hover:bg-orange-600"
    :disabled="!canProceed"
    @click="nextStep"
>
    Continue
    <ArrowRight class="h-4 w-4" />
</Button>

<!-- Submit button (step 4) -->
<Button
    v-else
    type="button"
    class="rounded-xl bg-orange-500 px-6 text-white hover:bg-orange-600"
    :disabled="!canProceed || form.processing"
    @click="submit"
>
    Get my weekly picks
    <ArrowRight class="h-4 w-4" />
</Button>
```

**`nextStep()` and `submit()` stay unchanged.** They still call `validateCurrentStep()` on click, which writes `stepErrors` for the inline error messages. The disabled state is a visual aid only — it doesn't bypass validation, it just removes the false affordance of an always-active button.

**Step 2 nuance:** The postcode pattern check in `canProceed` will keep the button disabled while the user is mid-typing a valid postcode (e.g. `SW1A` before the second half is entered). That's intentional — a partially entered postcode is not valid. The experience is consistent with how step 3 works (no radius selected = button disabled) and step 1 (no interests = button disabled).

---

## Files Changed

| File | Changes |
|---|---|
| `resources/js/components/InterestGroupPicker.vue` | `filteredGroups` — also match group titles; `toggleGroup` — branch on `allSelected` not `anySelected` |
| `resources/js/pages/Onboarding/Start.vue` | Add `canProceed` computed; bind `:disabled="!canProceed"` on Continue and Submit buttons |
