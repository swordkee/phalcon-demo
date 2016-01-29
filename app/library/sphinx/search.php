<?php

/**
 * 搜索接口
 *
 */
class search
{
    private $host = "127.0.0.1";
    private $port = "9312";

    public function __construct()
    {
//        SPH_RANK_PROXIMITY_BM25 = sum(lcs*user_weight)*1000+bm25
//        SPH_RANK_BM25 = bm25
//        SPH_RANK_NONE = 1
//        SPH_RANK_WORDCOUNT = sum(hit_count*user_weight)
//        SPH_RANK_PROXIMITY = sum(lcs*user_weight)
//        SPH_RANK_MATCHANY = sum((word_count+(lcs-1)*max_lcs)*user_weight)
//        SPH_RANK_FIELDMASK = field_mask
//        SPH_RANK_SPH04 = sum((4*lcs+2*(min_hit_pos==1)+exact_hit)*user_weight)*1000+bm25

        //初始化sphinx
        import('library.sphinx/sphinxapi');
        $this->cl = new SphinxClient();
        $mode = SPH_MATCH_EXTENDED2;
        $host = $this->host;
        $port = intval($this->port);
        $ranker = SPH_RANK_PROXIMITY;
        $this->cl->SetServer($host, $port);
        $this->cl->SetConnectTimeout(1);
        $this->cl->SetArrayResult(true);
        $this->cl->SetMatchMode($mode);
        $this->cl->SetRankingMode($ranker);
    }

    public function scws($key)
    {
        if (function_exists('scws')) {
            $so = scws_new();
            $so->set_charset('utf-8');
            $so->add_dict(ini_get('scws.default.fpath') . '/dict.utf8.xdb');
            //自定义词库
            //$so->add_dict(APP_PATH . '/library/dict/scws.txt', SCWS_XDICT_TXT);
            $so->set_rule(ini_get('scws.default.fpath') . '/rules.utf8.ini');
            $so->set_ignore(true);
            $so->set_multi(true);
            $so->set_duality(true);
        } else {
            require_once APP_PATH . '/library/scws/pscws4.class.php';
            $so = new PSCWS4('utf-8');
            $so->set_dict(APP_PATH . '/library/scws/etc/dict.utf8.xdb');
            $so->set_rule(APP_PATH . '/library/scws/etc/rules.utf8.ini');
            $so->set_multi(true);
            $so->set_ignore(true);
            $so->set_duality(true);
        }
        $keys = str_replace(array(" ", "　", "\t", "\n", "\r"), array("", "", "", "", ""), $key);
        $so->send_text($keys);
        $words_array = $so->get_result();
        $words = '';
        foreach ($words_array as $v) {
            $words = $words . '|"' . $v['word'] . '"';
        }
        $so->close();
        return $words = trim($words, '|');
    }

    /**
     * 搜索
     * @param $key
     * @param array|string $adddate 时间范围数组        类似sql between start AND end         格式:array('start','end');
     * @param int $offset 偏移量
     * @param int $limit 匹配项数目限制    类似sql limit $offset, $limit
     * @param string $orderby 排序字段        类似sql order by $orderby {id:文章id,weight:权重}
     * @return bool
     * @internal param array $siteids 站点id数组
     * @internal param array|string $typeids 类型ids        类似sql IN('')
     * @internal param string $catids
     * @internal param $eky
     * @internal param string $q 关键词            类似sql like'%$q%'
     */
    public function search($key, $adddate = array(), $offset = 0, $limit = 10, $orderby = '@weight desc')
    {
        if ($orderby) {
            $this->cl->SetSortMode(SPH_SORT_EXTENDED, $orderby);
        }
        if ($limit) {
            $this->cl->SetLimits($offset, $limit, 1000);
        }
        //过滤时间
        if ($adddate) {
            $this->cl->SetFilterRange('adddate', $adddate[0], $adddate[1], false);
        }
        // 设置字段权重
        $this->cl->SetFieldWeights(array('title' => 5, 'data' => 1));

        $keys = $this->scws($key);
        return $this->cl->Query($keys, 'main;delta');
    }
}