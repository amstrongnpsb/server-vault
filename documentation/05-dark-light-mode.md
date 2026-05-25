# Dark and Light Mode

This document explains the dark and light mode feature added to the Vue/Inertia frontend.

---

## What Changed

The app now supports switching between light mode and dark mode from the authenticated navigation bar.

The selected theme is saved in `localStorage`, so the user's preference remains after refreshes and future visits.

If the user has not selected a theme yet, the app follows the operating system preference.

---

## Files Updated

### Tailwind Configuration

File:

```text
tailwind.config.js
```

Change:

```js
darkMode: 'class',
```

This tells Tailwind to enable dark mode when the `dark` class exists on the root HTML element.

---

### Theme Toggle Component

File:

```text
resources/js/Components/ThemeToggle.vue
```

This new component:

- Shows a sun icon in dark mode
- Shows a moon icon in light mode
- Toggles between light and dark mode
- Saves the selected theme to `localStorage`
- Applies the `dark` class to `document.documentElement`

Storage key:

```text
server-vault-theme
```

Possible values:

```text
light
dark
system
```

---

### Initial Theme Script

File:

```text
resources/views/app.blade.php
```

A small script was added before the frontend app loads.

Purpose:

- Read the saved theme from `localStorage`
- Check the system color preference when no saved theme exists
- Apply the `dark` class before Vue renders

This prevents the page from flashing the wrong theme during refresh.

---

### Authenticated Layout

File:

```text
resources/js/Layouts/AuthenticatedLayout.vue
```

Changes:

- Added the `ThemeToggle` component to the desktop navigation
- Added the `ThemeToggle` component to the mobile navigation
- Replaced hard-coded light colors with theme-aware Tailwind tokens

Examples:

```text
bg-background
bg-card
text-foreground
text-muted-foreground
border-border
```

---

### Dashboard Page

File:

```text
resources/js/Pages/Dashboard.vue
```

Changes:

- Replaced hard-coded gray and white classes with theme-aware classes
- Dashboard card now works in both light and dark mode

---

### Shared UI and Auth/Profile Screens

Updated shared components and pages so the theme looks consistent outside the dashboard too.

Examples:

```text
resources/js/Components/Dropdown.vue
resources/js/Components/DropdownLink.vue
resources/js/Components/InputLabel.vue
resources/js/Components/TextInput.vue
resources/js/Components/Checkbox.vue
resources/js/Components/PrimaryButton.vue
resources/js/Components/SecondaryButton.vue
resources/js/Components/DangerButton.vue
resources/js/Layouts/GuestLayout.vue
resources/js/Pages/Profile/Edit.vue
resources/js/Pages/Auth/Login.vue
resources/js/Pages/Auth/Register.vue
resources/js/Pages/Auth/ForgotPassword.vue
resources/js/Pages/Auth/VerifyEmail.vue
resources/js/Pages/Auth/ConfirmPassword.vue
```

---

## How It Works

The theme is controlled by a class on the root HTML element:

```html
<html class="dark">
```

When the class exists, Tailwind dark mode and the CSS variables in `resources/css/app.css` use the dark theme.

When the class is removed, the app uses the light theme.

---

## Local Development

Run Laravel and Vite in separate terminals.

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
npm.cmd run dev
```

Use `npm.cmd` on Windows PowerShell if `npm run dev` is blocked by the execution policy.

Open:

```text
http://127.0.0.1:8000
```

Laravel serves the application, routes, auth, sessions, and Inertia responses.

Vite serves the Vue, JavaScript, and Tailwind assets during development with hot reload.

---

## Production Note

For production, do not run the Vite dev server.

Build the frontend assets instead:

```bash
npm.cmd run build
```

Laravel will then serve the compiled files from `public/build`.
