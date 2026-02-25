<?php
/**
 * @desc OpenAI大模型
 */
declare(strict_types=1);

namespace app\agent\providers;

use GuzzleHttp\Client;
use NeuronAI\Providers\HttpClientOptions;
use NeuronAI\Providers\OpenAI\OpenAI;

class OpenAIClient extends OpenAI
{
    protected string $baseUri = 'https://api.openai.com/v1';
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        protected string $key,
        protected string $model,
        protected array $parameters = [],
        protected bool $strict_response = false,
        protected ?HttpClientOptions $httpOptions = null,
        ?string $baseUri = null // 新增参数：自定义 API 地址
    ) {
        if ($baseUri !== null) {
            $this->baseUri = $baseUri;
        }

        // Swoole 兼容性处理：强制使用 StreamHandler 避免 CurlMultiHandler 崩溃，且支持流式输出
        $handler = new \GuzzleHttp\Handler\StreamHandler();
        $stack = \GuzzleHttp\HandlerStack::create($handler);

        $config = [
            'handler' => $stack,
            'base_uri' => rtrim($this->baseUri, '/') . '/',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->key,
            ]
        ];
        if ($this->httpOptions instanceof HttpClientOptions) {
            $config = $this->mergeHttpOptions($config, $this->httpOptions);
        }
        $this->client = new Client($config);
    }
}