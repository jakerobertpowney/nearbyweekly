# Scope: Interest Picker Redesign — Cards + Always-Visible Sub-interests + Search

## What's Changing

The current `InterestGroupPicker.vue` renders groups as accordion rows. Sub-interests are hidden behind a `ChevronDown` toggle — the user has to know to tap each group to see what's inside. On a first-time onboarding screen where the user needs to understand their options quickly, hidden content is friction.

This scope replaces the accordion with an open card grid where every sub-interest is immediately visible, adds a search filter for quick access, and preserves the existing group-level select-all toggle.

**Only `InterestGroupPicker.vue` changes.** The component's props, emits, and `v-model` contract stay identical, so `Start.vue` (onboarding step 1) and `Preferences/Edit.vue` both pick up the new design with no changes required in either parent.

---

## On the Search Filter

Yes, worth adding. With 7 groups and 40+ sub-interests fully visible across cards, the user is looking at a lot of content at once. A simple text filter that hides non-matching cards as you type costs almost nothing to build (a single `computed` over `filteredGroups`, no backend involvement) and meaningfully shortcuts the experience for someone who already knows what they want — typing "jazz" or "yoga" beats scanning every card. It also sets a good precedent for when the interest list grows.

---

## Redesigned `InterestGroupPicker.vue`

### Layout

Replace the single bordered list with a responsive card grid:

```
grid grid-cols-1 gap-4 sm:grid-cols-2
```

Each group becomes a self-contained card:

```
rounded-2xl border-2 bg-white p-5 transition-colors
```

The border colour reflects selection state:
- No children selected → `border-slate-200`
- Some children selected → `border-orange-300 bg-orange-50/30`
- All children selected → `border-orange-400 bg-orange-50/50`

### Card Structure

```
┌─────────────────────────────────────┐
│  🎵  Music             [Select all] │  ← group header row
│                                     │
│  ╭──────────╮  ╭──────────────╮     │  ← sub-interest pills
│  │ Live Gigs│  │   Festivals  │     │
│  ╰──────────╯  ╰──────────────╯     │
│  ╭────────────────╮  ╭──────────╮   │
│  │Electronic&Dance│  │ Indie&Rock│  │
│  ╰────────────────╯  ╰──────────╯   │
└─────────────────────────────────────┘
```

**Group header row:** emoji + group name (`font-medium text-slate-800`) on the left. On the right, a small text button: "Select all" when none/some are selected, "Deselect" when all are selected. This replaces the ChevronDown — no expand/collapse needed. The button uses the existing `toggleGroup()` logic.

**Sub-interest pills:** `flex flex-wrap gap-2 mt-3`. Each pill is a `<button>` using the existing pill styles:
- Unselected: `rounded-full border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:border-slate-300 hover:bg-slate-50`
- Selected: `rounded-full border border-orange-400 bg-orange-50 px-3 py-1.5 text-sm font-medium text-orange-700`

Selected count badge on the group name is removed — the visually selected pills make it redundant.

### Search Filter

A search input sits above the card grid, inside the component:

```
┌─────────────────────────────────────┐
│  🔍  Search interests...         ✕  │  ← search bar (× only visible when non-empty)
└─────────────────────────────────────┘
```

Styling matches the rest of the onboarding form: `h-11 rounded-xl border border-slate-200 pl-10 text-sm` with a `Search` icon inset left (`lucide-vue-next`). The × clear button appears when `searchQuery` is non-empty.

**Filter logic** — a `computed filteredGroups`:

```ts
const filteredGroups = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.groups;

    return props.groups
        .map((group) => ({
            ...group,
            children: group.children.filter((child) =>
                child.name.toLowerCase().includes(q)
            ),
        }))
        .filter((group) => group.children.length > 0);
});
```

Groups with no matching children disappear. Groups with some matching children show only the matching pills. The card grid re-flows naturally as cards appear/disappear.

When `searchQuery` is non-empty and no groups match, show a short empty state below the search bar:

```
No interests match "{{searchQuery}}" — try a different word.
```

**No highlighting of matched text** — the filter is brief enough that showing/hiding suffices without the complexity of wrapping match spans.

### "Selected" summary strip

The existing summary strip in `Start.vue` (the orange pill row below the picker showing all selected names) stays as-is in the parent. No change needed there.

---

## State Changes Inside the Component

Remove:
- `openGroups` ref (no accordion state needed)
- `isOpen()`, `toggleOpen()` functions
- `ChevronDown` import

Add:
- `searchQuery` ref (`ref<string>('')`)
- `filteredGroups` computed
- `clearSearch()` helper

Keep unchanged:
- `toggleGroup()` — now wired to "Select all / Deselect" button
- `toggleSubInterest()` — unchanged
- `selectedCountInGroup()`, `allSelectedInGroup()`, `someSelectedInGroup()` — still used for card border state and "Select all" label

---

## Files Changed

| File | Change |
|---|---|
| `resources/js/components/InterestGroupPicker.vue` | Full rewrite — card grid layout, always-visible pills, search filter, select-all button replacing chevron |

No changes required in `Start.vue`, `Preferences/Edit.vue`, or any backend file.

---

## What This Does Not Cover

**Preferences page layout** — `Preferences/Edit.vue` uses `InterestGroupPicker` but likely has a different page width and surrounding layout. The card grid will reflow correctly at whatever width it's given, but it's worth reviewing the preferences page visually after the change to ensure the two-column grid reads well in that context.

**Keyboard navigation** — pill buttons are already `<button>` elements so they're tab-focusable. Full arrow-key navigation within a group is out of scope.

**Animating card appearance during search** — cards snap in/out with `v-if`. A `Transition` wrapper with a short fade could be added later if the snap feels abrupt during testing.
