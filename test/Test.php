<?php

use PHPUnit\Framework\TestCase;

class Test extends TestCase
{
    public static string $index_name = 'test_index';

    public static string $type_name = 'test_type';

    public function handler(): \Shiroi\EasyElasticsearch\ElasticsearchHandler
    {
        $handler = new \Shiroi\EasyElasticsearch\ElasticsearchHandler('127.0.0.1:9201');
        return $handler
            //设置索引
            ->setIndex(self::$index_name)
            //设置类型
            ->setType(self::$type_name)
            //设置
            ->setSetting([
                'number_of_shards' => 1, //数据分片数
                'number_of_replicas' => 0, //数据备份数
            ]);
    }

    /**
     * 创建索引
     */
    public function testCreateIndex() {
        $table = [
            'id' => 'int',
            'username' => 'text',
            'password' => 'text',
            'content' => 'text',
            'create_time' => 'int',
            'update_time' => 'int',
        ];

        var_dump($this->handler()->create_index($table));
    }

    /**
     * 删除索引
     */
    public function testDeleteIndex() {
        var_dump($this->handler()->delete_index());
    }

    /**
     * 判断索引是否存在
     */
    public function testExistsIndex() {
        var_dump($this->handler()->exists_index());
    }

    /**
     * 获取索引
     */
    public function testGetIndex() {
        var_export($this->handler()->get_index());
    }

    /**
     * 添加文档
     */
    public function testAddDoc() {
        for ($i = 1; $i <= 50; $i++) {
            var_dump($this->handler()->add_doc([
                'id' => $i,
                'username' => uniqid(),
                'password' => rand(100000, 999999),
                'content' => 'test_'.rand(),
                'create_time' => time(),
                'update_time' => time(),
            ], $i));
        }
    }

    /**
     * 删除文档
     */
    public function testDeleteDoc() {
        var_dump($this->handler()->delete_doc(1));
    }

    /**
     * 更新文档
     */
    public function testUpdateDoc() {
        var_dump($this->handler()->update_doc(2, [
            'content' => 'update content after'
        ]));
    }

    /**
     * 判断文档是否存在
     */
    public function testExistsDoc() {
        var_dump($this->handler()->exists_doc(2));
    }

    /**
     * 获取文档
     */
    public function testGetDoc() {
        var_export($this->handler()->get_doc(2));
    }

    /**
     * 搜索文档
     */
    public function testSearchDoc_1() {
        var_export($this->handler()
            ->skipLimit(2)
            ->setPage(1)
            ->setLimit(10)
            //精确查询
            ->where('id','between', '1,5', function($query) {
                $query->boost = 3;
            })
            //模糊查询
            ->where('content', 'like', 'test', function ($query) {
                $query->boost = 2;
            })
            //多条件查询
            ->where('id','not between', [5,10])
            ->where([
                ['id', 'not between', [5,10]]
            ])
            ->where([
                'password' => ['>=', 500000, function($obj) {
                    $obj->boost = 4;
                }],
                'content' => ['or like', 'update'],
//                'id' => [
//                    ['in', '1,2,3,4,5,6'],
//                    ['not in', [5,6]],
//                    ['>=', 1],
//                    ['<=', 8],
//                    ['>', 1],
//                    ['<', 8]
//                ]
            ])
            ->order(['id' => 'desc'])
            ->setHighLight([
                'password',
                'content' => [
                    'number_of_fragments' => 10
                ]
            ], ["<span style='color: red;'>"], ["</span>"], 20)
            ->search_doc());
    }

    /**
     * 搜索文档
     */
    public function testSearchDoc_2() {
        var_export($this->handler()->search_doc());
    }

    /**
     * 搜索文档
     * @throws Exception
     */
    public function testSearchDoc_3() {
        //地址
        $host = "127.0.0.1:9201";
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
//        var_dump($table);

        //删除索引
//        var_dump($es->delete_index());

        //判断索引是否存在
//        var_dump($es->exists_index());

        //获取索引
//        var_dump($es->get_index());

        //添加文档
        for ($id = 1; $id <= 10; $id++) {
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
//        var_dump($es->delete_doc($id));

        //更新文档
        $id = 2;
//        var_dump($es->update_doc($id, [
//            'content' => 'update content after'
//        ]));

        //判断文档是否存在
        $id = 1;
//        var_dump($es->exists_doc($id));

        //获取文档
        $id = 2;
//        var_dump($es->get_doc($id));

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
    }

    /**
     * 搜索文档(多字段查询)
     */
    public function testSearchDoc_4() {
        var_export($this->handler()
            ->where([
                [
                    'status', '=', 2
                ],
                [
                    "title|describe", 'like', '噶啥刚打那个'
                ]
            ])
            ->search_doc());
    }

    /**
     * 搜索文档(清空之前的where条件)
     */
    public function testSearchDoc_5() {
        var_export($this->handler()
            ->where([
                ['id', 'not between', [5,10]]
            ])
            ->flushWhere()
            ->where([
                [
                    'status', '=', 2
                ],
                [
                    "title|describe", 'like', '噶啥刚打那个'
                ]
            ])
            ->search_doc());
    }
}