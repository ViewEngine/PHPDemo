<?php
/**
 * ViewEngine REST API Demo (PHP)
 * Demonstrates using the MCP endpoints with an API key
 *
 * Usage:
 *   php viewengine-demo.php <api-key>
 *   OR
 *   php viewengine-demo.php (you'll be prompted for the API key)
 */

const API_BASE_URL = 'https://www.viewengine.io';
// const API_BASE_URL = 'http://localhost:5072'; // For local development

class ViewEngineDemo
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function run(): void
    {
        echo "╔══════════════════════════════════════════════════════════╗\n";
        echo "║          ViewEngine REST API Demo (PHP)                 ║\n";
        echo "║  Demonstrates using the MCP endpoints with an API key   ║\n";
        echo "╚══════════════════════════════════════════════════════════╝\n\n";

        try {
            $this->runDemo();
        } catch (Exception $e) {
            echo "❌ Error: {$e->getMessage()}\n\n";
            echo "Stack trace:\n";
            echo $e->getTraceAsString() . "\n";
        }

        echo "\nPress Enter to exit...";
        fgets(STDIN);
    }

    private function runDemo(): void
    {
        echo "🔍 Step 1: Discovering available MCP tools...\n\n";

        $tools = $this->getMcpTools();
        if ($tools && count($tools) > 0) {
            echo "✅ Found " . count($tools) . " available tools:\n";
            foreach ($tools as $tool) {
                echo "   • {$tool->name}: {$tool->description}\n";
            }
        } else {
            echo "⚠️  No tools found or API not responding\n";
        }

        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        // Get URL to retrieve
        echo "Enter a URL to retrieve (or press Enter for example.com): ";
        $url = trim(fgets(STDIN));
        if (empty($url)) {
            $url = 'https://example.com';
        }

        echo "\nForce fresh retrieval? (y/n, default: n - use cache if available): ";
        $forceRefreshInput = strtolower(trim(fgets(STDIN)));
        $forceRefresh = $forceRefreshInput === 'y';

        echo "\nProcessing mode (private/community, default: private): ";
        $modeInput = strtolower(trim(fgets(STDIN)));
        $mode = $modeInput === 'community' ? 'community' : 'private';

        echo "\n🌐 Step 2: Submitting retrieval request for $url...\n";
        if ($forceRefresh) {
            echo "   (Forcing fresh retrieval, bypassing cache)\n";
        } else {
            echo "   (Will use cached results if available)\n";
        }
        echo "   Mode: $mode\n\n";

        $retrieveResponse = $this->submitRetrieveRequest($url, $forceRefresh, $mode);
        if (!$retrieveResponse) {
            echo "❌ Failed to submit retrieval request\n";
            return;
        }

        echo "✅ Request submitted successfully!\n";
        echo "   Request ID: {$retrieveResponse->requestId}\n";
        echo "   Status: {$retrieveResponse->status}\n";
        echo "   Estimated wait: {$retrieveResponse->estimatedWaitTimeSeconds}s\n";

        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        echo "⏳ Step 3: Polling for results (this may take a while)...\n\n";

        $result = $this->pollForResults($retrieveResponse->requestId);
        if (!$result) {
            echo "❌ Failed to get results\n";
            return;
        }

        echo "✅ Retrieval completed!\n";
        echo "   Status: {$result->status}\n";
        echo "   URL: {$result->url}\n";
        echo "   Completed at: {$result->completedAt}\n";

        if ($result->content) {
            echo "\n📄 Content available:\n";
            echo "   Page Data URL: {$result->content->pageDataUrl}\n";
            echo "   Content Hash: {$result->content->contentHash}\n";

            if ($result->content->artifacts && count((array)$result->content->artifacts) > 0) {
                echo "   Artifacts: " . implode(', ', array_keys((array)$result->content->artifacts)) . "\n";
            }

            if ($result->content->metrics && count((array)$result->content->metrics) > 0) {
                echo "   Metrics: " . implode(', ', array_keys((array)$result->content->metrics)) . "\n";
            }

            echo "\nDownload page content? (y/n): ";
            $download = strtolower(trim(fgets(STDIN)));
            if ($download === 'y') {
                $this->downloadPageData($result->content->pageDataUrl);
            }
        }

        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        echo "✅ Demo completed successfully!\n";
    }

    private function getMcpTools(): ?array
    {
        try {
            $ch = curl_init(API_BASE_URL . '/v1/mcp/tools');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $this->apiKey
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                echo "Error getting tools: HTTP $httpCode\n";
                return null;
            }

            $result = json_decode($response);
            return $result->tools ?? null;
        } catch (Exception $e) {
            echo "Error getting tools: {$e->getMessage()}\n";
            return null;
        }
    }

    private function submitRetrieveRequest(string $url, bool $forceRefresh, string $mode): ?object
    {
        try {
            $requestBody = json_encode([
                'url' => $url,
                'timeoutSeconds' => 60,
                'forceRefresh' => $forceRefresh,
                'mode' => $mode
            ]);

            $ch = curl_init(API_BASE_URL . '/v1/mcp/retrieve');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $requestBody,
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $this->apiKey,
                    'Content-Type: application/json'
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                echo "API Error ($httpCode): $response\n";
                return null;
            }

            return json_decode($response);
        } catch (Exception $e) {
            echo "Error submitting retrieval request: {$e->getMessage()}\n";
            return null;
        }
    }

    private function pollForResults(string $requestId, int $maxAttempts = 60): ?object
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $ch = curl_init(API_BASE_URL . "/v1/mcp/retrieve/$requestId");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'X-API-Key: ' . $this->apiKey
                    ]
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode !== 200) {
                    echo "   [$attempt/$maxAttempts] HTTP Error: $httpCode\n";
                    sleep(2);
                    continue;
                }

                $result = json_decode($response);
                if (!$result) {
                    echo "   [$attempt/$maxAttempts] Failed to decode response\n";
                    sleep(2);
                    continue;
                }

                echo "   [$attempt/$maxAttempts] Status: {$result->status} - {$result->message}\n";

                if ($result->status === 'complete') {
                    return $result;
                }

                if ($result->status === 'failed' || $result->status === 'canceled') {
                    echo "   Error: {$result->error}\n";
                    return $result;
                }

                sleep(2);
            } catch (Exception $e) {
                echo "   [$attempt/$maxAttempts] Error: {$e->getMessage()}\n";
                sleep(2);
            }
        }

        echo "⚠️  Timeout: Maximum polling attempts reached\n";
        return null;
    }

    private function downloadPageData(string $pageDataUrl): void
    {
        try {
            echo "\n⬇️  Downloading page content...\n";

            $ch = curl_init($pageDataUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $this->apiKey
                ]
            ]);

            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                echo "Error downloading page data: HTTP $httpCode\n";
                return;
            }

            $pageData = json_decode($content);
            $prettyJson = json_encode($pageData, JSON_PRETTY_PRINT);

            echo "\n📄 Page Content (first 500 chars):\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

            if (strlen($prettyJson) > 500) {
                echo substr($prettyJson, 0, 500) . "...\n";
            } else {
                echo $prettyJson . "\n";
            }

            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        } catch (Exception $e) {
            echo "Error downloading page data: {$e->getMessage()}\n";
        }
    }
}

// Main execution
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

$apiKey = null;

if (isset($argv[1])) {
    $apiKey = $argv[1];
} else {
    echo "Enter your API key: ";
    $apiKey = trim(fgets(STDIN));
}

if (empty($apiKey)) {
    echo "❌ Error: API key is required\n\n";
    echo "Usage:\n";
    echo "  php viewengine-demo.php <api-key>\n";
    echo "  OR\n";
    echo "  php viewengine-demo.php   (you'll be prompted for the API key)\n";
    exit(1);
}

$demo = new ViewEngineDemo($apiKey);
$demo->run();
