# easy elasticsearch
基于elasticsearch实现简单操作CDRD

### 测试版本
 - **[elasticsearch：8.2.0](https://www.elastic.co/downloads/past-releases/elasticsearch-8-2-0)**
 - **[kibana：8.2.0](https://codeload.github.com/elastic/kibana/zip/refs/tags/v8.2.0)**

### 版本新增
  - V1.0.6 新增
    - 修复 新增文档(`add_doc`) 连贯问题
    - 新增 `where` 闭包函数，可对where追加自定义属性
    - 开放 `getParam` 函数为公有, `getParam` 用于存储所有的 `elasticsearch` 查询信息

### 使用
~~~ 
//地址
$host = "127.0.0.1:9200";

//索引名
$index = "test_index";

//类型名
$type = "test_type";

//实例化
$es = new \Shiroi\EasyElasticsearch\ElasticsearchHandler($host);
$es = $es
    //设置索引
    ->setIndex($index)
    //设置类型
    ->setType($type)
    //设置切片
    ->setSetting([
        'number_of_shards' => 1, //数据分片数
        'number_of_replicas' => 1, //数据备份数
    ]);


//创建索引
$table = $es->create_index([
    'id' => 'int',
    'username' => 'text',
    'password' => 'text',
    'content' => 'text',
    'create_time' => 'int',
    'update_time' => 'int',
]);
var_dump($table);


//删除索引
var_dump($es->delete_index());


//判断索引是否存在
var_dump($es->exists_index());


//获取索引
var_dump($es->get_index());


//添加文档
for ($id = 1; $id <= 1; $id++) {
    var_dump($es->add_doc([
        'id' => $id,
        'username' => uniqid(),
        'password' => rand(100000, 999999),
        'content' => 'test_'.rand(),
        'create_time' => time(),
        'update_time' => time(),
    ], $id));
}


//删除文档
$id = 1;
var_dump($es->delete_doc($id));


//更新文档
$id = 2;
var_dump($es->update_doc($id, [
    'content' => 'update content after'
    ...
]));


//判断文档是否存在
$id = 1;
var_dump($es->exists_doc($id));


//获取文档
$id = 2;
var_dump($es->get_doc($id));


//搜索文档

//获取全部(默认20条)
var_dump($es->search_doc());

//获取第一页前五条数据
var_dump($es->setPage(1)->setLimit(5)->search_doc());

//获取全部(id desc)排序
var_dump($es->order(['id' => 'desc'])->search_doc());

//获取`id`在1到5的范围内的数据(相反用not between)
var_dump($es
    //举例1
    ->where('id','between', [1,5])
    //举例2
    ->where('id','between', '1,5')
    //举例3
    ->where([
        'id' => ['between', '1,5']
    ])
    //举例4
    ->where([
        'id' => ['between', [1,5]]
    ])
    //举例5
    ->where([
        'id' => [
            ['between', [1,5]]
        ]
    ])
    //举例6
    ->where([
        'id' => [
            ['between', '1,5']
        ]
    ])
    ->search_doc());

//获取`id`等于1的数据
var_dump($es
    //举例1
    ->where('id', 1)
    //举例2
    ->where('id', '=', 1)
    //举例3(`in`可传多个,相反的可以用`not in`)
    ->where('id', 'in', '1')
    //举例4
    ->where([
        'id' => 1
    ])
    //举例5
    ->where([
        'id' => [
            '=', 1
        ]
    ])
    //举例6
    ->where([
        'id' => [
            [
                1
            ]
        ]
    ])
    ->search_doc());

//获取模糊查询字段`content`的值为"test"的所有数据
var_dump($es
    //举例1
    ->where('content', 'like', "test")
    //举例2
    ->where([
        'content' => [
            'like', "test"
        ]
    ])
    //举例3
    ->where([
        'content' => [
            ['like', "test"]
        ]
    ])
    ->search_doc());

//获取模糊查询字段`content`的值为"test"或者"test1"的所有数据
var_dump($es
    ->where([
        'content' => [
            //等于"test"
            //举例1
            ['and', "test"],
            //举例2
            ['like', "test"],
            //举例3
            ['&&', "test"],
            
            //或者"test1"
            //举例1
            ['or', "test1"],
            //举例2
            ['||', "test1"],
            //举例3
            ['or like', "test1"]
        ]
    ])
    ->search_doc());

//获取字段`content`不为"test"的所有数据
var_dump($es
    ->where([
        'content' => [
            //举例1
            ['!=', "test"],
            //举例2
            ['=!', "test"],
            //举例3
            ['not like', "test"],
            //举例4
            ['not', "test"],
        ]
    ])
    ->search_doc());
    
//获取`id`大于5,小于等于8,不大于等于7,不小于6的所有数据
var_dump($es
    ->where([
        'id' => [
            ['>', 5], // >, gt
            ['lte', 8], // <=, lte
            ['not >=', 7], // not gte, ! gte, !gte, ! >=, !>=, not >=
            ['!lt', 6], // not lt, ! lt, !lt, ! <, !<, not <
        ]
    ])
    ->search_doc());
    
//设置高亮
var_export($es
    ->where([
        'password' => [
            'or', '123456'
        ],
        'content' => [
            'like', '苹果手机'
        ]
    ])
    ->setHighLight([
        'password',
        'content'
    ])
    ->search_doc());
    
//多字段搜索
var_export($es
    ->where([
        [
            'status', '=', 2
        ],
        [
            "title|describe", 'like', '噶啥刚打那个'
        ]
    ])
    ->search_doc());
    
//刷新where条件
var_export($es
    ->where([
        ['id', 'not between', [5,10]]
    ])
    ->flushWhere() //刷新后（前面的where条件则不存在）
    ->where([
        [
            'status', '=', 2
        ],
        [
            "title|describe", 'like', '噶啥刚打那个'
        ]
    ])
    ->search_doc());
~~~