<?php

namespace app\controller;

use app\agent\OpenAIAgent;
use app\output\PersonOutput;
use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\ContentBlocks\FileContent;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\UserMessage;
use support\Request;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Chunk;
use Workerman\Timer;

/**
 * Neuron AI开发
 * 
 * 相关文档：
 * https://docs.neuron-ai.dev/the-basics/ai-provider
 * https://github.com/neuron-core/neuron-ai
 * https://mp.weixin.qq.com/s/rtjW-13j5Di9jxdgxRpClw
 */
class NeuronAiController
{
    protected $agent;

    public function __construct()
    {
        $this->agent = new OpenAIAgent();
    }

    public function index(Request $request)
    {
        $connection = $request->connection;
        $message = $request->input('message');
        $stream = $request->input('stream');
        if ($message == '') {
            return json(['code' => 500, 'reply' => '请输入内容']);
        }
        return $stream ? $this->chatWithStream($connection, $message) : $this->chatWithoutStream($connection, $message);
    }


    // 聊天页面
    public function chat(Request $request)
    {
        return view('index/neuron_chat');
    }

    // 流式输出
    public function chatWithStream($connection, $message)
    {
        $id = Timer::add(0.01, function () use ($connection, &$id, $message) {
            if ($id !== null) {
                Timer::del($id);
            }
            if ($connection->getStatus() !== TcpConnection::STATUS_ESTABLISHED) {
                return;
            }

            try {
                $handler = $this->agent->make()->stream(
                    new UserMessage($message)
                );
                foreach ($handler as $chunk) {
                    // Neuron AI yields JSON strings for usage and regular strings for content
                    $content = $chunk;
                    if (!is_string($chunk)) {
                        $content = $chunk->content;
                    }

                    if (empty($content)) {
                        continue;
                    }

                    // Otherwise it's regular content
                    $connection->send(new Chunk(json_encode([
                        'reply' => $content,
                        'model' => getenv('AI_MODEL'),
                    ], JSON_UNESCAPED_UNICODE) . "\n"));
                }

                // 获取流结束后的最终响应对象，其中包含累计消耗
                $response = $handler->getReturn();
                $usage = $response->getUsage();
                $totalUsage = $usage ? $usage->getTotal() : 0;

                // Send completion event with total usage
                $connection->send(new Chunk(json_encode([
                    'event' => 'completed',
                    'reply' => '',
                    'usage' => $totalUsage,
                ], JSON_UNESCAPED_UNICODE) . "\n"));

                // Final empty chunk to signal end of stream
                $connection->send(new Chunk(''));
            } catch (\Exception $e) {
                $connection->send(new Chunk(json_encode([
                    'event' => 'error',
                    'reply' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE) . "\n"));
                $connection->send(new Chunk(''));
            }
        }, [], false);

        return response()->withHeaders([
            'Content-Type' => 'application/json',
            'Transfer-Encoding' => 'chunked',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    // 非流式输出
    public function chatWithoutStream($connection, $message)
    {
        try {
            // 定义文本消息
            $message = new UserMessage($message);
            // 定义图片消息内容,需要支持图片识别的大模型
            // $message->addContent(
            //     new ImageContent(
            //                 content: 'https://img-home.csdnimg.cn/images/20201124032511.png',
            //                 sourceType: SourceType::URL,
            //                 mediaType: 'image/png'
            //             )
            // );
            // 定义文件消息内容
            // $message->addContent(
            //     new FileContent(
            //         content: base64_encode(file_get_contents(__DIR__.'/invoice.pdf')),
            //         sourceType: SourceType::BASE64,
            //         mediaType: 'application/pdf'
            //     )
            // );

            // $reply = $this->agent->make()->chat($message); // 普通聊天
            $person = $this->agent->make()->structured($message, PersonOutput::class); // 结构化输出聊天
            // $usage = $person->getUsage();
            // $totalUsage = $usage ? $usage->getTotal() : 0;
            $connection->send(json_encode([
                'reply' => $person->name . '的爱好是' . $person->preference,
                'model' => getenv('AI_MODEL'),
                'usage' => 0,
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            $connection->send(json_encode([
                'reply' => $e->getFile() . $e->getLine() . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }
}
