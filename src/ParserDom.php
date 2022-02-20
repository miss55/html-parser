<?php

namespace HtmlParser;

/**
 * Copyright (c) 2013, 俊杰Jerry
 * All rights reserved.
 *
 * @description: html解析器
 * @author     : 俊杰Jerry<bupt1987@gmail.com>
 * @date       : 2013-6-10
 */
class ParserDom
{

    /**
     * @var \DOMNode
     */
    public $node;

    /**
     * @var bool|integer
     */
    private $idx;

    /**
     * @var integer
     */
    private $currentIdx;

    /**
     * 严格模式
     *
     * @var bool
     */
    private $strict;

    /**
     * @param \DOMNode|string $node
     *
     * @throws ParseDomException
     */
    public function __construct($node, $strict = false)
    {
        if ($node) {
            $this->load($node);
        }
        $this->strict = $strict;
    }

    /**
     * 初始化的时候可以不用传入html，后面可以多次使用
     *
     * @param null $node
     *
     * @throws ParseDomException
     */
    public function load($node = null)
    {
        if ($node instanceof \DOMNode) {
            $this->node = $node;
        } else {
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->strictErrorChecking = false;
            if (is_file($node)) {
                $f = fopen($node, 'r');
                try {
                    $this->isHtml(fgets($f, 2096)) ? $dom->loadHTMLFile($node) : $dom->load($node);
                } finally {
                    fclose($f);
                }
                $this->node = $dom;
            } else if ($this->isHtml($node) && @$dom->loadHTML($node)) {
                $this->node = $dom;
            } else if ($this->isXml($node) && @$dom->loadXML($node)) {
                $this->node = $dom;
            } else {
                throw new ParseDomException('load html error');
            }
        }
    }

    private function isHtml($source)
    {
        return strpos($source, '<html') !== false;
    }

    private function isXml($source)
    {
        return strpos($source, '<?xml') !== false;
    }

    /**
     * @codeCoverageIgnore
     *
     * @param string $name
     *
     * @return mixed
     */
    function __get($name)
    {
        switch ($name) {
            case 'outertext':
                return $this->outerHtml();
            case 'innertext':
                return $this->innerHtml();
            case 'plaintext':
                return $this->getPlainText();
            case 'href':
                return $this->getAttr("href");
            case 'src':
                return $this->getAttr("src");
            default:
                return null;
        }
    }

    /**
     * @param string       $selector
     * @param null|integer $idx 找第几个,从0开始计算，null 表示都返回, 负数表示倒数第几个
     *
     * @return \Generator|self|self[]|bool
     * @throws ParseDomException
     */
    public function get($selector, $idx = null)
    {
        $this->idx = $idx;
        $this->currentIdx = 0;
        if (empty($this->node->childNodes)) {
            return $this->throwException('dom没有子节点');
        }
        $selectors = $this->parse_selector($selector);
        if (($count = count($selectors)) === 0) {
            return $this->throwException('dom没有查到');
        }
        for ($c = 0; $c < $count; $c++) {
            if (($level = count($selectors[$c])) === 0) {
                return $this->throwException('selector没有');
            }
            yield from $this->search($this->node, $selectors[$c], $level);
        }
    }

    /**
     * @param $selector
     *
     * @return \Generator|self[]|bool
     * @throws ParseDomException
     */
    public function all($selector)
    {
        yield from $this->get($selector);
    }

    /**
     * 查找第几个节点
     *
     * @param string  $selector
     * @param integer $idx
     *
     * @return $this|bool
     * @throws ParseDomException
     */
    public function eq($selector, $idx)
    {
        $yield = $this->get($selector, $idx);
        foreach ($yield as $item) {
            return $item;
        }

        return $this->throwException('not found');
    }

    /**
     * @param     $selector
     * @param int $idx
     *
     * @return $this|bool|ParserDom
     * @throws ParseDomException
     */
    public function one($selector, $idx = 0)
    {
        return $this->eq($selector, $idx);
    }

    /**
     * 查找第一个节点
     *
     * @param string $selector
     *
     * @return $this|bool
     * @throws ParseDomException
     */
    public function first($selector)
    {
        return $this->eq($selector, 0);
    }

