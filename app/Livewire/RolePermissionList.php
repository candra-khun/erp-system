<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('components.layouts.erp', ['title' => 'Hak Akses & Role'])]
class RolePermissionList extends Component
{
    use WithPagination;

    // ===== Form Role =====
    public ?int $editingRoleId = null;

    public string $roleName = '';

    public string $roleDisplayName = '';

    public string $roleDescription = '';

    public string $search = '';

    public ?int $selectedRoleId = null;

    // ===== Form User =====
    public ?int $assignUserId = null;

    public ?int $assignRoleId = null;

    /**
     * Permission yang dicentang untuk role yang sedang dipilih.
     *
     * @var list<int>
     */
    public array $checked = [];

    protected function rules(): array
    {
        return [
            'roleName' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_-]+$/'],
            'roleDisplayName' => ['required', 'string', 'max:255'],
            'roleDescription' => ['nullable', 'string'],
        ];
    }

    protected $validationAttributes = [
        'roleName' => 'nama role',
        'roleDisplayName' => 'nama tampilan',
        'assignUserId' => 'user',
        'assignRoleId' => 'role',
    ];

    /**
     * Daftar permission dikelompokkan per modul.
     *
     * @return array<string, array<int, Permission>>
     */
    #[Computed]
    public function groupedPermissions()
    {
        return Permission::orderBy('group')
            ->orderBy('id')
            ->get()
            ->groupBy('group')
            ->all();
    }

    /**
     * Daftar role untuk dropdown.
     *
     * @return Collection<int, Role>
     */
    #[Computed]
    public function roles()
    {
        return Role::withCount('users')
            ->with('permissions')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('display_name', 'like', '%'.$this->search.'%'))
            ->orderBy('id')
            ->get();
    }

    /**
     * Daftar user untuk dropdown assignment.
     */
    #[Computed]
    public function users()
    {
        return User::with('roles')
            ->orderBy('name')
            ->get();
    }

    /**
     * Role yang sedang dipilih untuk atur permission.
     */
    #[Computed]
    public function selectedRole()
    {
        return $this->selectedRoleId !== null
            ? Role::with('permissions')->find($this->selectedRoleId)
            : null;
    }

    public function selectRole(int $roleId): void
    {
        $role = Role::find($roleId);

        if ($role === null) {
            throw new HttpException(404, 'Role tidak ditemukan.');
        }

        $this->selectedRoleId = $roleId;
        $this->checked = $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function togglePermission(int $permissionId): void
    {
        $key = array_search($permissionId, $this->checked, true);

        if ($key === false) {
            $this->checked[] = $permissionId;
        } else {
            unset($this->checked[$key]);
            $this->checked = array_values($this->checked);
        }
    }

    public function toggleGroup(string $group): void
    {
        $groupPermissionIds = collect($this->groupedPermissions[$group] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $allChecked = count(array_intersect($groupPermissionIds, $this->checked)) === count($groupPermissionIds);

        if ($allChecked) {
            $this->checked = array_values(array_diff($this->checked, $groupPermissionIds));
        } else {
            $this->checked = array_values(array_unique(array_merge($this->checked, $groupPermissionIds)));
        }
    }

    public function savePermissions(): void
    {
        if ($this->selectedRole === null) {
            throw new RuntimeException('Pilih role terlebih dahulu.');
        }

        $this->selectedRole->permissions()->sync($this->checked);

        $this->dispatch('permissions-updated', roleId: $this->selectedRole->id);

        session()->flash('success', 'Permission untuk role "'.$this->selectedRole->display_name.'" berhasil diperbarui.');
    }

    public function saveRole(): void
    {
        $validated = $this->validate();

        $role = $this->editingRoleId !== null
            ? Role::find($this->editingRoleId)
            : new Role;

        if ($role === null) {
            throw new HttpException(404, 'Role tidak ditemukan.');
        }

        $exists = Role::where('name', $validated['roleName'])
            ->where('id', '!=', $this->editingRoleId ?? 0)
            ->exists();

        if ($exists) {
            $this->addError('roleName', 'Nama role sudah digunakan.');
            $this->dispatch('close-modal', 'role-form');

            return;
        }

        $role->fill([
            'name' => $validated['roleName'],
            'display_name' => $validated['roleDisplayName'],
            'description' => $validated['roleDescription'] ?: null,
        ]);

        $role->save();

        $this->dispatch('close-modal', 'role-form');
        $this->reset(['editingRoleId', 'roleName', 'roleDisplayName', 'roleDescription']);

        session()->flash('success', 'Role "'.$role->display_name.'" berhasil disimpan.');
    }

    public function editRole(int $roleId): void
    {
        $role = Role::find($roleId);

        if ($role === null) {
            throw new HttpException(404, 'Role tidak ditemukan.');
        }

        $this->editingRoleId = $roleId;
        $this->roleName = $role->name;
        $this->roleDisplayName = $role->display_name;
        $this->roleDescription = $role->description ?? '';

        $this->dispatch('open-modal', 'role-form');
    }

    public function deleteRole(int $roleId): void
    {
        $role = Role::find($roleId);

        if ($role === null) {
            throw new HttpException(404, 'Role tidak ditemukan.');
        }

        if ($role->name === 'super_admin') {
            session()->flash('error', 'Role super_admin tidak dapat dihapus.');
            $this->dispatch('rbac-error');

            return;
        }

        if ($role->users()->exists()) {
            session()->flash('error', 'Role "'.$role->display_name.'" masih dipakai oleh '.$role->users()->count().' user. Lepaskan dulu.');
            $this->dispatch('rbac-error');

            return;
        }

        $role->permissions()->detach();
        $role->delete();

        if ($this->selectedRoleId === $roleId) {
            $this->selectedRoleId = null;
            $this->checked = [];
        }

        session()->flash('success', 'Role "'.$role->display_name.'" berhasil dihapus.');
    }

    public function assignRole(): void
    {
        $this->validate([
            'assignUserId' => ['required', 'exists:users,id'],
            'assignRoleId' => ['required', 'exists:roles,id'],
        ]);

        $user = User::find($this->assignUserId);
        $role = Role::find($this->assignRoleId);

        if ($user === null || $role === null) {
            throw new RuntimeException('User atau role tidak ditemukan.');
        }

        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->reset(['assignUserId', 'assignRoleId']);

        session()->flash('success', 'Role "'.$role->display_name.'" berhasil diberikan kepada '.$user->name.'.');
    }

    public function revokeRole(int $userId, int $roleId): void
    {
        $user = User::find($userId);
        $role = Role::find($roleId);

        if ($user === null || $role === null) {
            throw new HttpException(404, 'User atau role tidak ditemukan.');
        }

        if ($role->name === 'super_admin' && $user->roles()->where('name', 'super_admin')->count() <= 1) {
            session()->flash('error', 'Tidak dapat melepas role super_admin terakhir dari user.');
            $this->dispatch('rbac-error');

            return;
        }

        $user->roles()->detach($roleId);

        session()->flash('success', 'Role "'.$role->display_name.'" dilepas dari '.$user->name.'.');
    }

    public function render()
    {
        $users = User::with('roles')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(15, ['*'], 'usersPage');

        return view('livewire.role-permission-list', [
            'userList' => $users,
        ]);
    }
}
