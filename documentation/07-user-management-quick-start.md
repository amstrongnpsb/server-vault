# User Management - Quick Start Guide

## 🚀 Getting Started

### Prerequisites
Make sure you have roles in your database. Run this in `php artisan tinker`:

```php
use Spatie\Permission\Models\Role;

Role::create(['name' => 'admin']);
Role::create(['name' => 'moderator']);
Role::create(['name' => 'user']);
```

### Start Development Server

```bash
# Terminal 1: Start Laravel dev server
php artisan serve

# Terminal 2: Start Vite dev server
npm run dev

# Or use the combined dev command
composer dev
```

### Access User Management
Navigate to: **http://localhost:8000/users**

## 📋 Available Routes

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | /users | users.index | List all users |
| GET | /users/create | users.create | Show create form |
| POST | /users | users.store | Store new user |
| GET | /users/{user}/edit | users.edit | Show edit form |
| PUT/PATCH | /users/{user} | users.update | Update user |
| DELETE | /users/{user} | users.destroy | Delete user |

## 🎨 UI Components Used

### shadcn-vue Components
- ✅ Button
- ✅ Input
- ✅ Label
- ✅ Select
- ✅ Table
- ✅ Card
- ✅ Badge
- ✅ Dialog
- ✅ AlertDialog
- ✅ DropdownMenu
- ✅ Toaster (vue-sonner)

### Icons (lucide-vue-next)
- Plus (Add User)
- Pencil (Edit)
- Trash2 (Delete)
- MoreHorizontal (Actions menu)
- UserCircle (User avatar)
- X (Remove badge)

## 🔑 Key Features

### ✅ Create User
- Required: Name, Email, Password, Confirm Password
- Optional: Multiple roles
- Email must be unique
- Password automatically hashed

### ✅ Edit User
- Update name and email
- Change password (optional)
- Modify role assignments
- Email must be unique (except current user)

### ✅ Delete User
- Confirmation dialog required
- Cannot delete own account
- Success/error notifications

### ✅ List Users
- Paginated (10 per page)
- Shows: Name, Email, Roles, Created Date
- Action dropdown per row
- Role badges with colors

## 🎯 Form Validation Rules

### Store User (Create)
```php
'name' => 'required|string|max:255'
'email' => 'required|string|email|max:255|unique:users'
'password' => 'required|string|confirmed|Password::defaults()'
'roles' => 'nullable|array'
'roles.*' => 'exists:roles,name'
```

### Update User (Edit)
```php
'name' => 'required|string|max:255'
'email' => 'required|string|email|max:255|unique:users,email,{current_user_id}'
'password' => 'nullable|string|confirmed|Password::defaults()'
'roles' => 'nullable|array'
'roles.*' => 'exists:roles,name'
```

## 🎨 Role Badge Colors

```javascript
admin: 'destructive' (red)
moderator: 'default' (primary)
user: 'secondary' (gray)
other: 'outline' (bordered)
```

## 🔧 Customization

### Change Pagination
In `UserController.php`, line 24:
```php
->paginate(10); // Change 10 to desired number
```

### Add More Roles
```php
Role::create(['name' => 'editor']);
Role::create(['name' => 'viewer']);
```

### Customize Role Colors
In `Index.vue`, update `getRoleBadgeVariant()` function:
```javascript
const getRoleBadgeVariant = (roleName) => {
    const variants = {
        'admin': 'destructive',
        'moderator': 'default',
        'user': 'secondary',
        'editor': 'default',
        'viewer': 'outline',
    };
    return variants[roleName?.toLowerCase()] || 'outline';
};
```

### Add Authorization
In Form Request classes, update `authorize()` method:
```php
public function authorize(): bool
{
    return $this->user()->hasRole('admin');
}
```

Or add middleware in routes:
```php
Route::resource('users', UserController::class)
    ->middleware('role:admin');
```

## 📱 Responsive Breakpoints

- Mobile: < 640px
- Tablet: 640px - 1024px
- Desktop: > 1024px

All components are fully responsive and mobile-friendly.

## 🌙 Dark Mode

Dark mode is automatically supported through your existing ThemeToggle component.
All colors use CSS variables that adapt to the current theme.

## 🐛 Troubleshooting

### Error: "Role does not exist"
Make sure you've created roles in the database (see Prerequisites).

### Error: "Class UserController not found"
Run: `composer dump-autoload`

### Flash messages not showing
Check that HandleInertiaRequests middleware is properly updated.

### Styles not applied
Make sure Vite dev server is running: `npm run dev`

### Page not found (404)
Clear route cache: `php artisan route:clear`

## 📚 Learn More

- [Laravel Documentation](https://laravel.com/docs)
- [Vue 3 Documentation](https://vuejs.org)
- [Inertia.js Documentation](https://inertiajs.com)
- [shadcn-vue Documentation](https://www.shadcn-vue.com)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)

## 🎉 You're All Set!

Navigate to `/users` and start managing your users!
