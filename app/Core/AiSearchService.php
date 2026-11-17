<?php
namespace App\Core;

use Exception;
use Throwable;

/**
 * Parses natural language search queries with Groq or Google Gemini.
 *
 * The provider is selected through AI_PROVIDER. Existing /search is handled by
 * PublicSearchController::index(); this service only turns a query into
 * structured parameters and generates URL slugs.
 */
class AiSearchService
{
    private const GEMINI_MODEL = 'gemini-2.5-flash';

    private string $provider;
    private string $apiKey;
    private string $groqApiKey;
    private string $groqModel;

    /**
     * Store the selected provider and its credentials.
     *
     * @throws Exception When the selected provider has no API key.
     */
    public function __construct(string $apiKey)
    {
        $this->provider = 'gemini';
        if (isset($_ENV['AI_PROVIDER'])) {
            $provider = strtolower(trim((string) $_ENV['AI_PROVIDER']));
            if ($provider !== '') {
                $this->provider = $provider;
            }
        }

        $this->apiKey = $apiKey;

        $this->groqApiKey = '';
        if (isset($_ENV['GROQ_API_KEY'])) {
            $this->groqApiKey = (string) $_ENV['GROQ_API_KEY'];
        }

        $this->groqModel = '';
        if (isset($_ENV['GROQ_MODEL'])) {
            $this->groqModel = (string) $_ENV['GROQ_MODEL'];
        }

        if ($this->provider === 'groq') {
            if ($this->groqApiKey === '') {
                throw new Exception('GROQ_API_KEY is not set.');
            }
        } else {
            if ($this->apiKey === '') {
                throw new Exception('GEMINI_API_KEY is not set.');
            }
        }
    }

    /**
     * Parse a user query into a validated search parameter array.
     */
    public function parseQuery(string $userQuery): ?array
    {
        $query = trim($userQuery);
        if ($query === '') {
            return null;
        }

        if ($this->provider === 'groq') {
            return $this->parseWithGroq($query);
        } else {
            return $this->parseWithGemini($query);
        }
    }

    /**
     * Parse a query through the OpenAI-compatible Groq chat API.
     */
    private function parseWithGroq(string $query): ?array
    {
        $payload = [
            'model' => $this->groqModel,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => $query,
                ],
            ],
            'response_format' => [
                'type' => 'json_object',
            ],
            'temperature' => 0.1,
        ];

        $body = json_encode($payload);
        if (!is_string($body)) {
            return null;
        }

        try {
            $ch = curl_init();
            if ($ch === false) {
                return null;
            }

            curl_setopt($ch, CURLOPT_URL, 'https://api.groq.com/openai/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->groqApiKey,
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            $response = curl_exec($ch);
            $curlError = curl_errno($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlError !== 0 || !is_string($response)) {
                return null;
            }
            if ($statusCode !== 200) {
                return null;
            }

            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                return null;
            }

            $text = '';
            if (isset($decoded['choices'][0]['message']['content'])) {
                $text = (string) $decoded['choices'][0]['message']['content'];
            }
            if ($text === '') {
                return null;
            }

            $parsed = json_decode($text, true);
            if (!is_array($parsed)) {
                return null;
            }

            return $this->validateResponse($parsed);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Parse a query through the Google Gemini generateContent API.
     */
    private function parseWithGemini(string $query): ?array
    {
        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $this->systemPrompt()],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $query],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.1,
                'maxOutputTokens' => 300,
            ],
        ];

        $body = json_encode($payload);
        if (!is_string($body)) {
            return null;
        }

        try {
            $ch = curl_init();
            if ($ch === false) {
                return null;
            }

            $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
                . self::GEMINI_MODEL
                . ':generateContent';

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey,
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            $response = curl_exec($ch);
            $curlError = curl_errno($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlError !== 0 || !is_string($response)) {
                return null;
            }
            if ($statusCode !== 200) {
                return null;
            }

            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                return null;
            }

            $text = '';
            if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                $text = (string) $decoded['candidates'][0]['content']['parts'][0]['text'];
            }
            if ($text === '') {
                return null;
            }

            $parsed = json_decode($text, true);
            if (!is_array($parsed)) {
                return null;
            }

            return $this->validateResponse($parsed);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Return the shared search query parsing prompt.
     */
    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a search query parser for GudangPC, an Indonesian PC parts price comparison site.
