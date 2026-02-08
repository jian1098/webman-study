<?php

namespace app\controller;

use support\Request;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\ModelCatalog;
use Symfony\AI\Platform\Bridge\Generic\PlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\Message;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\AI\Platform\Message\MessageBag;

// 不同的模型要安装对应模型的扩展，并引用对应的PlatformFactory，自定义模型用Ollama\PlatformFactory,通用平台用Generic\PlatformFactory
// 支持的模型列表：https://symfony.com/doc/current/ai/components/platform.html#custom-models
// 平台对应扩展 https://github.com/symfony/ai/blob/main/src/platform/README.md
class SymfonyAIController
{
    protected $api;
    protected $apiKey;
    protected $model;
    protected $platform;
    protected $systemPrompt;

    public function __construct()
    {
        $this->api = getenv('AI_API_URL'); // https://api.siliconflow.cn
        $this->apiKey = getenv('AI_API_KEY'); // sk-*******
        $this->model = getenv('AI_MODEL'); // Qwen/Qwen2.5-Coder-7B-Instruct
        $this->systemPrompt = '你是我的女朋友，请用娇羞可爱的语气回答问题';

        // 对接主流AI平台,例如：OpenAI、Gemini、Claude、Qwen等，需要用到对应平台的PlatformFactory
        // $this->platform = OpenAiPlatformFactory::create(
        //     $this->apiKey,
        //     HttpClient::create()
        // );

        // 对接聚合AI平台,例如：硅基流动、阿里云百炼等，需要用到Generic\PlatformFactory
        $modelCatalog = new ModelCatalog([
            $this->model => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
        ]);
        $this->platform = PlatformFactory::create(
            $this->api,
            $this->apiKey,
            $this->getClient(),
            $modelCatalog,
        );
    }

    // 聊天页面
    public function chat(Request $request)
    {
        return view('index/chat2');
    }

    // 聊天接口
    public function index(Request $request)
    {
        $connection = $request->connection;
        $message = $request->input('message');
        $stream = $request->input('stream');
        if ($message == '') {
            return json(['code' => 500, 'msg' => '请输入内容']);
        }
        return $stream ? $this->chatWithStream($message) : $this->chatWithoutStream($message);
    }

    // 非流式返回
    public function chatWithoutStream($message)
    {
        // 构建消息
        $messages = new MessageBag(
            // 系统提示
            Message::forSystem($this->systemPrompt),
            // 历史消息
            // Message::ofUser('早安'),
            // Message::ofAssistant('早安，有什么问题吗？'),
            // 用户提问
            Message::ofUser($message),
        );

        try {
            // 调用模型（同步）
            $response = $this->platform->invoke(
                $this->model,
                $messages,
                [
                    'temperature' => 0.7,
                    'max_output_tokens' => 2048,
                ]
            );
            // var_dump($response);
            return json([
                'reply' => $response->getResult()->getContent(),
                'model' => $this->model,
                // Generic平台目前没有实现TokenUsageExtractor，所以getMetadata()为空
                // 可以从原始响应中获取
                'usage' => $response->getRawResult()->getData()['usage'] ?? [],
            ]);
        } catch (\Exception $e) {
            return json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    // 流式返回
    public function chatWithStream($message)
    {
        // 构建消息
        $messages = new MessageBag(
            // 系统提示
            Message::forSystem($this->systemPrompt),
            // 历史消息
            // Message::ofUser('早安'),
            // Message::ofAssistant('早安，有什么问题吗？'),
            // 用户提问
            Message::ofUser($message),
        );

        try {
            // 调用模型（同步）
            $response = $this->platform->invoke(
                $this->model,
                $messages,
                [
                    'temperature' => 0.7,
                ]
            );
            return json([
                'reply' => $response->getResult()->getContent(),
            ]);
        } catch (\Exception $e) {
            return json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    // Initialize platform with MockHttpClient to bypass Swoole transport issues
    // We will perform the actual request using a simple, Swoole-safe CURL call
    public function getClient()
    {
        return new MockHttpClient(function ($method, $url, $options) {
            $ch = curl_init($url);
            curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, \CURLOPT_HTTPHEADER, $options['headers']);
            curl_setopt($ch, \CURLOPT_POSTFIELDS, $options['body']);
            curl_setopt($ch, \CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, false); // For debugging, usually true

            $content = curl_exec($ch);
            if (curl_errno($ch)) {
                return new MockResponse(json_encode(['error' => curl_error($ch)]), ['http_code' => 500]);
            }
            $statusCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);
            curl_close($ch);

            return new MockResponse($content, ['http_code' => $statusCode]);
        });
    }

}
