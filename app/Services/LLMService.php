<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LLMService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.mistral.ai/v1';
    private string $model = 'mistral-large-latest';
    private float $temperature = 0.7;
    private int $maxTokens = 1000;

    public function __construct()
    {
        $this->apiKey = config('ai.mistral_api_key') ?? env('MISTRAL_API_KEY');
    }

    /**
     * Set the model to use
     */
    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Set temperature for randomness (0-1)
     */
    public function setTemperature(float $temperature): self
    {
        $this->temperature = max(0, min(1, $temperature));
        return $this;
    }

    /**
     * Set max tokens in response
     */
    public function setMaxTokens(int $tokens): self
    {
        $this->maxTokens = $tokens;
        return $this;
    }

    /**
     * Send a message to Mistral and get response
     * 
     * @param array $messages Array of ['role' => 'user'|'assistant', 'content' => 'text']
     * @param array $tools Optional tools for structured output
     * 
     * @return array ['content' => 'response text', 'tokens' => ['input' => X, 'output' => Y], 'stop_reason' => 'stop']
     */
    public function chat(array $messages, ?array $tools = null): array
    {
        try {
            $payload = [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ];

            if ($tools) {
                $payload['tools'] = $tools;
                $payload['tool_choice'] = 'auto';
            }

            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post($this->apiUrl . '/chat/completions', $payload);

            if (! $response->successful()) {
                Log::error('Mistral API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'content' => null,
                    'error' => 'API request failed: ' . $response->status(),
                    'tokens' => ['input' => 0, 'output' => 0],
                ];
            }

            $data = $response->json();

            return [
                'content' => $data['choices'][0]['message']['content'] ?? null,
                'tool_calls' => $data['choices'][0]['message']['tool_calls'] ?? [],
                'tokens' => [
                    'input' => $data['usage']['prompt_tokens'] ?? 0,
                    'output' => $data['usage']['completion_tokens'] ?? 0,
                    'total' => $data['usage']['total_tokens'] ?? 0,
                ],
                'stop_reason' => $data['choices'][0]['finish_reason'] ?? 'stop',
            ];
        } catch (\Exception $e) {
            Log::error('LLMService exception: ' . $e->getMessage());

            return [
                'content' => null,
                'error' => $e->getMessage(),
                'tokens' => ['input' => 0, 'output' => 0],
            ];
        }
    }

    /**
     * Simple prompt + response (no tools)
     */
    public function prompt(string $systemPrompt, string $userMessage): array
    {
        return $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ]);
    }

    /**
     * Get estimated cost from token counts
     */
    public function estimateCost(int $inputTokens, int $outputTokens): float
    {
        // Mistral Large pricing (update as needed)
        $inputCostPer1k = 0.002;
        $outputCostPer1k = 0.006;

        return ($inputTokens / 1000) * $inputCostPer1k + ($outputTokens / 1000) * $outputCostPer1k;
    }
}
