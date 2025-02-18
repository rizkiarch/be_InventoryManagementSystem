<?php

namespace App\Services;

use App\Models\GeminiResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected $apiKey;
    protected $model;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key');
        $this->model = config('gemini.model');
        $this->baseUrl = config('gemini.base_url');
    }

    public function generateAndSaveContent($prompt)
    {
        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/{$this->model}:generateContent", [
                'contents' => [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ],
                'safety_settings' => [
                    [
                        'category' => 'HARM_CATEGORY_HARASSMENT',
                        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                    ]
                ]
            ]);

            $statusCode = $response->status();
            $responseBody = $response->json();

            if ($statusCode >= 200 && $statusCode < 300 && isset($responseBody['candidates'][0]['content']['parts'][0]['text'])) {
                $generatedText = $responseBody['candidates'][0]['content']['parts'][0]['text'];

                return GeminiResult::create([
                    'prompt' => $prompt,
                    'response' => $generatedText,
                    'raw_response' => $responseBody,
                    'status' => 'completed'
                ]);
            }

            Log::error('Gemini API Error:', [
                'status' => $statusCode,
                'body' => $responseBody
            ]);

            return GeminiResult::create([
                'prompt' => $prompt,
                'response' => null,
                'raw_response' => $responseBody,
                'status' => 'failed'
            ]);
        } catch (\Exception $e) {
            Log::error('Gemini Service Error:', ['message' => $e->getMessage()]);

            return GeminiResult::create([
                'prompt' => $prompt,
                'response' => null,
                'raw_response' => ['error' => $e->getMessage()],
                'status' => 'error'
            ]);
        }
    }
}
