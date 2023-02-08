<?php

namespace Shiroi\EasyElasticsearch;

use Elastic\Elasticsearch\{Client, ClientBuilder};
use Exception;
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

    private array $andWhere = [];

    private array $notWhere = [];

    private array $orWhere = [];

    private array $order = [];

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

    /**
     * 构造函数
     * MyElasticsearch constructor.
     * @throws Exception
     */
    public function __construct($host = "127.0.0.1:9201", $retries = 10)
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
     * @return bool|string
     */
    public function create_index(array $body = [])
    {
        //初始化索引参数
        $this->setParam($this->getIndex(), 'index', $this->param)
            ->setParam($this->getType(), 'type', $this->param)
            ->setParam($this->getSetting(), 'body.settings', $this->param)
            ->setParam(['enabled' => true], 'body.mappings._source', $this->param)
            ->setParam($this->setProperties($body)->getProperties(), 'body.mappings.properties', $this->param);
        try {
            $getIndex = $this->client->indices()->create($this->getParam())->asBool();
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
        $this->setParam($this->getIndex(),'index', $this->param);
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
        $this->setParam($this->getIndex(),'index', $this->param);
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
        $this->setParam($this->getIndex(),'index', $this->param)
            ->setParam($this->getType(),'type', $this->param);
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
        $this->setParam($this->getIndex(),'index',$this->param)
            ->setParam($this->getType(),'type',$this->param)
            ->setParam($id,'id',$this->param)
            ->setSaveData($doc);
        $param = $this->getParam();
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
        $this->setParam($this->getIndex(),'index',$this->param)
            ->setParam($this->getType(),'type',$this->param)
            ->setParam($id,'id',$this->param);
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
        $this->setParam($this->getIndex(),'index',$this->param)
            ->setParam($this->getType(),'type',$this->param)
            ->setParam($id,'id', $this->param);
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
        $this->setParam($this->getIndex(),'index',$this->param)
            ->setParam($this->getType(),'type',$this->param)
            ->setParam($id,'id', $this->param);
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
        $this->setParam($this->getIndex(),'index',$this->param)
            ->setParam($this->getType(),'type',$this->param)
            ->setParam($id,'id',$this->param)
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
    public function search_doc() {
        $this->setParam($this->getIndex())
            ->setParam($this->getType(),'type')
            ->setParam($this->getLimit(),'size')
            ->setParam(($this->getPage() - 1) * $this->getLimit(), 'from');
        //where condition jointing
        if($this->getWhereData()) {
            $this->setParam($this->getWhereData(), 'body.query');
        } else {
            $this->setParam([],'match_all');
        }
        //order condition jointing
        $this->setParam($this->getOrder(), 'body.sort');
        //set highlight
        if($this->getHighLight()) {
            $this->setParam($this->getHighLight(), 'body.highlight');
        }
//        var_export($this->getParam(['index', 'type', 'size', 'from', 'body']));
        try {
            return $this->client->search($this->getParam(['index', 'type', 'size', 'from', 'body']))->asArray();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function where($field, $op = null, $condition = null): self {
        $match = [];
        if(is_array($field)) {
            foreach ($field as $key => $val) {
                if(is_string($val)) $this->buildWhere($key,$val);
                if(is_array($val)) {
                    foreach ($val as $v) if(!is_array($v)) {
                        $val = [$val];
                        break;
                    }
                    foreach ($val as $v) {
                        if(count($v) == 2) {
                            $this->buildWhere($key, $v[1], $v[0]);
                        } else {
                            $this->buildWhere($key, $v[0]);
                        }
                    }
                }
            }
        } else {
            $param = func_get_args();
            if(count($param) == 2) $this->buildWhere($param[0], $param[1]);
            if(count($param) == 3) $this->buildWhere($param[0], $param[2], $param[1]);
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
     * @param $value
     * @param $condition
     * @return void
     */
    private function buildWhere($field, $value, $condition = null) {
        switch ($condition) {
            case 'between':
                if(is_string($value)) $value = explode(',',$value);
                if(count($value) == 2) {
                    $this->andWhere[]['range'] = [$field => ['gte' => $value[0], 'lte' => $value[1]]];
                }
                break;
            case '=':
                $this->andWhere[]['term'] = [$field => $value];
                break;
            case 'gt':
            case '>':
                $this->andWhere[]['range'] = [$field => ['gt' => $value]];
                break;
            case 'lt':
            case '<':
                $this->andWhere[]['range'] = [$field => ['lt' => $value]];
                break;
            case 'gte':
            case '>=':
                $this->andWhere[]['range'] = [$field => ['gte' => $value]];
                break;
            case 'lte':
            case '<=':
                $this->andWhere[]['range'] = [$field => ['lte' => $value]];
                break;
            case 'not gt':
            case '! gt':
            case '!gt':
            case '! >':
            case '!>':
            case 'not >':
                $this->notWhere[]['range'] = [$field => ['gt' => $value]];
                break;
            case 'not lt':
            case '! lt':
            case '!lt':
            case '! <':
            case '!<':
            case 'not <':
                $this->notWhere[]['range'] = [$field => ['lt' => $value]];
                break;
            case 'not gte':
            case '! gte':
            case '!gte':
            case '! >=':
            case '!>=':
            case 'not >=':
                $this->notWhere[]['range'] = [$field => ['gte' => $value]];
                break;
            case 'not lte':
            case '! lte':
            case '!lte':
            case '! <=':
            case '!<=':
            case 'not <=':
                $this->notWhere[]['range'] = [$field => ['lte' => $value]];
                break;
            case 'in':
                $this->andWhere[]['terms'] = [$field => is_array($value)?$value:explode(',',$value)];
                break;
            case 'and':
            case 'like':
            case '&&':
                $this->andWhere[]['match'] = [$field => $value];
                break;
            case '=!':
            case '!=':
            case 'not like':
            case 'not':
                $this->notWhere[]['match'] = [$field => $value];
                break;
            case 'not in':
                if(is_string($value)) $value = explode(',',$value);
                foreach ($value as $v) {
                    $this->notWhere[]['match'] = [$field => $v];
                }
                break;
            case 'not between':
                if(is_string($value)) $value = explode(',',$value);
                if(count($value) == 2) {
                    $this->notWhere[]['range'] = ['id' => ['gte' => $value[0], 'lte' => $value[1]]];
                }
                break;
            case 'or':
            case '||':
            case 'or like':
                $this->orWhere[]['match'] = [$field => $value];
                break;
            default:
                $this->andWhere[]['term'] = [$field => $value];
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
    private function getParam($show = true): array
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
                if(in_array($info,explode('|',$types))) $this->properties[$column] = $content;
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
     * @return self
     */
    public function setHighLight($highLight = [], array $pre_tags = ["<span style='color: red;'>"], array $post_tags = ["</span>"]): self
    {
        if($highLight) {
            $this->highLight['pre_tags'] = $pre_tags;
            $this->highLight['post_tags'] = $post_tags;
        }
        foreach (is_string($highLight)? explode(',', $highLight): $highLight as $field) {
            $this->highLight['fields'][$field] = new stdClass();
        }
        return $this;
    }
}