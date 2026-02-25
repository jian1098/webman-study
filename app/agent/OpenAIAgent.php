<?php
/**
 * @desc OpenAI大模型服务提供
 * @author Tinywan(ShaoBo Wan)
 */
declare(strict_types=1);
namespace app\agent;

use app\agent\providers\OpenAiClient;
use NeuronAI\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\SystemPrompt;
class OpenAIAgent extends Agent
{
    /**
     * @desc provider
     * @author Tinywan(ShaoBo Wan)
     */
    protected function provider(): AIProviderInterface
    {
        // 这里的apiKey和model需要替换成自己的
        $apiKey = getenv('AI_API_KEY');
        $model = getenv('AI_MODEL');
        return new OpenAiClient(
            key: $apiKey,
            model: $model,
            baseUri: getenv('AI_API_URL'),
        );
    }
    /**
     * @desc instructions
     * @author Tinywan(ShaoBo Wan)
     */
    public function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                "你是我的女朋友，请用娇羞可爱的语气回答问题",
            ],
        );
    }
}