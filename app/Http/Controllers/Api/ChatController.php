<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConversationLog;
use App\Services\LLMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    private LLMService $llmService;

    public function __construct(LLMService $llmService)
    {
        $this->llmService = $llmService;
    }

    /**
     * POST /api/chat/message
     * Send a message to the AI chat
     * 
     * Request:
     * {
     *   "message": "How do I get from downtown to the airport?",
     *   "conversation_id": optional (for multi-turn)
     * }
     * 
     * Response:
     * {
     *   "status": "success",
     *   "message": "AI response...",
     *   "conversation_id": "uuid",
     *   "intent": "RouteSearch",
     *   "confidence": 0.95,
     *   "tools_used": ["RouteTool"],
     *   "tokens_used": 150,
     *   "response_time_ms": 1250
     * }
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Reject if not authenticated
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $userMessage = $validated['message'];
        $startTime = microtime(true);

        try {
            // Create system prompt for conversational assistant
            $systemPrompt = $this->getSystemPrompt($user);

            // Call LLM
            $llmResponse = $this->llmService->prompt($systemPrompt, $userMessage);

            if (isset($llmResponse['error'])) {
                return response()->json([
                    'error' => 'Failed to process message',
                    'message' => $llmResponse['error'],
                ], 500);
            }

            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            // Log the conversation
            $log = ConversationLog::create([
                'user_id' => $user->id,
                'tenant_id' => $user->isSubAdmin() ? $user->id : $user->tenant_id,
                'agent_type' => 'conversational',
                'user_message' => $userMessage,
                'agent_response' => $llmResponse['content'],
                'tokens_used' => $llmResponse['tokens']['total'] ?? 0,
                'estimated_cost' => $this->llmService->estimateCost(
                    $llmResponse['tokens']['input'] ?? 0,
                    $llmResponse['tokens']['output'] ?? 0
                ),
                'response_time_ms' => $responseTime,
                'confidence_score' => 0.85, // Placeholder - will be enhanced in Agent 1
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $llmResponse['content'],
                'conversation_id' => $log->id,
                'tokens' => $llmResponse['tokens'],
                'response_time_ms' => $responseTime,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error processing message',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Generate system prompt based on user role and tenant
     */
    private function getSystemPrompt($user): string
    {
        $role = match ($user->role) {
            'customer' => 'a customer of our mobility service',
            'employee' => 'an employee of one of our mobility operators',
            'subadmin' => 'a mobility operator administrator',
            default => 'a user',
        };

        return <<<PROMPT
You are a helpful mobility assistant AI for a transit routing platform. You are helping {$role}.

Your responsibilities:
1. Answer questions about routes, schedules, and stops in a conversational manner
2. Search for routes between two locations when asked
3. Provide information about accessibility, fares, and policies
4. Ask clarifying questions if information is unclear
5. Be concise and helpful

When users ask about routes:
- Ask for origin, destination, and preferred time if not provided
- Provide clear, step-by-step instructions
- Mention relevant transfer points and platforms
- Include estimated travel times

When users ask about schedules or policies:
- Provide accurate information based on available data
- Be honest if you don't know something
- Suggest contacting customer service for complex issues

Always be friendly, professional, and helpful. Use the user's preferred language when applicable.
PROMPT;
    }
}
