<?php

namespace Shiroi\EasyElasticsearch;

use Elastic\Elasticsearch\{Client, ClientBuilder};
use Exception;
use Shiroi\EasyElasticsearch\bean\QueryProperty;
use stdClass;

class ElasticsearchHandler
{
    //ES客户端链接
    private Client $client;

    private string $host;

    private int $retries;

    private array $param = [];

    private string $index;

    private string $type;

    private array $setting = [
        'number_of_shards' => 1,
        'number_of_replicas' => 1
    ];

    private int $page = 1;

    private int $limit = 20;

    private int $skipLimit = 0;

    private array $andWhere = [];

    private array $notWhere = [];

    private array $orWhere = [];

    private array $order = [];

    private array $field = [];

    private array $highLight = [];

    //索引属性
    private array $properties = [];

    //设置表字段类型对应es字段类型
    private array $columnType = [
        'varchar|text|char|longtext|tinytext' => [
            'type' => 'text',
            'analyzer' => 'ik_max_word',
            'search_analyzer' => 'ik_smart',
        ],
        'int|tinyint|bigint|integer' => [
            'type' => 'integer'
        ],
        'decimal|double|float' => [
            'type' => 'float'
        ]
    ];

    //查询条件
    private array $whereData = [];

    //保存条件
    private array $saveData = [];

    //字段属性
    private array $fieldAttribute = [
        'type'
    ];

    /**
     * 构造函数
     * MyElasticsearch constructor.
     * @throws Exception
     */
    public function __construct($host = "127.0.0.1:9200", $retries = 10)
    {
        $this->setHost($host);
        $this->setRetries($retries);
        $this->client = ClientBuilder::create()
            ->setHosts([$this->getHost()])
            ->setRetries($this->getRetries())
            ->build();
    }

    /**
     * 创建索引
     * @param array $body
     * @param null $callback
     * @return bool|string
     */
    public function create_index(array $body = [], $callback = null)
    {
        //初始化索引参数
        $this->setParam($this->getIndex(), 'index', $this->param, true)
            ->setParam($this->getType(), 'type', $this->param, true)
            ->setParam($this->getSetting(), 'body.settings', $this->param)
            ->setParam(['enabled' => true], 'body.mappings._source', $this->param)
            ->setParam($this->setProperties($body)->getProperties(), 'body.mappings.properties', $this->param);

        //接收所有参数
        $body = $this->getParam();
        if (is_callable($callback)) {
            $body = $callback($body,$this);
        }
        try {
            $getIndex = $this->client->indices()->create($body)->asBool();
        } catch (Exception $e) {
            $getIndex = $this->exists_index();
        }
        return $getIndex;
    }

    /**
     * 判断索引是否存在
     * @return bool|string
     */
    public function exists_index()
    {
        $this->setParam($this->getIndex(),'index', $this->param, true);
        try {
            return $this->client->indices()->exists($this->getParam('index'))->asBool();
        } catch (Exception $e) {
            return  $e->getMessage();
        }
    }

