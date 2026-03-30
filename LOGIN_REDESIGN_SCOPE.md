# Login Page Redesign Scope

## What This Covers

The auth flow — `Login.vue`, `TwoFactorChallenge.vue`, `VerifyEmail.vue` — currently renders
through `AuthSimpleLayout.vue`. The layout itself has good bones (split-panel, warm gradient
background, glassmorphism card) but diverges from the brand in several specific ways. The form
elements inside each page use unstyled defaults that look nothing like the onboarding flow the
user has just come from.

**Files to change:**

| File | What changes |
|---|---|
| `resources/js/layouts/auth/AuthSimpleLayout.vue` | Logo colour, logo icon, heading font, left-panel copy tone |
| `resources/js/pages/auth/Login.vue` | Input sizing, button style, status message, "Sign up" link |
| `resources/js/pages/auth/TwoFactorChallenge.vue` | Button style, OTP ring colour, recovery link style |
| `resources/js/pages/auth/VerifyEmail.vue` | Button style, status message, log out link |

`AuthLayout.vue` (the thin pass-through wrapper) does not need changes.

---

## 1. `AuthSimpleLayout.vue` — Layout Fixes

### Logo (right card)

Current: `bg-slate-950` square with `AppLogoIcon` SVG.
Brand: every other branded surface uses `bg-orange-500` with a `Sparkles` icon.

Replace:
```html
<!-- Before -->
<div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-950 text-white">
    <AppLogoIcon class="size-8 fill-current" />
</div>
<Badge variant="outline" class="rounded-full">
    NearbyWeekly
</Badge>

<!-- After -->
<div class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-500">
    <Sparkles class="h-5 w-5 text-white" />
</div>
<span class="font-heading text-lg font-semibold text-slate-900">nearbyweekly</span>
```

Remove the `AppLogoIcon` import and add `Sparkles` from `lucide-vue-next` (already imported for
the feature card). Drop the `Badge` import if it becomes unused after this change.

### Card title / description typography

The `CardTitle` that renders `{{ title }}` should use `font-heading`:

```html
<CardTitle class="font-heading text-2xl font-bold text-slate-900">{{ title }}</CardTitle>
<CardDescription class="text-sm leading-6 text-slate-500">{{ description }}</CardDescription>
```

### Left-panel headings

Add `font-heading` to the marketing headline on the left:

```html
<h1 class="font-heading max-w-2xl text-4xl font-semibold tracking-tight text-slate-950 md:text-5xl">
    The weekly event newsletter is the product.
</h1>
```

### Left-panel badge

Replace the black `bg-slate-950` badge with the orange pill used elsewhere:

```html
<!-- Before -->
<Badge class="rounded-full bg-slate-950 px-3 py-1 text-white">
    Passwordless access
</Badge>

<!-- After -->
<span class="inline-flex items-center gap-1.5 rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-600">
    <Sparkles class="h-3 w-3" />
    Passwordless access
</span>
```

### Feature card icons

The "Magic links" card uses `bg-amber-100 text-amber-700`. Update both icon containers to use
brand orange consistently:

```html
<!-- Magic links icon -->
<div class="rounded-full bg-orange-100 p-2 text-orange-600">
    <Mail class="h-4 w-4" />
</div>

<!-- Preference-first icon -->
<div class="rounded-full bg-orange-100 p-2 text-orange-600">
    <Sparkles class="h-4 w-4" />
</div>
```

---

## 2. `Login.vue` — Form Element Fixes

### Email input

Current input uses default sizing. Match the onboarding email field:

```html
<!-- Before -->
<Input
    id="email"
    type="email"
    name="email"
    required
    autofocus
    :tabindex="1"
    autocomplete="email"
    placeholder="email@example.com"
/>

<!-- After -->
<div class="relative">
    <Mail class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
    <Input
        id="email"
        type="email"
        name="email"
        required
        autofocus
        :tabindex="1"
        autocomplete="email"
        placeholder="you@example.com"
        class="h-14 rounded-2xl border-slate-300 pl-12 text-lg focus-visible:ring-orange-300"
    />
</div>
```

Import `Mail` from `lucide-vue-next`.

### Submit button

```html
<!-- Before -->
<Button type="submit" class="mt-4 w-full" ...>

<!-- After -->
<Button
    type="submit"
    class="mt-4 h-14 w-full rounded-2xl bg-orange-500 text-base font-semibold text-white hover:bg-orange-600"
    ...
>
```

