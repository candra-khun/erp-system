<div>
    @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition
             class="mb-4 rounded-lg bg-red-100 p-4 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition
             class="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Hak Akses &amp; Role</h2>
            <p class="text-sm text-gray-500">Atur role user dan centang modul yang boleh diakses tanpa menyentuh kode.</p>
        </div>
        <div class="flex items-center gap-3">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari role/user..."
                   class="w-56 rounded-lg border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'role-form')"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                + Role Baru
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <!-- ============ KOLOM KIRI: MATRIKS ROLE × PERMISSION ============ -->
        <div class="lg:col-span-3">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">Role &amp; Modul Akses</h3>
                    <p class="text-xs text-gray-500">Klik role untuk atur permission-nya di panel kanan.</p>
                </div>
                <div class="max-h-[32rem] overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Role</th>
                                @foreach($this->groupedPermissions as $group => $perms)
                                    <th class="px-2 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-500"
                                        :title="{{ json_encode($group) }}">
                                        <button type="button" wire:click="toggleGroup('{{ $group }}')"
                                                class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-gray-600 hover:bg-gray-200"
                                                title="Centang/batal semua modul {{ $group }}">
                                            {{ ucfirst(str_replace('-', ' ', $group)) }}
                                        </button>
                                    </th>
                                @endforeach
                                <th class="px-3 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($this->roles as $role)
                                <tr class="{{ $selectedRoleId === $role->id ? 'bg-blue-50' : 'hover:bg-gray-50' }}">
                                    <td class="px-4 py-3">
                                        <button type="button" wire:click="selectRole({{ $role->id }})"
                                                class="text-left font-medium {{ $selectedRoleId === $role->id ? 'text-blue-700' : 'text-gray-900' }} hover:underline">
                                            {{ $role->display_name }}
                                        </button>
                                        <p class="text-xs text-gray-400">{{ $role->name }} · {{ $role->users_count }} user</p>
                                    </td>
                                    @foreach($this->groupedPermissions as $group => $perms)
                                        @php
                                            $permIds = $perms->pluck('id')->all();
                                            $granted = $role->permissions->pluck('id')->all();
                                            $grantedInGroup = count(array_intersect($permIds, $granted));
                                        @endphp
                                        <td class="px-2 py-3 text-center">
                                            @if($grantedInGroup === count($permIds) && count($permIds) > 0)
                                                <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-blue-600 text-white" title="Semua modul {{ $group }}">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                </span>
                                            @elseif($grantedInGroup > 0)
                                                <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-blue-200 text-blue-700" title="Sebagian modul {{ $group }} ({{ $grantedInGroup }}/{{ count($permIds) }})">
                                                    <span class="text-xs font-bold">–</span>
                                                </span>
                                            @else
                                                <span class="inline-block h-5 w-5 rounded border border-gray-200 bg-gray-50" title="Tidak ada akses modul {{ $group }}"></span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-3 text-right whitespace-nowrap">
                                        <button type="button" wire:click="editRole({{ $role->id }})"
                                                class="text-xs text-blue-600 hover:underline">Ubah</button>
                                        @if($role->name !== 'super_admin')
                                            <button type="button" wire:click="deleteRole({{ $role->id }})"
                                                    wire:confirm="Hapus role {{ $role->display_name }}?"
                                                    class="ml-3 text-xs text-red-600 hover:underline">Hapus</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============ KOLOM KANAN: PERMISSION CHECKBOX ============ -->
        <div class="lg:col-span-2">
            @if($this->selectedRole)
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-3">
                        <h3 class="text-sm font-semibold text-gray-900">Permission: {{ $this->selectedRole->display_name }}</h3>
                        <p class="text-xs text-gray-500">Centang modul yang boleh diakses role ini.</p>
                    </div>
                    <div class="max-h-[28rem] space-y-4 overflow-auto p-5">
                        @foreach($this->groupedPermissions as $group => $perms)
                            <div>
                                <button type="button" wire:click="toggleGroup('{{ $group }}')"
                                        class="mb-2 flex w-full items-center justify-between rounded-md bg-gray-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-600 hover:bg-gray-100">
                                    <span>{{ ucfirst(str_replace('-', ' ', $group)) }}</span>
                                    <span class="text-gray-400">{{ count(array_intersect($perms->pluck('id')->all(), $checked)) }}/{{ count($perms) }}</span>
                                </button>
                                <div class="space-y-1.5">
                                    @foreach($perms as $permission)
                                        @php
                                            $isActive = in_array((int) $permission->id, $checked, true);
                                        @endphp
                                        <label class="flex cursor-pointer items-start gap-2.5 rounded-md px-2.5 py-1.5 hover:bg-gray-50">
                                            <input type="checkbox"
                                                   value="{{ $permission->id }}"
                                                   @if($isActive) checked @endif
                                                   wire:change="togglePermission({{ $permission->id }})"
                                                   class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="flex-1">
                                                <span class="block text-sm font-medium text-gray-800">{{ $permission->display_name ?: $permission->name }}</span>
                                                @if($permission->description)
                                                    <span class="block text-xs text-gray-400">{{ $permission->description }}</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="border-t border-gray-200 px-5 py-3">
                        <button type="button" wire:click="savePermissions"
                                class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Simpan Permission
                        </button>
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
                    <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="mt-3 text-sm text-gray-500">Pilih role di tabel sebelah kiri untuk mengatur modul yang dapat diakses.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- ============ PENUGASAN ROLE KE USER ============ -->
    <div class="mt-8 rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-900">Penugasan Role ke User</h3>
            <p class="text-xs text-gray-500">Satu user bisa punya beberapa role; permission digabungkan.</p>
        </div>
        <div class="border-b border-gray-200 p-4">
            <form wire:submit="assignRole" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600">User</label>
                    <select wire:model="assignUserId"
                            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Pilih user —</option>
                        @foreach($this->users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                    @error('assignUserId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600">Role</label>
                    <select wire:model="assignRoleId"
                            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Pilih role —</option>
                        @foreach($this->roles as $role)
                            <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                        @endforeach
                    </select>
                    @error('assignRoleId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit"
                        class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Berikan Role
                </button>
            </form>
        </div>
        <div class="overflow-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">User</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Email</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Role</th>
                        <th class="px-3 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($userList as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                @forelse($user->roles as $role)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                        {{ $role->display_name }}
                                        @if($role->name !== 'super_admin')
                                            <button type="button" wire:click="revokeRole({{ $user->id }}, {{ $role->id }})"
                                                    wire:confirm="Lepas role {{ $role->display_name }} dari {{ $user->name }}?"
                                                    class="text-blue-400 hover:text-red-600" title="Lepas role">×</button>
                                        @endif
                                    </span>
                                @empty
                                    <span class="text-xs text-gray-400 italic">tanpa role</span>
                                @endforelse
                            </td>
                            <td class="px-3 py-3 text-right">
                                <span class="text-xs text-gray-400">{{ $user->permissions->count() }} permission</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">
            {{ $userList->links() }}
        </div>
    </div>

    <!-- ===== MODAL ROLE FORM ===== -->
    <div x-data="{ open: false }" x-on:open-modal.window="if ($event.detail === 'role-form') open = true"
         x-on:close-modal.window="if ($event.detail === 'role-form') open = false"
         x-show="open" x-transition.opacity x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" @click.outside="open = false">
            <h3 class="text-lg font-semibold text-gray-900">{{ $editingRoleId ? 'Ubah' : 'Buat' }} Role</h3>
            <form wire:submit="saveRole" class="mt-4 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600">Nama Role (huruf kecil, tanpa spasi)</label>
                    <input type="text" wire:model="roleName" placeholder="contoh: staff_hr"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('roleName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Nama Tampilan</label>
                    <input type="text" wire:model="roleDisplayName" placeholder="contoh: Staff HR"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('roleDisplayName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Deskripsi (opsional)</label>
                    <textarea wire:model="roleDescription" rows="2"
                              class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" x-on:click="open = false"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