    /**
     * 获取索引字段
     * @return array|string
     */
    public function get_index()
    {
        $this->setParam($this->getIndex(),'index', $this->param, true);
        try {
            return $this->client->indices()->getMapping($this->getParam('index'))->asArray();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 删除索引
     * @return bool|string
     */
    public function delete_index()
    {
        $this->setParam($this->getIndex(),'index', $this->param, true)
            ->setParam($this->getType(),'type', $this->param, true);
        try {
            return $this->client->indices()->delete($this->getParam(['index', 'type']))->asBool();
        } catch (Exception $e) {
            return  $e->getMessage();
        }
    }

    /**
     * 添加文档
     * @param $doc ['id'=>100, 'title'=>'phone']
     * @param $id
     * @example $object->add_doc($doc, $d)->asBool();//bool
     * @return bool|array|string
     */
    public function add_doc($doc, $id = null)
    {
        $this->setParam($this->getIndex(),'index',$this->param, true)
            ->setParam($this->getType(),'type',$this->param, true)
            ->setParam($id,'id',$this->param, true)
            ->setSaveData($doc);
        $param = $this->getParam(['index', 'type', 'id']);
        $this->setParam($doc,'body',$param);
        try {
            return $this->client->index($param)->asBool()? $this->getSaveData(): false;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 删除文档
     * @param $id
     * @return bool|string
     * @example $object->delete_doc($id);//bool
     */
    public function delete_doc($id) {
        $this->setParam($this->getIndex(),'index',$this->param, true)
            ->setParam($this->getType(),'type',$this->param, true)
            ->setParam($id,'id',$this->param, true);
        try {
            return $this->client->delete($this->getParam(['index', 'type', 'id']))->asBool();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 判断文档存在
     * @param $id
     * @return bool|string
     * @example $object->exists_doc($id);//bool
     */
    public function exists_doc($id) {
        $this->setParam($this->getIndex(),'index',$this->param, true)
            ->setParam($this->getType(),'type',$this->param, true)
            ->setParam($id,'id', $this->param, true);
        try {
            return $this->client->exists($this->getParam(['index', 'type', 'id']))->asBool();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 获取文档
     * @param $id
     * @return array|string
     */
    public function get_doc($id) {
        $this->setParam($this->getIndex(),'index',$this->param, true)
            ->setParam($this->getType(),'type',$this->param, true)
            ->setParam($id,'id', $this->param, true);
        try {
            return $this->client->get($this->getParam(['index', 'type', 'id']))->asArray();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 更新文档
     * @param $id
     * @param array $body ['doc' => ['title' => '苹果手机iPhoneX']]
     * @return bool|string
     */
    public function update_doc($id, array $body = []) {
        // 可以灵活添加新字段,最好不要乱添加
        $this->setParam($this->getIndex(),'index',$this->param, true)
            ->setParam($this->getType(),'type',$this->param, true)
            ->setParam($id,'id',$this->param, true)
            ->setParam($body,'body.doc',$this->param, true);
        try {
            return $this->client->update($this->getParam())->asBool();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 搜索文档 (分页，排序，权重，过滤)
     * @return array|string
     */
    public function search_doc($callback = null) {
        $this->setParam($this->getIndex(),'index',$this->param, true)
            ->setParam($this->getType(),'type',$this->param, true)
            ->setParam($this->getLimit(),'size',$this->param, true)
            ->setParam((($this->getPage() - 1) * $this->getLimit()) + $this->skipLimit, 'from',$this->param, true)
            ->setParam([], 'body', $this->param, true);
        //where condition jointing
        if($this->getWhereData()) {
            $this->setParam($this->getWhereData(), 'body.query');
        }
        //field condition jointing
        if($this->getField()) {
            $this->setParam($this->getField(), 'body._source');
        }
        //order condition jointing
        $this->setParam($this->getOrder(), 'body.sort');
        //set highlight
        if($this->getHighLight()) {
            $this->setParam($this->getHighLight(), 'body.highlight');
        }

        //body struct
        $body = $this->getParam(['index', 'type', 'size', 'from', 'body']);
        if (is_callable($callback)) {
            $body = $callback($body,$this);
        }
//        var_export(json_encode($body));die();
        try {
            return $this->client->search($body)->asArray();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function field($field = []):self {
        if(is_string($field)) {
            $this->field = explode(',', $field);
        } else {
            $this->field = $field;
        }
        return $this;
    }

    public function where($field, $op = null, $condition = null, $func = null): self {
        $match = [];
        if(is_array($field)) {
            foreach ($field as $key => $val) {
                if(is_string($val)) $this->buildWhere($key,$val);
                if(is_array($val)) {
                    foreach ($val as $v) if(!is_array($v)) {
                        $val = [$val];break;
                    }
                    foreach ($val as $v) {
                        if(is_int($key)) {
                            call_user_func_array([self::class, 'buildWhere'], $v);
                        } else {
                            call_user_func_array([self::class, 'buildWhere'], array_merge([$key], $v));
                        }
                    }
                }
            }
        } else {
            call_user_func_array([self::class, 'buildWhere'], func_get_args());
        }
        $this->setParam($this->andWhere,'bool.must', $match)
            ->setParam($this->notWhere,'bool.must_not', $match)
            ->setParam($this->orWhere,'bool.should', $match)
            ->setWhereData($match);
        return $this;
    }

    public function order($order = []): self
    {
        foreach ($order as $k => $v) {
            if(is_int($k)) {
                $param = explode(' ', $v);
                if(isset($param[0])) $this->order[$param[0]] = ['order' => $param[1]??'asc'];
            } else {
                $this->order[$k] = ['order' => $v];
            }
        }
        return $this;
    }

    public function flushWhere(): self
    {
        $this->andWhere = [];
        $this->notWhere = [];
        $this->orWhere = [];
        $this->whereData = [];
        return $this;
    }

    /**
     * @return string
     */
    private function getIndex(): string
    {
        return $this->index;
    }

    /**
     * @param string $index
     * @return self
     */
    public function setIndex(string $index): self
    {
        $this->index = $index;
        return $this;
    }

    /**
     * @return string
     */
    private function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return self
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * where query build
     * @param $field
     * @param $op
     * @param $condition
     * @param $func
     * @return void
     */
    private function buildWhere($field, $op, $condition = null, $func = null) {
        $param = func_get_args();
        $obj = end($param);
        $fields = [];
        $attributes = [];
        if(is_object($obj)) {
            //获取对象
            $obj($queryProperty = new QueryProperty());
            //获取对象属性
            $fields = array_filter(get_object_vars($queryProperty));
            //忽略对象参数
            array_pop($param);
            //属性追加
            foreach ($fields as $attribute => $value) {
                if(in_array($attribute, $this->fieldAttribute)) $attributes[$attribute] = $value;
            }
        }
        if(count($param) == 2) $condition = $op;
        switch ($op) {
            case 'between':
                if(is_string($condition)) $condition = explode(',',$condition);
                if(count($condition) == 2) {
                    $this->andWhere[]['range'] = [$field => array_merge(
                        ['gte' => $condition[0], 'lte' => $condition[1]], $fields
                    )];
                }
                break;
            case '=':
                if(count($multi_field = explode('|', $field)) > 1) {
                    $this->andWhere[]['multi_match'] = array_merge(['query' => $condition, 'fields' => $multi_field], $attributes);
                } else {
                    $this->andWhere[]['term'] = [$field => array_merge(['value' => $condition], $fields)];
                }
                break;
            case 'gt':
            case '>':
                $this->andWhere[]['range'] = [$field => array_merge(['gt' => $condition], $fields)];
                break;
            case 'lt':
            case '<':
                $this->andWhere[]['range'] = [$field => array_merge(['lt' => $condition], $fields)];
                break;
            case 'gte':
            case '>=':
                $this->andWhere[]['range'] = [$field => array_merge(['gte' => $condition], $fields)];
                break;
            case 'lte':
            case '<=':
                $this->andWhere[]['range'] = [$field => array_merge(['lte' => $condition], $fields)];
                break;
            case 'not gt':
            case '! gt':
            case '!gt':
            case '! >':
            case '!>':
            case 'not >':
                $this->notWhere[]['range'] = [$field => array_merge(['gt' => $condition], $fields)];
                break;
            case 'not lt':
            case '! lt':
            case '!lt':
            case '! <':
            case '!<':
            case 'not <':
                $this->notWhere[]['range'] = [$field => array_merge(['lt' => $condition], $fields)];
                break;
            case 'not gte':
            case '! gte':
            case '!gte':
            case '! >=':
            case '!>=':
            case 'not >=':
                $this->notWhere[]['range'] = [$field => array_merge(['gte' => $condition], $fields)];
                break;
            case 'not lte':
            case '! lte':
            case '!lte':
            case '! <=':
            case '!<=':
            case 'not <=':
                $this->notWhere[]['range'] = [$field => array_merge(['lte' => $condition], $fields)];
                break;
            case 'in':
                $this->andWhere[]['terms'] = [$field => array_merge(
                    is_array($condition)? $condition: explode(',',$condition), $fields
                )];
                break;
            case 'and':
            case 'like':
            case '&&':
                if(count($multi_field = explode('|', $field)) > 1) {
                    $this->andWhere[]['multi_match'] = array_merge(['query' => $condition, 'fields' => $multi_field], $attributes);
                } else {
                    $this->andWhere[]['match'] = [$field => array_merge(['query' => $condition], $fields)];
                }
                break;
            case '=!':
            case '!=':
            case 'not like':
            case 'not':
                if(count($multi_field = explode('|', $field)) > 1) {
                    $this->notWhere[]['multi_match'] = array_merge(['query' => $condition, 'fields' => $multi_field], $attributes);
                } else {
                    $this->notWhere[]['match'] = [$field => array_merge(['query' => $condition], $fields)];
                }
                break;
            case 'not in':
                if(is_string($condition)) $condition = explode(',',$condition);
                foreach ($condition as $v) {
                    $this->notWhere[]['match'] = [$field => array_merge(['query' => $v], $fields)];
                }
                break;
            case 'not between':
                if(is_string($condition)) $condition = explode(',',$condition);
                if(count($condition) == 2) {
                    $this->notWhere[]['range'] = ['id' => array_merge(['gte' => $condition[0], 'lte' => $condition[1]], $fields)];
                }
                break;
            case 'or':
            case '||':
            case 'or like':
                if(count($multi_field = explode('|', $field)) > 1) {
                    $this->orWhere[]['multi_match'] = array_merge(['query' => $condition, 'fields' => $multi_field], $attributes);
                } else {
                    $this->orWhere[]['match'] = [$field => array_merge(['query' => $condition], $fields)];
                }
                break;
            default:
                if(count($multi_field = explode('|', $field)) > 1) {
                    $this->andWhere[]['multi_match'] = array_merge(['query' => $condition, 'fields' => $multi_field], $attributes);
                } else {
                    $this->andWhere[]['term'] = [$field => array_merge(['value' => $condition], $fields)];
                }
        }
    }

    /**
     * @return string
     */
    private function getHost(): string
    {
        return $this->host;
    }

    /**
     * @param string $host
     */
    private function setHost(string $host): void
    {
        $this->host = $host;
    }

    /**
     * @return int
     */
    private function getRetries(): int
    {
        return $this->retries;
    }

    /**
     * @param mixed $retries
     */
    private function setRetries($retries): void
    {
        $this->retries = $retries;
    }

    /**
     * @param boolean|string|array $show
     * @return array
     */
    public function getParam($show = true): array
    {
        $arr = [];
        if(is_bool($show)) {
            $arr = $this->param;
        } elseif (is_string($show)) {
            $show = explode(',', $show);
        }
        if(is_array($show)) {
            foreach ($this->param as $k => $v) {
                if(in_array($k, $show)) {
                    $arr[$k] = $v;
                }
            }
        }

        return $arr;
    }

    /**
     * @param $value
     * @param string $key
     * @param array|null $data
     * @param bool $isReplace (是否覆盖)
     * @return self
     */
    private function setParam($value, string $key = 'index', array &$data = null, bool $isReplace = false): self
    {
        if($data === null) {
            $data = &$this->param;
        }
        $keys = explode('.', $key);
        $section = &$data;
        if($isReplace) unset($section[$key]);
        foreach ($keys as $key => $part) {
            if (array_key_exists($part, $section) === false) {
                $section[$part] = (($key == count($keys) - 1)? $value: []);
            }
            $section = &$section[$part];
        }
        return $this;
    }

    /**
     * @return array|int[]
     */
    private function getSetting(): array
    {
        return $this->setting;
    }

    /**
     * @param array|int[] $setting
     */
    public function setSetting(array $setting): self
    {
        $this->setting = $setting;
        return $this;
    }

    /**
     * @return array
     */
    private function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @param array $properties
     * @return self
     */
    private function setProperties(array $properties): self
    {
        foreach ($properties as $column => $info) {
            foreach ($this->columnType as $types => $content) {
                $infoData = [];
                if(is_array($info)) {
                    $infoType = $info['type'] ?? 'text';
                    unset($info['type']);
                    $infoData = $info;
                } else {
                    $infoType = $info;
                }
                if(in_array($infoType,explode('|',$types))) {
                    $this->properties[$column] = $infoData ? array_merge(array_intersect_key($content, array_flip(['type'])), $infoData): $content;
                }
            }
        }
        return $this;
    }

    /**
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @param int $page
     * @return self
     */
    public function setPage(int $page): self
    {
        $this->page = $page;
        return $this;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     * @return self
     */
    public function setLimit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @param int $skipLimit
     * @return self
     */
    public function skipLimit(int $skipLimit = 0): self
    {
        $this->skipLimit = $skipLimit;
        return $this;
    }

    /**
     * @return array
     */
    private function getWhereData(): array
    {
        return $this->whereData;
    }

    /**
     * @param array $whereData
     * @return void
     */
    private function setWhereData(array $whereData): void
    {
        $this->whereData = $whereData;
    }

    /**
     * @return array
     */
    public function getField(): array
    {
        return $this->field;
    }

    /**
     * @return array
     */
    private function getOrder(): array
    {
        return $this->order;
    }

    /**
     * @return array
     */
    public function getSaveData(): array
    {
        return $this->saveData;
    }

    /**
     * @param array $saveData
     */
    public function setSaveData(array $saveData): void
    {
        $this->saveData = $saveData;
    }

    /**
     * @return array|array[]
     */
    public function getColumnType(): array
    {
        return $this->columnType;
    }

    /**
     * @param array $columnType
     * @return void
     */
    public function setColumnType(array $columnType): void
    {
        $this->columnType = $columnType;
    }

    /**
     * @return array
     */
    public function getHighLight(): array
    {
        return $this->highLight;
    }

    /**
     * @param array|string $highLight
     * @param string[] $pre_tags
     * @param string[] $post_tags
     * @param null|int|boolean $size
     * @return self
     */
    public function setHighLight($highLight = [], array $pre_tags = ["<span style='color: red;'>"], array $post_tags = ["</span>"], $size = null): self
    {
        if($highLight) {
            $this->highLight['pre_tags'] = $pre_tags;
            $this->highLight['post_tags'] = $post_tags;
        }
        foreach (is_string($highLight)? explode(',', $highLight): $highLight as $key => $field) {
            if (is_string($key)) {
                $this->highLight['fields'][$key] = new stdClass();
                if(is_array($field)) foreach ($field as $k => $v) $this->highLight[$k] = $v;
            } else {
                $this->highLight['fields'][$field] = new stdClass();
            }
            if($size !== null)
                $this->highLight['number_of_fragments'] = $size;
        }
        return $this;
    }
}
