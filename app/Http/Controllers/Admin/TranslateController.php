<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TranslateController extends Controller
{
    public function translate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text'          => ['required', 'string', 'max:5000'],
            'target_locale' => ['required', 'string', 'in:en-US,es_MX,pt_BR'],
        ]);

        $apiKey = config('services.ai.api_key');
        $apiUrl = config('services.ai.api_url');
        $model  = config('services.ai.model');

        if (! $apiKey || ! $apiUrl || ! $model) {
            return response()->json([
                'error' => 'AI translation is not configured. Set AI_API_KEY, AI_API_URL, and AI_MODEL in .env.',
            ], 400);
        }

        $localeNames = [
            'en-US' => 'English',
            'es_MX' => 'Spanish (Mexican)',
            'pt_BR' => 'Brazilian Portuguese',
        ];

        $targetName = $localeNames[$validated['target_locale']] ?? $validated['target_locale'];

        $systemPrompt = "You are a professional translator. Translate the following text to {$targetName}. "
            . "Preserve formatting, line breaks, and any HTML tags. "
            . "Return ONLY the translated text, no explanations, no quotes, no markdown.";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ])
                ->timeout(30)
                ->post(rtrim($apiUrl, '/') . '/chat/completions', [
                    'model'       => $model,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $validated['text']],
                    ],
                    'temperature' => 0.3,
                    'max_tokens'  => 4096,
                ]);

            if (! $response->successful()) {
                return response()->json([
                    'error' => 'AI API error: ' . ($response->json('error.message') ?? $response->status()),
                ], 502);
            }

            $translated = $response->json('choices.0.message.content');

            if (empty($translated)) {
                return response()->json([
                    'error' => 'AI returned an empty response.',
                ], 502);
            }

            return response()->json([
                'translated_text' => trim($translated),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Translation request failed: ' . $e->getMessage(),
            ], 502);
        }
    }
}
