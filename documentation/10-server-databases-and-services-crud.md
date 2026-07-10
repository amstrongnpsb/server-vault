# Plan: Server Databases & Services CRUD Implementation

This document outlines the implementation plan for the Server Databases and Services CRUD functionality. Based on project requirements, the "Detail Server" view is implemented as a **modal** on the existing Servers Index page rather than a separate page, allowing users to view and manage a server's databases and services without navigating away from the list.

## 1. Backend: Routes & Controllers Setup

### A. Eager Load Relations in `ServerController`
The `app/Http/Controllers/ServerController.php` index method is updated to include `databases` and `services` when fetching servers. This ensures the frontend has the necessary data ready for the detail modal.

```php
$servers = Server::query()
    ->with(['databases', 'services'])
    ->search($search)
    // ...
```

### B. Update Web Routes (`routes/web.php`)
Nested routes are added to manage databases and services for a given server.

```php
Route::middleware(['auth', 'verified'])->group(function () {
    // Existing servers resource...
    
    // Routes for Databases
    Route::post('/servers/{server}/databases', [App\Http\Controllers\ServerDatabaseController::class, 'store'])->name('servers.databases.store');
    Route::put('/databases/{serverDatabase}', [App\Http\Controllers\ServerDatabaseController::class, 'update'])->name('servers.databases.update');
    Route::delete('/databases/{serverDatabase}', [App\Http\Controllers\ServerDatabaseController::class, 'destroy'])->name('servers.databases.destroy');

    // Routes for Services
    Route::post('/servers/{server}/services', [App\Http\Controllers\ServerServiceController::class, 'store'])->name('servers.services.store');
    Route::put('/services/{serverService}', [App\Http\Controllers\ServerServiceController::class, 'update'])->name('servers.services.update');
    Route::delete('/services/{serverService}', [App\Http\Controllers\ServerServiceController::class, 'destroy'])->name('servers.services.destroy');
});
```

### C. Create Form Requests
Dedicated form requests handle validation:
- `StoreServerDatabaseRequest` & `UpdateServerDatabaseRequest`
- `StoreServerServiceRequest` & `UpdateServerServiceRequest`

### D. Create Controllers
- **`ServerDatabaseController`**: Handles `store`, `update`, and `destroy` operations for databases. It encrypts passwords and uses Inertia's `back()` to redirect, maintaining the page state.
- **`ServerServiceController`**: Handles `store`, `update`, and `destroy` operations for services. Similarly encrypts passwords and uses `back()`.

---

## 2. Frontend: Vue Components & Modals

The UI implementation centers around modals that live within the `Servers/Index.vue` context, built with `shadcn-vue`.

### A. Server Detail Modal
**File**: `resources/js/Pages/Servers/Modals/ServerDetailModal.vue`
- Triggered from the servers list via a "Details" action button.
- Displays a `Tabs` layout to separate the server's **Databases** and **Services**.
- Renders tables of the eager-loaded data.
- Provides "Add", "Edit", and "Delete" capabilities for each item in the lists.

### B. Database Modal
**File**: `resources/js/Pages/Servers/Modals/DatabaseModal.vue`
- Triggered from the `ServerDetailModal` for creating or editing databases.
- Submits to the backend using Inertia `useForm` with `preserveState: true` and `preserveScroll: true` to prevent the UI from resetting abruptly.

### C. Service Modal
**File**: `resources/js/Pages/Servers/Modals/ServiceModal.vue`
- Triggered from the `ServerDetailModal` for creating or editing services.
- Submits using Inertia `useForm` keeping the user flow uninterrupted.

### D. Update `Index.vue`
- A new "Details" action with an eye icon was added to the actions dropdown for each server row.
- The `ServerDetailModal` component is imported and placed in the template.
- State (`detailModalOpen`, `serverToView`) is managed to control visibility and pass the selected server data down to the modal.