Analyze the user's natural language query (usually in Indonesian) and extract search parameters.
You MUST return ONLY a valid JSON object with these keys:

"category_slug": string or null. Must be one of: "processor", "motherboard", "graphics-card", "memory", "storage", "power-supply", "casing", "monitor", "keyboard", "mouse". Use null if unclear.

"keywords": array of strings. Use only catalog-friendly terms that are likely to appear in product names, model numbers, technical specs, or search tags. Translate Indonesian part names to their standard terms: "kartu grafis" / "VGA" -> "GPU", "prosesor" -> "CPU", "memori" -> "RAM", "penyimpanan" -> "SSD", "casing" -> "case", "pendingin" -> "cooler", "layar" -> "monitor". Do NOT include generic use-case words such as "gaming", "video editing", "editing", "kerja", "kantor", "desain", "murah", "terbaik", "bagus", or game titles unless they are also product names. If the query only describes a category or use case, return an empty keywords array.

"sort": string. One of: "price_asc", "price_desc", "newest", "popular".

"max_price": integer or null. Maximum price in IDR if user mentions a budget.

"min_price": integer or null. Minimum price in IDR if user mentions it.
Rules:

Only include a keyword if it is a product name, brand, model number, or technical spec.

Never include use-case phrases like "untuk video editing", "buat gaming", or "untuk kerja".

"murah" / "budget" / "di bawah X" -> sort "price_asc" and set max_price if a number is mentioned.

"mahal" / "high-end" / "terbaik" -> sort "price_desc".

"baru" / "terbaru" -> sort "newest".

If unsure, default sort to "popular".

Never invent categories outside the allowed list.

Examples:
Input: "GPU murah buat main valorant"
Output: {"category_slug": "graphics-card", "keywords": ["GPU"], "sort": "price_asc", "max_price": null, "min_price": null}

Input: "kartu grafis untuk video editing"
Output: {"category_slug": "graphics-card", "keywords": ["GPU"], "sort": "popular", "max_price": null, "min_price": null}

Input: "RAM 16GB di bawah 1 juta"
Output: {"category_slug": "memory", "keywords": ["RAM", "16GB"], "sort": "price_asc", "max_price": 1000000, "min_price": null}

Input: "monitor 144hz terbaik"
Output: {"category_slug": "monitor", "keywords": ["monitor", "144Hz"], "sort": "price_desc", "max_price": null, "min_price": null}
PROMPT;
    }

    /**
     * Turn free text into a lowercase dash-separated slug.
     */
    public function generateSlug(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        if (!is_string($slug)) {
            $slug = '';
        }

        return trim($slug, '-');
    }

    /**
     * Normalize a query into a lowercase single-spaced cache key.
     */
    public function normalizeQuery(string $text): string
    {
        $normalized = strtolower(trim($text));
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        if (!is_string($normalized)) {
            return '';
        }

        return trim($normalized);
    }

    /**
     * Validate the required AI response fields.
     */
    private function validateResponse(array $parsed): ?array
    {
        if (!array_key_exists('category_slug', $parsed)) {
            return null;
        }
        if (!array_key_exists('keywords', $parsed)) {
            return null;
        }
        if (!array_key_exists('sort', $parsed)) {
            return null;
        }

        $categorySlug = $parsed['category_slug'];
        if ($categorySlug !== null && !is_string($categorySlug)) {
            return null;
        }

        $keywords = $parsed['keywords'];
        if (!is_array($keywords)) {
            return null;
        }
        foreach ($keywords as $keyword) {
            if (!is_string($keyword)) {
                return null;
            }
        }

        $sort = $parsed['sort'];
        if (!is_string($sort)) {
            return null;
        }
        if (!in_array($sort, ['price_asc', 'price_desc', 'newest', 'popular'], true)) {
            return null;
        }

        $maxPrice = null;
        if (array_key_exists('max_price', $parsed)) {
            $maxPrice = $parsed['max_price'];
        }
        if ($maxPrice !== null && !is_int($maxPrice)) {
            return null;
        }

        $minPrice = null;
        if (array_key_exists('min_price', $parsed)) {
            $minPrice = $parsed['min_price'];
        }
        if ($minPrice !== null && !is_int($minPrice)) {
            return null;
        }

        return [
            'category_slug' => $categorySlug,
            'keywords' => $keywords,
            'sort' => $sort,
            'max_price' => $maxPrice,
            'min_price' => $minPrice,
        ];
    }
}