    /**
     * 查询最后一个节点
     *
     * @param string $selector
     *
     * @return $this|bool
     * @throws ParseDomException
     */
    public function end($selector)
    {
        return $this->eq($selector, -1);
    }


    /**
     * 深度优先查询
     *
     * @param string  $selector
     * @param integer $idx 找第几个,从0开始计算，null 表示都返回, 负数表示倒数第几个
     *
     * @return self|self[]|bool
     * @throws ParseDomException
     */
    public function find($selector, $idx = null)
    {
        $rows = [];
        foreach ($this->get($selector, $idx) as $item) {
            $rows[] = $item;
        }
        if (empty($rows)) {
            return $this->throwException("not found");
        }

        return is_null($idx) ? $rows : array_pop($rows);
    }

    /**
     * 返回文本信息
     *
     * @return string
     */
    public function getPlainText()
    {
        return $this->text($this->node);
    }

    /**
     * 获取innerHtml
     *
     * @return string
     */
    public function innerHtml()
    {
        $innerHTML = "";
        $children = $this->node->childNodes;
        foreach ($children as $child) {
            $innerHTML .= $this->node->ownerDocument->saveHTML($child) ?: '';
        }

        return $innerHTML;
    }

    /**
     * 获取outerHtml
     *
     * @return string|bool
     */
    public function outerHtml()
    {
        $doc = new \DOMDocument();
        $doc->appendChild($doc->importNode($this->node, true));

        return $doc->saveHTML($doc);
    }


    /**
     * 获取html的元属值
     *
     * @param string $name
     *
     * @return string|null
     */
    public function getAttr($name)
    {
        $oAttr = $this->node->attributes->getNamedItem($name);
        if (isset($oAttr)) {
            return $oAttr->nodeValue;
        }

        return null;
    }

    /**
     * 匹配
     *
     * @param string $exp
     * @param string $pattern
     * @param string $value
     *
     * @return boolean|number
     */
    private function match($exp, $pattern, $value)
    {
        $pattern = strtolower($pattern);
        $value = strtolower($value);
        switch ($exp) {
            case '=' :
                return ($value === $pattern);
            case '!=' :
                return ($value !== $pattern);
            case '^=' :
                return preg_match("/^" . preg_quote($pattern, '/') . "/", $value);
            case '$=' :
                return preg_match("/" . preg_quote($pattern, '/') . "$/", $value);
            case '*=' :
                if ($pattern [0] == '/') {
                    return preg_match($pattern, $value);
                }

                return preg_match("/" . $pattern . "/i", $value);
        }

        return false;
    }

    /**
     * 分析查询语句
     *
     * @param string $selector_string
     *
     * @return array
     */
    private function parse_selector($selector_string)
    {
        $pattern = '/([\w\-:\*]*)(?:\#([\w-]+)|\.([\w-]+))?(?:\[@?(!?[\w\-:]+)(?:([!*^$]?=)["\']?(.*?)["\']?)?\])?([\/, ]+)/is';
        preg_match_all($pattern, trim($selector_string) . ' ', $matches, PREG_SET_ORDER);
        $selectors = [];
        $result = [];
        foreach ($matches as $m) {
            $m [0] = trim($m [0]);
            if ($m [0] === '' || $m [0] === '/' || $m [0] === '//') {
                continue;
            }
            if ($m [1] === 'tbody') {
                continue;
            }
            [$tag, $key, $val, $exp, $no_key] = [$m [1], null, null, '=', false];
            if (! empty ($m [2])) {
                $key = 'id';
                $val = $m [2];
            }
            if (! empty ($m [3])) {
                $key = 'class';
                $val = $m [3];
            }
            if (! empty ($m [4])) {
                $key = $m [4];
            }
            if (! empty ($m [5])) {
                $exp = $m [5];
            }
            if (! empty ($m [6])) {
                $val = $m [6];
            }
            // convert to lowercase
            $tag = strtolower($tag);
            $key = strtolower($key);
            // elements that do NOT have the specified attribute
            if (isset ($key [0]) && $key [0] === '!') {
                $key = substr($key, 1);
                $no_key = true;
            }
            $result [] = [$tag, $key, $val, $exp, $no_key];
            if (trim($m [7]) === ',') {
                $selectors [] = $result;
                $result = [];
            }
        }
        if (count($result) > 0) {
            $selectors [] = $result;
        }

        return $selectors;
    }

