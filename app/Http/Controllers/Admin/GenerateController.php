<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GenerateController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:10000'],
        ]);

        $apiKey = config('services.ai.api_key');
        $apiUrl = config('services.ai.api_url');
        $model  = config('services.ai.model');

        if (! $apiKey || ! $apiUrl || ! $model) {
            return response()->json([
                'error' => 'AI generation is not configured. Set AI_API_KEY, AI_API_URL, and AI_MODEL in .env.',
            ], 400);
        }

        // Get the prompt from settings, or use the default
        $prompt = Setting::get('ai_generate_prompt');
        if (! $prompt) {
            $prompt = self::defaultPrompt();
        }

        // Append the user's input text
        $prompt = str_replace('{{INPUT_TEXT}}', $validated['text'], $prompt);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ])
                ->timeout(60)
                ->post(rtrim($apiUrl, '/') . '/chat/completions', [
                    'model'       => $model,
                    'messages'    => [
                        ['role' => 'system', 'content' => 'You are a social media content generator. You MUST respond with ONLY a valid JSON object, no markdown, no explanations, no code fences.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                    'max_tokens'  => 4096,
                ]);

            if (! $response->successful()) {
                return response()->json([
                    'error' => 'AI API error: ' . ($response->json('error.message') ?? $response->status()),
                ], 502);
            }

            $rawContent = $response->json('choices.0.message.content');

            if (empty($rawContent)) {
                return response()->json([
                    'error' => 'AI returned an empty response.',
                ], 502);
            }

            // Try to parse the JSON from the AI response
            $parsed = self::extractJson(trim($rawContent));

            if ($parsed === null) {
                // Return raw text so user can still use it manually
                return response()->json([
                    'raw_text' => trim($rawContent),
                    'error'    => 'Could not parse AI response as JSON. Raw text returned.',
                ]);
            }

            return response()->json($parsed);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Generation request failed: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Extract a JSON object from AI output, even if wrapped in markdown code fences.
     */
    private static function extractJson(string $content): ?array
    {
        // Strip code fences if present (case-insensitive, so ```JSON and ```json both work).
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/is', $content, $m)) {
            $content = $m[1];
        }

        $data = json_decode($content, true);

        if (is_array($data)) {
            return $data;
        }

        // Fallback: the model may wrap the JSON object in prose/explanations.
        // Extract the outermost JSON object and try to decode it.
        $start = strpos($content, '{');
        $end   = strrpos($content, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $candidate = substr($content, $start, $end - $start + 1);
            $data = json_decode($candidate, true);

            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Default prompt used when the admin hasn't set a custom one.
     * Uses {{INPUT_TEXT}} as a placeholder for the user's press release text.
     */
    public static function defaultPrompt(): string
    {
        return <<<'PROMPT'
**Role and Objective**
You are an expert Social Media Manager and PR Copywriter. Your task is to analyze a provided Press Release or News article and transform it into a short, highly engaging post suitable for a broad audience.

**Tone and Style**
Your writing should be **enthusiastic and slightly sensationalist**. Use a captivating, hype-driven tone to grab the reader's attention immediately. Make the news sound exciting, groundbreaking, and highly relevant, while still keeping the core message of the original text.

**Content Requirements**
For the Press Release or News article provided, you must generate:
1. **Title / Headline:** A catchy, click-worthy headline.
2. **Post Body:** 1 to 3 short paragraphs summarizing the news and highlighting the most exciting elements.
3. **Tags:** 3 to 8 relevant tags (lowercase, short phrases) for categorizing the post.

**Language Requirements**
You must provide the complete output in all three of the following languages:
- "en_US" — English (US)
- "pt_BR" — Portuguese (Brazil)
- "es_MX" — Spanish (Mexico)

**Strict Privacy Constraints**
- **NO CONTACT DATA:** You must strictly filter out and completely omit any personal data, email addresses, phone numbers, or contact information related to the PR contact, the author, or the PR company that shared the news.
- Keep the focus solely on the product, event, or announcement itself.

**CRITICAL OUTPUT FORMAT**
You MUST respond with ONLY a valid JSON object. No markdown, no code fences, no explanations. The JSON structure must be exactly:

{
    "headlines": {
        "en_US": "English headline here",
        "pt_BR": "Portuguese headline here",
        "es_MX": "Spanish headline here"
    },
    "descriptions": {
        "en_US": "English description paragraphs here",
        "pt_BR": "Portuguese description paragraphs here",
        "es_MX": "Spanish description paragraphs here"
    },
    "tags": "tag1, tag2, tag3, tag4"
}

---
**Input Data:**
Here is the Press Release/News to process:
{{INPUT_TEXT}}
PROMPT;
    }
}