### Status message

```html
<!-- Before -->
<div class="mb-4 text-center text-sm font-medium text-green-600">

<!-- After -->
<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700">
```

### "Sign up" link

The current copy says "Don't have an account? Sign up" and links to the register route. Since
NearbyWeekly's registration is the onboarding flow:

```html
<!-- Before -->
<div class="text-center text-sm text-muted-foreground" v-if="canRegister">
    Don't have an account?
    <TextLink :href="register()" :tabindex="3">Sign up</TextLink>
</div>

<!-- After -->
<div class="text-center text-sm text-slate-500" v-if="canRegister">
    New to NearbyWeekly?
    <TextLink :href="register()" :tabindex="3" class="text-orange-500 hover:text-orange-600">
        Get started
    </TextLink>
</div>
```

Remove the `Label` component import if it becomes unused — the label for the email field can be
dropped in favour of the placeholder, matching the onboarding style where inputs are self-labelled.

---

## 3. `TwoFactorChallenge.vue` — Button & OTP Styling

### Continue buttons (both OTP and recovery modes)

```html
<!-- Before -->
<Button type="submit" class="w-full" ...>

<!-- After -->
<Button
    type="submit"
    class="h-14 w-full rounded-2xl bg-orange-500 text-base font-semibold text-white hover:bg-orange-600"
    ...
>
```

Apply this change to both `<Button>` instances (OTP mode and recovery mode).

### OTP slot ring

The `InputOTP` component uses Tailwind's default focus ring. Override via a wrapper or class prop
to use orange:

```html
<InputOTP
    id="otp"
    v-model="code"
    :maxlength="6"
    :disabled="processing"
    autofocus
    class="[&_[data-active]]:ring-orange-400"
>
```

### Recovery mode toggle link

```html
<!-- Before -->
<button
    type="button"
    class="text-foreground underline decoration-neutral-300 underline-offset-4 ..."
>

<!-- After -->
<button
    type="button"
    class="text-orange-500 underline-offset-4 hover:underline hover:text-orange-600 text-sm"
>
```

Apply to both toggle buttons.

---

## 4. `VerifyEmail.vue` — Button & Status Styling

### Resend button

```html
<!-- Before -->
<Button :disabled="processing" variant="secondary">

<!-- After -->
<Button
    :disabled="processing"
    class="h-14 rounded-2xl bg-orange-500 px-8 text-base font-semibold text-white hover:bg-orange-600"
>
```

### Status message

```html
<!-- Before -->
<div class="mb-4 text-center text-sm font-medium text-green-600">
    A new verification link has been sent ...
</div>

<!-- After -->
<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700">
    A new verification link has been sent ...
</div>
```

### Log out link

```html
<!-- Before -->
<TextLink :href="logout()" as="button" class="mx-auto block text-sm">
    Log out
</TextLink>

<!-- After -->
<TextLink :href="logout()" as="button" class="mx-auto block text-sm text-slate-400 hover:text-slate-600">
    Log out
</TextLink>
```

---

## What Does Not Change

- The two-column split layout structure — it works well and is already branded in spirit
- The warm gradient background (`radial-gradient` amber + linear warm cream) — this is correct
- The glassmorphism card (`bg-white/90 backdrop-blur shadow-2xl`) — correct
- The left-panel copy text itself (the words are fine, just the type treatment needs `font-heading`)
- Routing and form logic — purely visual changes throughout

---

## Summary of Visual Changes

| Element | Before | After |
|---|---|---|
| Logo container | `bg-slate-950` + SVG icon | `bg-orange-500` + `Sparkles` |
| Wordmark | `Badge` outline variant | `font-heading` span |
| Left badge | `bg-slate-950` pill | `bg-orange-100 text-orange-600` pill with icon |
| Feature card icons | `bg-amber-100` / `bg-slate-100` | `bg-orange-100 text-orange-600` (both) |
| Email input | Default height + no icon | `h-14 rounded-2xl` + `Mail` icon left |
| Submit buttons | Default `Button` | `h-14 rounded-2xl bg-orange-500` |
| OTP slots | Default ring | Orange active ring |
| Status messages | `text-green-600` inline | `bg-emerald-50 border border-emerald-200 rounded-xl` |
| 2FA toggle links | Default underline style | `text-orange-500` |
| Card heading | Default `CardTitle` | `font-heading font-bold` |
| "Sign up" link | `text-muted-foreground` + default | `text-orange-500 hover:text-orange-600` |
