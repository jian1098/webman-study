<?php

namespace app\output;

use NeuronAI\StructuredOutput\SchemaProperty;

// 定义输出结构
class PersonOutput 
{
    #[SchemaProperty(
        description: '姓名', 
        required: true
    )]
    public string $name;
    
    #[SchemaProperty(
        description: '爱好', 
        required: false
    )]
    public string $preference;
}