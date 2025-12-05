<?php

namespace YakimaFinds\Scrapers\Intelligent;

class SegmindAPI
{
    private $apiKey;
    private $baseUrl = 'https://api.segmind.com/v1/';
    
    // Available models - exact endpoints from user
    const MODEL_LLAVA = 'llava-v1.6';
    const MODEL_CLAUDE_SONNET = 'kimi-k2-instruct-0905';  // Claude unavailable, using Kimi K2
    const MODEL_CLAUDE_35_SONNET = 'kimi-k2-instruct-0905';  // Claude unavailable, using Kimi K2
    
    public function __construct($apiKey = null)
    {
        $this->apiKey = $apiKey ?: getenv('SEGMIND_API_KEY');
        if (!$this->apiKey) {
            throw new \Exception('Segmind API key not configured');
        }
    }
    
    /**
     * Analyze a webpage with visual understanding (LLaVA)
     */
    public function analyzeWebpageVisual($imageData, $prompt)
    {
        $endpoint = $this->baseUrl . self::MODEL_LLAVA;
        
        // LLaVa uses 'images' not 'image' according to docs
        $data = [
            'images' => base64_encode($imageData),
            'prompt' => $prompt
        ];
        
        return $this->makeRequest($endpoint, $data);
    }
    
    /**
     * Analyze HTML content with Claude Sonnet
     */
    public function analyzeHTMLContent($htmlContent, $prompt)
    {
        // Use Claude 3.5 Sonnet for text analysis (3.7 requires multimodal format)
        $endpoint = $this->baseUrl . self::MODEL_CLAUDE_35_SONNET;
        
        $data = [
            'instruction' => 'You are an expert at analyzing HTML content to find and extract event information. Return your analysis as valid JSON.',
            'temperature' => 0.3,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt . "\n\nHTML Content:\n" . substr($htmlContent, 0, 30000) // Limit content size
                ]
            ]
        ];
        
        return $this->makeRequest($endpoint, $data);
    }
    
    /**
     * Generate extraction method with Claude Opus
     */
    public function generateExtractionMethod($htmlContent, $foundEvents, $url)
    {
        // Use Claude 3.5 Sonnet for method generation
        $endpoint = $this->baseUrl . self::MODEL_CLAUDE_35_SONNET;
        
        $prompt = "Based on the following HTML content and successfully extracted events, generate a reusable extraction method.

URL: {$url}
Found Events: " . json_encode($foundEvents, JSON_PRETTY_PRINT) . "

Create a JSON extraction method with:
1. CSS selectors or XPath expressions to find events
2. Field mappings (title, date, location, description, etc.)
3. Date parsing patterns
4. URL construction rules if events link to detail pages
5. Any special processing rules

Return ONLY valid JSON with the extraction method.";

        $data = [
            'instruction' => 'You are an expert at creating reusable web scraping methods. Always return valid JSON.',
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt . "\n\nHTML Sample:\n" . substr($htmlContent, 0, 20000)
                ]
            ]
        ];
        
        return $this->makeRequest($endpoint, $data);
    }
    
    /**
     * Analyze page structure to find event patterns
     */
    public function findEventPatterns($htmlContent, $url)
    {
        // Use Claude 3.5 Sonnet for pattern finding
        $endpoint = $this->baseUrl . self::MODEL_CLAUDE_35_SONNET;
        
        $prompt = "Analyze this webpage to find events or event information. Be very thorough and look for ANY type of event content.

URL: {$url}

Look for:
1. Event listings with dates, times, titles (any format)
2. Calendar views or event calendars
3. News about upcoming events
4. Meeting schedules, performances, workshops
5. Festival announcements, community gatherings
6. Business hours that mention special events
7. \"Upcoming\", \"Events\", \"Calendar\", \"What's On\" sections
8. Event-related data in JSON-LD, microdata, or structured formats
9. Links to event detail pages or external calendars
10. Even minimal event mentions like \"Next meeting: June 15\"

IMPORTANT: If you find ANY event-related content, set has_events to true. Extract whatever event information is available, even if incomplete.

For each event found, include:
- title (even if generic like \"Meeting\")
- date/time (any format found)
- location (if mentioned)
- description (any details available)
- link (if clickable)

Return a JSON response with:
{
    \"has_events\": true/false,
    \"event_type\": \"list\"|\"calendar\"|\"links\"|\"news\"|\"schedule\"|\"none\",
    \"events_found\": [
        {
            \"title\": \"...\",
            \"date\": \"...\",
            \"time\": \"...\",
            \"location\": \"...\",
            \"description\": \"...\",
            \"link\": \"...\"
        }
    ],
    \"event_links\": [...],
    \"selectors\": {
        \"event_container\": \"...\",
        \"title\": \"...\",
        \"date\": \"...\",
        \"location\": \"...\",
        \"link\": \"...\"
    },
    \"patterns\": {
        \"date_format\": \"...\",
        \"url_pattern\": \"...\"
    }
}";

        $data = [
            'instruction' => 'You are an expert at finding event information in HTML. Always return valid JSON.',
            'temperature' => 0.3,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt . "\n\nHTML Content:\n" . substr($htmlContent, 0, 30000)
                ]
            ]
        ];
        
        return $this->makeRequest($endpoint, $data);
    }
    
    /**
     * Make API request
     */
    private function makeRequest($endpoint, $data)
    {
        $ch = curl_init($endpoint);
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception('CURL error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            error_log('Segmind API HTTP Error: ' . $httpCode);
            error_log('Response: ' . substr($response, 0, 500));
            throw new \Exception('API error: HTTP ' . $httpCode . ' - ' . substr($response, 0, 200));
        }
        
        // Log raw response for debugging
        error_log('Segmind API Response (first 500 chars): ' . substr($response, 0, 500));
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            error_log('Raw response: ' . substr($response, 0, 1000));
            throw new \Exception('Invalid JSON response from API: ' . json_last_error_msg());
        }
        
        return $result;
    }
    
    /**
     * Extract text content from response
     */
    public function extractContent($response)
    {
        // Log response structure for debugging
        error_log('Segmind API response structure: ' . json_encode(array_keys($response)));
        
        // Handle Anthropic Claude format (what Segmind returns)
        if (isset($response['content'][0]['text'])) {
            return $response['content'][0]['text'];
        }
        // Handle different response formats from different models
        elseif (isset($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        } elseif (isset($response['generated_text'])) {
            return $response['generated_text'];
        } elseif (isset($response['text'])) {
            return $response['text'];
        } elseif (isset($response['output'])) {
            return $response['output'];
        } elseif (isset($response['response'])) {
            return $response['response'];
        }
        
        // If no known format, log the whole response
        error_log('Unknown response format: ' . json_encode($response));
        return null;
    }
    
    /**
     * Parse JSON from LLM response
     */
    public function parseJSONResponse($response)
    {
        $content = $this->extractContent($response);
        if (!$content) {
            return null;
        }
        
        // Try to extract JSON from the response
        // LLMs sometimes wrap JSON in markdown code blocks
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
            $content = $matches[1];
        }
        
        // Try to find JSON object or array
        if (preg_match('/(\{.*\}|\[.*\])/s', $content, $matches)) {
            $content = $matches[1];
        }
        
        $result = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }
        
        // If JSON parsing fails, try to clean up common issues
        $content = str_replace(["\n", "\r", "\t"], ' ', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        
        $result = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }
        
        return null;
    }
}