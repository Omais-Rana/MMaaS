<div class="fixed bottom-4 right-4 w-96 h-screen max-h-96 bg-white rounded-lg shadow-xl border border-gray-200 flex flex-col z-50">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-3 rounded-t-lg">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                </svg>
                <h3 class="font-semibold">Mobility Assistant</h3>
            </div>
        </div>
    </div>

    <!-- Messages Container -->
    <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50">
        @if (empty($messages))
            <div class="text-center text-gray-500 mt-8">
                @if ($isAuthenticated)
                    <p class="text-sm">👋 Hello! Ask me about routes and schedules.</p>
                @else
                    <p class="text-sm">🔒 Please log in to chat with me.</p>
                    <a href="{{ route('login') }}" class="text-blue-600 hover:underline text-xs mt-2 inline-block">
                        Sign in here
                    </a>
                @endif
            </div>
        @else
            @foreach ($messages as $msg)
                <div class="flex {{ $msg['type'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-xs px-3 py-2 rounded-lg {{ 
                        $msg['type'] === 'user' 
                            ? 'bg-blue-600 text-white rounded-br-none' 
                            : ($msg['type'] === 'error' 
                                ? 'bg-red-100 text-red-800 rounded-bl-none'
                                : 'bg-gray-200 text-gray-900 rounded-bl-none')
                    }}">
                        <p class="text-sm">{{ $msg['content'] }}</p>
                        @if (isset($msg['metadata']['response_time_ms']))
                            <p class="text-xs opacity-70 mt-1">⚡ {{ $msg['metadata']['response_time_ms'] }}ms</p>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif

        @if ($isLoading)
            <div class="flex justify-start">
                <div class="bg-gray-300 text-gray-900 px-3 py-2 rounded-lg rounded-bl-none">
                    <div class="flex gap-1">
                        <div class="w-2 h-2 bg-gray-600 rounded-full animate-bounce"></div>
                        <div class="w-2 h-2 bg-gray-600 rounded-full animate-bounce" style="animation-delay: 0.1s;"></div>
                        <div class="w-2 h-2 bg-gray-600 rounded-full animate-bounce" style="animation-delay: 0.2s;"></div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Input -->
    <form wire:submit="sendMessage" class="border-t border-gray-200 bg-white p-3 rounded-b-lg">
        <div class="flex gap-2">
            <input 
                wire:model="message"
                type="text" 
                placeholder="{{ $isAuthenticated ? 'Type your question...' : 'Login to chat' }}"
                {{ $isAuthenticated ? '' : 'disabled' }}
                @keydown.enter="$wire.sendMessage()"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 disabled:bg-gray-100 disabled:text-gray-400"
            />
            <button 
                type="submit"
                {{ $isAuthenticated && !$isLoading ? '' : 'disabled' }}
                class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white px-4 py-2 rounded-lg text-sm font-medium transition"
            >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5.951-1.429a1 1 0 00.725-.725l1.429-5.951 5.951 1.429a1 1 0 001.409-1.169l-7-14z"/>
                </svg>
            </button>
        </div>
    </form>
</div>
