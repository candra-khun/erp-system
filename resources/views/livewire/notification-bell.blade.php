<div class="relative" wire:poll.30s>
    <button type="button" wire:click="toggle" class="relative text-gray-400 hover:text-gray-600" title="Notifikasi">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if($this->unreadCount > 0)
            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white bg-red-600 rounded-full">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    @if($open)
        <div class="absolute right-0 mt-2 w-80 rounded-lg border border-gray-200 bg-white shadow-lg z-50">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2">
                <span class="text-sm font-semibold text-gray-700">Notifikasi</span>
                @if($this->unreadCount > 0)
                    <button type="button" wire:click="markAllAsRead" class="text-xs font-medium text-blue-600 hover:text-blue-800">
                        Tandai semua dibaca
                    </button>
                @endif
            </div>
            <div class="max-h-80 overflow-y-auto divide-y divide-gray-100">
                @forelse($this->notifications as $notification)
                    <button type="button" wire:click="markAsRead('{{ $notification->id }}')"
                            class="block w-full px-4 py-3 text-left hover:bg-gray-50 {{ $notification->read_at ? 'opacity-60' : '' }}">
                        <p class="text-xs text-gray-700">{{ $notification->data['message'] ?? 'Notifikasi baru' }}</p>
                        <p class="mt-1 text-[10px] text-gray-400">{{ $notification->created_at?->diffForHumans() }}</p>
                    </button>
                @empty
                    <p class="px-4 py-6 text-center text-xs text-gray-400">Tidak ada notifikasi.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>