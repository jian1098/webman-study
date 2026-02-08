<?php

namespace app\controller;

use support\Request;
use Webman\Openai\Chat;
use Workerman\Protocols\Http\Chunk;

/**
 * AI开发
 * 
 * 相关文档：https://www.workerman.net/plugin/157
 */
class AiController
{
    protected $api;
    protected $apiKey;
    protected $model;
    protected $chat;
    protected $systemPrompt;

    public function __construct()
    {
        $this->api = getenv('AI_API_URL'); // https://api.siliconflow.cn
        $this->apiKey = getenv('AI_API_KEY'); // sk-*******
        $this->model = getenv('AI_MODEL'); // Qwen/Qwen2.5-Coder-7B-Instruct
        $this->systemPrompt = '你是我的女朋友，请用娇羞可爱的语气回答问题';
        $this->chat = new Chat([
            'api' => $this->api,
            'apikey' => $this->apiKey
        ]);
    }

    public function index(Request $request)
    {
        $connection = $request->connection;
        $message = $request->input('message');
        $stream = $request->input('stream', 1);
        if ($message == '') {
            return json(['code' => 500, 'msg' => '请输入内容']);
        }
        return $stream ? $this->chatWithStream($connection, $message) : $this->chatWithoutStream($connection, $message);
    }

    // 非流式返回
    public function chatWithoutStream($connection, $content)
    {
        $this->chat->completions(
            [
                'model' => $this->model,
                'messages' => [
                    // 系统提示(role='system')
                    ['role' => 'system', 'content' => $this->systemPrompt],
                    // 历史消息-第一轮历史会话(每轮历史消息包含一组用户提问(role='user')和ai回复(role='assistant'))
                    // ['role' => 'user', 'content' => ''],
                    // ['role' => 'assistant', 'content' => ''],
                    // 历史消息-第n轮历史会话
                    // ...
                    // 用户提问(role='user')
                    ['role' => 'user', 'content' => $content],
                ],
            ],
            [
                'timeout' => 60, //可选参数,超时时间，默认600s
                'complete' => function ($result, $response) use ($connection) {
                    $connection->send(new Chunk(json_encode($result, JSON_UNESCAPED_UNICODE) . "\n"));
                    $connection->send(new Chunk(''));
                }
            ]
        );
        return response()->withHeaders([
            'Transfer-Encoding' => 'chunked',
            'Content-Type' => 'application/json'
        ]);
    }

    // 流式返回
    public function chatWithStream($connection, $content)
    {
        $this->chat->completions(
            [
                'model' => $this->model,
                'stream' => true,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt],
                    ['role' => 'user', 'content' => $content],
                ],
            ],
            [
                'timeout' => 60, //可选参数,超时时间，默认600s
                'stream' => function ($data) use ($connection) {
                    $connection->send(new Chunk(json_encode($data, JSON_UNESCAPED_UNICODE) . "\n"));
                },
                'complete' => function ($result, $response) use ($connection) {
                    if (isset($result['error'])) {
                        $connection->send(new Chunk(json_encode($result, JSON_UNESCAPED_UNICODE) . "\n"));
                    }
                    $connection->send(new Chunk(''));
                }
            ]
        );
        return response()->withHeaders([
            'Transfer-Encoding' => 'chunked',
        ]);
    }

    // 聊天页面
    public function chat(Request $request)
    {
        return view('index/chat');
    }
}
