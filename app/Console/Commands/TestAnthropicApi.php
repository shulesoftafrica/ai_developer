<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class TestAnthropicApi extends Command
{
    protected $signature = 'kudos:test-anthropic';
    protected $description = 'Test Anthropic API connectivity and authentication';

    public function handle()
    {
        $this->info('🧪 Testing Anthropic API Connection');
        $this->newLine();

        $apiKey = config('app.llm.anthropic_api_key');
        $model = config('app.llm.model');

        $this->line("API Key: " . substr($apiKey, 0, 20) . "...");
        $this->line("Model: {$model}");
        $this->newLine();

        $client = new Client([
            'base_uri' => 'https://api.anthropic.com/',
            'timeout' => 30,
        ]);

        try {
            $this->line('Testing API connection...');
            
            $response = $client->post('v1/messages', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ],
                'json' => [
                    'model' => $model,
                    'max_tokens' => 10,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Hello']
                    ]
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            $this->line("<fg=green>✅ Success!</fg=green>");
            $this->line("Status Code: {$statusCode}");
            $this->line("Response: " . substr($body, 0, 200) . "...");

            if (isset($data['content'][0]['text'])) {
                $this->line("AI Response: " . $data['content'][0]['text']);
            }

        } catch (RequestException $e) {
            $this->line("<fg=red>❌ Request Failed</fg=red>");
            $this->line("Error: " . $e->getMessage());
            
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $body = $e->getResponse()->getBody()->getContents();
                $this->line("Status Code: {$statusCode}");
                $this->line("Response: " . $body);
            }
            
            return 1;
        } catch (\Exception $e) {
            $this->line("<fg=red>❌ General Error</fg=red>");
            $this->line("Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}