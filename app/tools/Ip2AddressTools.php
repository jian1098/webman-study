<?php

namespace App\tools;

use GuzzleHttp\Client;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

// 工具：IP地址查询
class Ip2AddressTools extends Tool
{
    protected Client $client;

    public function __construct()
    {
        // Define Tool name and description
        parent::__construct(
            'ip_to_address',
            '根据IP地址查询对应的省份和城市信息',
        );
    }

    /**
     * Return the list of properties.
     */
    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'ip',
                type: PropertyType::STRING,
                description: 'The IP address.',
                required: true
            )
        ];
    }

    /**
     * Implementing the tool logic
     */
    public function __invoke(string $ip): string
    {
        $response = $this->getClient()
            ->get($ip . '?lang=zh-CN')
            ->getBody()
            ->getContents();

        $response = json_decode($response, true);

        return $response['regionName'] . $response['city'];
    }

    protected function getClient(): Client
    {
        return $this->client ??= new Client([
            'base_uri' => 'http://ip-api.com/json/',
            'headers' => []
        ]);
    }
}