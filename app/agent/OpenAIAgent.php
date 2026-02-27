<?php
/**
 * @desc OpenAI大模型服务提供
 */
declare(strict_types=1);
namespace app\agent;

use App\tools\Ip2AddressTools;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAI\Chat\History\FileChatHistory;
use NeuronAI\Chat\History\InMemoryChatHistory;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\HttpClientOptions;
use NeuronAI\Providers\OpenAILike;
class OpenAIAgent extends Agent
{
    /**
     * @desc provider
     */
    protected function provider(): AIProviderInterface
    {
        // Swoole 兼容性处理：强制使用 StreamHandler 避免 CurlMultiHandler 崩溃，且支持流式输出
        $handler = new StreamHandler();
        $stack = HandlerStack::create($handler);

        // 这里的apiKey和model需要替换成自己的，我使用的是硅基流动上的模型，所以直接实例化OpenAILike类，根据自己的模型做Provider修改
        // 支持的AI模型Provider：https://docs.neuron-ai.dev/the-basics/ai-provider#openai
        return new OpenAILike(
            baseUri: getenv('AI_API_URL'),
            key: getenv('AI_API_KEY'),
            model: getenv('AI_MODEL'),
            parameters: [], // Add custom params (temperature, logprobs, etc)
            strict_response: false, // Strict structured output
            httpOptions: new HttpClientOptions(
                timeout: 30,
                handler: $stack
            ),
        );
    }
    /**
     * @desc instructions系统指令
     */
    public function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                // "你是我的女朋友，请用娇羞可爱的语气回答问题",
            ],
            // steps: [
            //     "Get the url of a YouTube video, or ask the user to provide one.",
            //     "Use the tools you have available to retrieve the transcription of the video.",
            //     "Write the summary.",
            // ],
            // output: [
            //     "Write a summary in a paragraph without using lists. Use just fluent text.",
            //     "After the summary add a list of three sentences as the three most important take away from the video.",
            // ]
        );
    }

    /**
     * @return \NeuronAI\Tools\ToolInterface[]
     */
    protected function tools(): array
    {
        return [
            Ip2AddressTools::make(), //注册tools，如果构造函数有参数，在make()中填写
        ];
    }

    // 历史聊天记录
    protected function chatHistory(): ChatHistoryInterface
    {
        // 默认使用内存存储，只在当前请求有效，ai回复后就失效了
        // return new InMemoryChatHistory(
        //     contextWindow: 50000 // 最大存储token数，默认值是50000，可以根据需要修改
        // );

        // 文件存储，可以持久化和恢复记录，需要配置目录
        return new FileChatHistory(
            directory: '/app/runtime/logs/neuron',
            key: 'USER_ID', // key用于区分不同的用户或会话记录，必须是唯一的
            contextWindow: 50000
        );

        // 数据库存储，需要配置数据库和创建表
        // return new SQLChatHistory(
        //     thread_id: 'THREAD_ID',
        //     pdo: new \PDO("mysql:host=localhost;dbname=DB_NAME;charset=utf8mb4", "DB_USER", "DB_PASS"),
        //     table: 'chat_hisotry',
        //     contextWindow: 50000
        // );

        // ORM模型存储，需要创建模型和表
        // return new EloquentChatHistory(
        //     thread_id: 'THREAD_ID',
        //     modelClass: ChatMessage::class,
        //     contextWindow: 100000
        // );
    }
}