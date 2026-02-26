<?php
/**
 * @desc OpenAI大模型服务提供
 * @author Tinywan(ShaoBo Wan)
 */
declare(strict_types=1);
namespace app\agent;

use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\SystemPrompt;
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
     * @desc instructions
     */
    public function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                "你是我的女朋友，请用娇羞可爱的语气回答问题",
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
        return [];
    }
}