    /**
     * 深度查询
     *
     * @param \DOMNode $search
     * @param array    $selectors
     * @param integer  $level
     * @param integer  $search_level
     *
     * @return bool|\Generator|self
     */
    private function search($search, $selectors, $level, $search_level = 0)
    {
        if ($search_level >= $level) {
            $rs = $this->seek($search, $selectors, $level - 1);
            if ($rs !== false) {
                if ($this->idx !== null) {
                    if (($this->idx < 0 && $this->currentIdx === abs($this->idx + 1)) ||
                        ($this->idx === $this->currentIdx)) {
                        yield new self($rs);

                        return true;
                    }
                    $this->currentIdx++;
                } else {
                    yield new self($rs);
                }
            }
        }
        if (! empty($search->childNodes)) {
            if (isset($this->idx) && $this->idx < 0) {
                // 如果负数 则通过取逆
                for ($count = $search->childNodes->count(); $count > 0; $count--) {
                    $yield = $this->search($search->childNodes->item($count - 1), $selectors, $level,
                        $search_level + 1);
                    foreach ($yield as $self) {
                        yield $self;
                    }
                    if ($yield->getReturn()) {
                        return $yield->getReturn();
                    }
                }
            } else {
                foreach ($search->childNodes as $val) {
                    $yield = $this->search($val, $selectors, $level, $search_level + 1);
                    foreach ($yield as $self) {
                        yield $self;
                    }
                    if ($yield->getReturn()) {
                        return $yield->getReturn();
                    }
                }
            }
        }

        return false;
    }

    /**
     * 获取tidy_node文本
     *
     * @param \DOMNode $node
     *
     * @return string
     */
    private function text($node)
    {
        return $node->textContent;
    }

    /**
     * 匹配节点,由于采取的倒序查找，所以时间复杂度为n+m*l n为总节点数，m为匹配最后一个规则的个数，l为规则的深度,
     *
     * @codeCoverageIgnore
     *
     * @param \DOMNode $search
     * @param array    $selectors
     * @param int      $current
     *
     * @return boolean|\DOMNode
     */
    private function seek($search, $selectors, $current)
    {
        if (! ($search instanceof \DOMElement)) {
            return false;
        }
        [$tag, $key, $val, $exp, $no_key] = $selectors [$current];

        $pass = true;
        if ($tag === '*' && ! $key) {
            throw new ParseDomException('tag为*时，key不能为空');
        }
        if ($tag && $tag != $search->tagName && $tag !== '*') {
            $pass = false;
        }
        if ($pass && $key) {
            if ($no_key) {
                if ($search->hasAttribute($key)) {
                    $pass = false;
                }
            } else {
                if ($key != "plaintext" && ! $search->hasAttribute($key)) {
                    $pass = false;
                }
            }
        }
        if ($pass && $key && $val && $val !== '*') {
            if ($key == "plaintext") {
                $nodeKeyValue = $this->text($search);
            } else {
                $nodeKeyValue = $search->getAttribute($key);
            }
            $check = $this->match($exp, $val, $nodeKeyValue);
            if (! $check && strcasecmp($key, 'class') === 0) {
                foreach (explode(' ', $search->getAttribute($key)) as $k) {
                    if (! empty ($k)) {
                        $check = $this->match($exp, $val, $k);
                        if ($check) {
                            break;
                        }
                    }
                }
            }
            if (! $check) {
                $pass = false;
            }
        }
        if ($pass) {
            $current--;
            if ($current < 0) {
                return $search;
            } else if ($this->seek($this->getParent($search), $selectors, $current)) {
                return $search;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 获取父亲节点
     *
     * @param \DOMNode $node
     *
     * @return \DOMNode
     */
    private function getParent($node)
    {
        return $node->parentNode;
    }

    private function throwException($msg)
    {
        if ($this->strict) {
            throw new ParseDomException($msg);
        }

        return $this->strict;
    }

}
