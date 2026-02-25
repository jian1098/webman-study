<?php

namespace app\controller;

use app\agent\OpenAIAgent;
use NeuronAI\Chat\Messages\UserMessage;
use support\Request;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Chunk;
use Workerman\Timer;

/**
 * AI开发
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
            return json(['code' => 500, 'msg' => '请输入内容']);
        }
        return $stream ? $this->chatWithStream($connection, $message) : $this->chatWithoutStream($connection, $message);
    }


    // 聊天页面
    public function chat(Request $request)
    {
        return view('index/neuron_chat');
    }

    public function chatWithStream($connection, $message)
    {
        $id = Timer::add(0.001, function () use ($connection, &$id, $message) {
            Timer::del($id);
            if ($connection->getStatus() !== TcpConnection::STATUS_ESTABLISHED) {
                return;
            }

            try {
                $stream = $this->agent->make()->stream(
                    new UserMessage($message)
                );

                foreach ($stream as $chunk) {
                    // Neuron AI yields JSON strings for usage and regular strings for content
                    $content = $chunk;
                    if (!is_string($chunk)) {
                        $content = $chunk->getContent();
                    }

                    if (empty($content)) {
                        continue;
                    }

                    // Check if it's usage data (JSON)
                    $decoded = json_decode((string) $content, true);
                    if (is_array($decoded) && isset($decoded['usage'])) {
                        $connection->send(new Chunk(json_encode([
                            'event' => 'usage',
                            'usage' => $decoded['usage'],
                        ], JSON_UNESCAPED_UNICODE) . "\n"));
                        continue;
                    }

                    // Otherwise it's regular content
                    $connection->send(new Chunk(json_encode([
                        'event' => 'message',
                        'reply' => $content,
                        'model' => getenv('AI_MODEL'),
                    ], JSON_UNESCAPED_UNICODE) . "\n"));
                }

                // Send completion event
                $connection->send(new Chunk(''));

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

    public function chatWithoutStream($connection, $message)
    {
        try {
            $reply = $this->agent->make()->chat(new UserMessage($message));
            $connection->send(json_encode([
                'reply' => $reply->getContent(),
                'model' => getenv('AI_MODEL'),
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            $connection->send(json_encode([
                'reply' => $e->getFile() . $e->getLine() . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }
}
