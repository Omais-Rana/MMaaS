<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Validate;

class ChatWidget extends Component
{
    #[Validate('required|string|max:2000')]
    public string $message = '';

    public array $messages = [];
    public bool $isLoading = false;
    public bool $isAuthenticated = false;

    public function mount(): void
    {
        $this->isAuthenticated = Auth::check();
    }

    /**
     * Send a message to the AI chat
     */
    public function sendMessage(): void
    {
        if (! $this->isAuthenticated) {
            $this->messages[] = [
                'type' => 'error',
                'content' => 'Please log in to use the chat feature.',
            ];
            return;
        }

        $this->validate();

        if (empty($this->message)) {
            return;
        }

        // Add user message to display
        $this->messages[] = [
            'type' => 'user',
            'content' => $this->message,
        ];

        $userMessage = $this->message;
        $this->message = '';
        $this->isLoading = true;

        try {
            $response = $this->callChatAPI($userMessage);

            if ($response['status'] === 'success') {
                $this->messages[] = [
                    'type' => 'assistant',
                    'content' => $response['message'],
                    'metadata' => [
                        'tokens' => $response['tokens'] ?? null,
                        'response_time_ms' => $response['response_time_ms'] ?? null,
                    ],
                ];
            } else {
                $this->messages[] = [
                    'type' => 'error',
                    'content' => 'Error: ' . ($response['message'] ?? 'Failed to get response'),
                ];
            }
        } catch (\Exception $e) {
            $this->messages[] = [
                'type' => 'error',
                'content' => 'Error communicating with server: ' . $e->getMessage(),
            ];
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Call the chat API endpoint
     */
    private function callChatAPI(string $message): array
    {
        $response = \Illuminate\Support\Facades\Http::withToken(optional(Auth::user())->api_token ?? '')
            ->post(route('api.chat.message'), [
                'message' => $message,
            ]);

        if (! $response->successful()) {
            return [
                'status' => 'error',
                'message' => 'API request failed: ' . $response->status(),
            ];
        }

        return $response->json();
    }

    public function render()
    {
        return view('livewire.chat-widget');
    }
}
