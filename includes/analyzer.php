<?php

class analyzer
{
    private $css;
    private $cssUrls = array();
    private $html;
    private $htmlUrls = array();
    private $parsedUrl;
    private $url;

    public function __construct()
    {
    }

    public function analyze($url, $depth = 0)
    {
        $this->url = $url;
        $this->parsedUrl = parse_url($url);


        $this->_loadHtml($url, $depth);

        $this->_findCss();
        $this->_findCssUsages();

        $txt = $this->_report();

        file_put_contents('result.txt', $txt);
        echo $txt;
    }

    private function _loadHtml($url, $depth)
    {
        if (in_array($url, $this->htmlUrls))
            return;

        $html = file_get_contents($url);
        $this->html[] = array(
            'url' => $url,
            'html' => $html,
            'depth' => $depth
        );
        $this->htmlUrls[] = $url;

        if ($depth > 0) {
            $internalLinks = $this->_findInternalLinks($html);
            if (!empty($internalLinks))
                foreach ($internalLinks as $link)
                    $this->_loadHtml($link, $depth - 1);
        }
    }

    /**
     * @param $html
     * @return Array
     */
    private function _findInternalLinks($html)
    {
        $result = array();
        $links = htmlqp($html)->find('a');
        foreach ($links as $l) {
            $href = $l->attr('href');

            if (substr($href, 0, 2) == '//')
                $href = $this->parsedUrl['scheme'] . ':' . $href;

            if (substr($href, 0, 1) == '/')
                $href = $this->parsedUrl['scheme'] . '://' . $this->parsedUrl['host'] . $href;

            $parsedUrl = parse_url($href);

            if (isset($parsedUrl['host']) and $parsedUrl['host'] == $this->parsedUrl['host'])
                $result[] = $href;
        }
        return $result;
    }

    private function _findCss()
    {
        foreach ($this->html as $h) {
            $files = htmlqp($h['html'])->find('link');
            foreach ($files as $file) {
                $href = $file->attr('href');

                if (substr($href, 0, 2) == '//')
                    $href = $this->parsedUrl['scheme'] . ':' . $href;

                if (substr($href, 0, 1) == '/')
                    $href = $this->parsedUrl['scheme'] . '://' . $this->parsedUrl['host'] . $href;


                $parsedUrl = parse_url($href);

                if (end(explode('.', $parsedUrl['path'])) == 'css' and !in_array($href, $this->cssUrls)) {
                    $this->cssUrls[] = $href;
                    $this->css[] = array(
                        'url' => $href,
                        'css' => file_get_contents($href)
                    );
                }
            }
        }

        foreach ($this->css as &$c) {
            if (!isset($c['css']))
                continue;

            $oCssParser = new Sabberworm\CSS\Parser($c['css']);
            unset($c['css']);
            $oCss = $oCssParser->parse();
            foreach ($oCss->getAllDeclarationBlocks() as $oBlock)
                foreach ($oBlock->getSelectors() as $oSelector) {
                    $_selector = $oSelector->getSelector();

                    $add = true;

                    foreach (array(':before', ':after', ':selection', ':indeterminate', ':lang') as $pseudo)
                        if (stripos($_selector, $pseudo) !== false)
                            $add = false;

                    if ($add)
                        $c['selectors'][] = array(
                            'selector' => $_selector,
                            'usages' => 0
                        );
                }
        }
    }

    private function _findCssUsages()
    {
        foreach ($this->html as $h) {
            $q = htmlqp($h['html']);
            foreach ($this->css as &$c)
                foreach ($c['selectors'] as &$s)
                    $s['usages'] += (int)count($q->find($s['selector']));
        }
    }

    private function _report()
    {
        $txt = 'Analyzing ' . $this->url . PHP_EOL . PHP_EOL;

        $txt .= 'Pages scanned - ' . count($this->htmlUrls) . PHP_EOL . PHP_EOL;
        $txt .= implode(PHP_EOL, $this->htmlUrls);

        $txt .= PHP_EOL . PHP_EOL . 'Css files scanned - ' . count($this->cssUrls);
        $txt .= implode(PHP_EOL, $this->cssUrls) . PHP_EOL . PHP_EOL;

        foreach ($this->css as &$c) {
            $txt .= $c['url'] . PHP_EOL . PHP_EOL;
            foreach ($c['selectors'] as &$s) {
                if ($s['usages'] < 1)
                    $txt .= $s['selector'] . PHP_EOL;
            }
            $txt .= PHP_EOL . PHP_EOL;
        }

        return $txt;
    }


}